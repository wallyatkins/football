<?php

declare(strict_types=1);

namespace WallyFootball\Services;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use WallyFootball\Database\Connection;

class SportsDataService
{
    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? Connection::getInstance();
    }

    public function getCurrentWeekInfo(): array
    {
        $data = $this->fetchScoreboardData();

        $season = (int) ($data['season']['year'] ?? ($data['content']['sbData']['season']['year'] ?? date('Y')));
        $week = (int) ($data['week']['number'] ?? ($data['content']['sbData']['week']['number'] ?? 1));

        return [
            'season_year' => $season > 0 ? $season : (int) date('Y'),
            'week_number' => $week > 0 ? $week : 1,
        ];
    }

    public function syncIfNeeded(int $season, int $week, ?int $ttlSeconds = null): ?array
    {
        $cacheDir = dirname(__DIR__, 2) . '/data';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        $cacheFile = "{$cacheDir}/.last_sync_{$season}_{$week}";

        // Dynamic TTL calculation if not explicitly supplied:
        if ($ttlSeconds === null) {
            try {
                $hasLiveGame = (bool) $this->db->queryValue(
                    "SELECT 1 FROM games WHERE season_year = :s AND week_number = :w AND status = 'in_progress' LIMIT 1",
                    ['s' => $season, 'w' => $week]
                );
            } catch (\Throwable) {
                $hasLiveGame = false;
            }

            if ($hasLiveGame) {
                $ttlSeconds = 60; // 1 minute during live games
            } else {
                try {
                    $recentOrUpcoming = (bool) $this->db->queryValue(
                        "SELECT 1 FROM games 
                         WHERE season_year = :s AND week_number = :w 
                           AND kickoff_time >= datetime('now', '-5 hours')
                           AND kickoff_time <= datetime('now', '+1 hour')
                         LIMIT 1",
                        ['s' => $season, 'w' => $week]
                    );
                } catch (\Throwable) {
                    $recentOrUpcoming = false;
                }

                $ttlSeconds = $recentOrUpcoming ? 180 : 1800; // 3 mins in game window, 30 mins outside
            }
        }

        if (file_exists($cacheFile)) {
            $mtime = filemtime($cacheFile);
            if ($mtime !== false && (time() - $mtime) < $ttlSeconds) {
                return null;
            }
        }

        try {
            $result = $this->syncWeek($season, $week);
            @touch($cacheFile);

            try {
                $scoring = new ScoringEngine($this->db);
                $scoring->gradeSurvivorWeek($season, $week);
            } catch (\Throwable) {
                // Non-blocking
            }

            return $result;
        } catch (\Throwable) {
            @touch($cacheFile);
            return null;
        }
    }

    public function syncWeek(int $seasonYear, int $weekNumber, bool $force = false): array
    {
        $data = $this->fetchScoreboardData($seasonYear, $weekNumber);
        $events = $data['events'] ?? ($data['content']['sbData']['events'] ?? []);

        if (empty($events)) {
            $errorMsg = "CRITICAL ALERT: NFL schedule sync failed for Season {$seasonYear} Week {$weekNumber}. Official ESPN feed returned 0 events.";
            error_log($errorMsg);
            try {
                $notifier = new NotificationService();
                $notifier->sendSms($errorMsg, "CRITICAL: NFL Schedule Sync Failed");
            } catch (\Throwable) {
                // Non-blocking notification error
            }
            throw new RuntimeException($errorMsg);
        }

        $synced = 0;
        $updated = 0;
        $officialKeys = [];
        $parsedGames = [];

        foreach ($events as $event) {
            $competitors = $event['competitions'][0]['competitors'] ?? ($event['competitors'] ?? []);
            if (empty($competitors)) {
                continue;
            }

            $dateIso = $event['date'] ?? null;
            if (!$dateIso) {
                continue;
            }

            $kickoff = new DateTimeImmutable($dateIso);
            $kickoffUtc = $kickoff->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');

            // Detect Monday Night Football: Game played Monday evening US Eastern Time
            $kickoffEt = $kickoff->setTimezone(new DateTimeZone('America/New_York'));
            $isMnf = ($kickoffEt->format('N') === '1' && (int) $kickoffEt->format('G') >= 17);

            $statusType = $event['status']['type']['name'] ?? ($event['status'] ?? 'scheduled');
            $status = match ($statusType) {
                'STATUS_FINAL', 'STATUS_FINAL_OVERTIME', 'post', 'final' => 'final',
                'STATUS_IN_PROGRESS', 'STATUS_HALFTIME', 'STATUS_END_PERIOD', 'in' => 'in_progress',
                default => 'scheduled',
            };

            $homeTeam = '';
            $awayTeam = '';
            $homeScore = null;
            $awayScore = null;

            foreach ($competitors as $competitor) {
                $abbr = strtoupper($competitor['abbreviation'] ?? ($competitor['team']['abbreviation'] ?? ''));
                $score = (isset($competitor['score']) && is_numeric($competitor['score'])) ? (int) $competitor['score'] : null;

                if (($competitor['homeAway'] ?? '') === 'home') {
                    $homeTeam = $abbr;
                    $homeScore = $score;
                } else {
                    $awayTeam = $abbr;
                    $awayScore = $score;
                }
            }

            $homeTeam = \WallyFootball\Support\TeamData::normalize($homeTeam);
            $awayTeam = \WallyFootball\Support\TeamData::normalize($awayTeam);

            if (!$homeTeam || !$awayTeam || $homeTeam === $awayTeam) {
                continue;
            }

            $matchupTeams = [$homeTeam, $awayTeam];
            sort($matchupTeams);
            $matchupKey = $matchupTeams[0] . '_' . $matchupTeams[1];
            $officialKeys[$matchupKey] = true;

            $parsedGames[] = [
                'home' => $homeTeam,
                'away' => $awayTeam,
                'kickoff' => $kickoffUtc,
                'is_mnf' => $isMnf ? 1 : 0,
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'status' => $status,
                'matchup_key' => $matchupKey,
            ];
        }

        // Reconcile database: Remove obsolete or erroneous games for this week not in official feed
        if (!empty($officialKeys)) {
            $existingGames = $this->db->query(
                'SELECT id, home_team, away_team, status FROM games WHERE season_year = :s AND week_number = :w',
                ['s' => $seasonYear, 'w' => $weekNumber]
            );

            foreach ($existingGames as $eg) {
                $h = \WallyFootball\Support\TeamData::normalize($eg['home_team']);
                $a = \WallyFootball\Support\TeamData::normalize($eg['away_team']);
                $teams = [$h, $a];
                sort($teams);
                $key = $teams[0] . '_' . $teams[1];

                if (!isset($officialKeys[$key])) {
                    // Stale matchup from outdated seed or old season
                    $this->db->execute('DELETE FROM pickem_picks WHERE game_id = :gid', ['gid' => $eg['id']]);
                    $this->db->execute('DELETE FROM games WHERE id = :id', ['id' => $eg['id']]);
                    // Unlock pickem entries if obsolete games were removed so players can make valid picks
                    $this->db->execute(
                        'UPDATE pickem_entries SET is_locked = 0 WHERE season_year = :s AND week_number = :w',
                        ['s' => $seasonYear, 'w' => $weekNumber]
                    );
                }
            }
        }

        // Upsert verified games
        foreach ($parsedGames as $pg) {
            $existing = $this->db->queryOne(
                'SELECT id FROM games 
                 WHERE season_year = :season AND week_number = :week 
                   AND ((home_team = :home AND away_team = :away) OR (home_team = :away AND away_team = :home))',
                [
                    'season' => $seasonYear,
                    'week' => $weekNumber,
                    'home' => $pg['home'],
                    'away' => $pg['away'],
                ]
            );

            if ($existing) {
                $this->db->execute(
                    'UPDATE games SET 
                        home_team = :home,
                        away_team = :away,
                        kickoff_time = :kickoff, 
                        is_mnf = :is_mnf,
                        home_score = :home_score, 
                        away_score = :away_score, 
                        status = :status 
                     WHERE id = :id',
                    [
                        'home' => $pg['home'],
                        'away' => $pg['away'],
                        'kickoff' => $pg['kickoff'],
                        'is_mnf' => $pg['is_mnf'],
                        'home_score' => $pg['home_score'],
                        'away_score' => $pg['away_score'],
                        'status' => $pg['status'],
                        'id' => $existing['id'],
                    ]
                );
                $updated++;
            } else {
                $this->db->execute(
                    'INSERT INTO games (season_year, week_number, home_team, away_team, kickoff_time, is_mnf, home_score, away_score, status)
                     VALUES (:season, :week, :home, :away, :kickoff, :is_mnf, :home_score, :away_score, :status)',
                    [
                        'season' => $seasonYear,
                        'week' => $weekNumber,
                        'home' => $pg['home'],
                        'away' => $pg['away'],
                        'kickoff' => $pg['kickoff'],
                        'is_mnf' => $pg['is_mnf'],
                        'home_score' => $pg['home_score'],
                        'away_score' => $pg['away_score'],
                        'status' => $pg['status'],
                    ]
                );
                $synced++;
            }
        }

        $this->deduplicateWeek($seasonYear, $weekNumber);
        $this->ensureTiebreakerSelected($seasonYear, $weekNumber);

        return [
            'total_events' => count($events),
            'inserted' => $synced,
            'updated' => $updated,
        ];
    }

    private function fetchScoreboardData(?int $season = null, ?int $week = null): array
    {
        $urls = [];
        if ($season !== null && $week !== null) {
            $urls[] = sprintf('https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates=%d&seasontype=2&week=%d', $season, $week);
            $urls[] = sprintf('https://site.web.api.espn.com/apis/v2/scoreboard/header?sport=football&league=nfl&dates=%d&seasontype=2&week=%d', $season, $week);
            $urls[] = sprintf('http://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates=%d&seasontype=2&week=%d', $season, $week);
        } else {
            $urls[] = 'https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard';
            $urls[] = 'https://site.web.api.espn.com/apis/v2/scoreboard/header?sport=football&league=nfl';
            $urls[] = 'http://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard';
        }

        foreach ($urls as $url) {
            try {
                $json = $this->fetchJson($url);
                $events = $json['events'] ?? ($json['content']['sbData']['events'] ?? ($json['sports'][0]['leagues'][0]['events'] ?? []));
                if (!empty($events) && is_array($events)) {
                    if (isset($json['sports'][0]['leagues'][0]['events']) && $week !== null) {
                        $events = array_values(array_filter($events, fn($e) => ($e['seasonType'] ?? 2) == 2 && ($e['week'] ?? 1) == $week));
                    }
                    if (!empty($events)) {
                        $json['events'] = $events;
                        return $json;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }

    private function fetchJson(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'curl/7.88.1',
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
            ],
            CURLOPT_TIMEOUT => 6,
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($res === false || $code !== 200) {
            throw new RuntimeException("Failed fetching sports data ({$code}): {$err}");
        }

        $json = json_decode($res, true);
        if (!is_array($json)) {
            throw new RuntimeException("Invalid JSON received from sports data provider.");
        }

        return $json;
    }

    public function syncSeason(int $season = 2026): array
    {
        $summary = [
            'synced_weeks' => 0,
            'total_events' => 0,
            'inserted' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        for ($w = 1; $w <= 18; $w++) {
            try {
                $res = $this->syncWeek($season, $w);
                $summary['synced_weeks']++;
                $summary['total_events'] += ($res['total_events'] ?? 0);
                $summary['inserted'] += ($res['inserted'] ?? 0);
                $summary['updated'] += ($res['updated'] ?? 0);
            } catch (\Throwable $e) {
                $summary['errors'][$w] = $e->getMessage();
            }
        }

        return $summary;
    }

    public function deduplicateWeek(int $season, int $week): void
    {
        $games = $this->db->query(
            'SELECT id, home_team, away_team FROM games WHERE season_year = :s AND week_number = :w ORDER BY id ASC',
            ['s' => $season, 'w' => $week]
        );

        $seenMatchups = [];
        $seenTeams = [];
        $toDelete = [];

        foreach ($games as $g) {
            $h = \WallyFootball\Support\TeamData::normalize($g['home_team']);
            $a = \WallyFootball\Support\TeamData::normalize($g['away_team']);
            $teams = [$h, $a];
            sort($teams);
            $matchupKey = $teams[0] . '_' . $teams[1];

            if (isset($seenMatchups[$matchupKey]) || isset($seenTeams[$h]) || isset($seenTeams[$a])) {
                $toDelete[] = (int) $g['id'];
            } else {
                $seenMatchups[$matchupKey] = (int) $g['id'];
                $seenTeams[$h] = (int) $g['id'];
                $seenTeams[$a] = (int) $g['id'];
            }
        }

        if (!empty($toDelete)) {
            $delList = implode(',', $toDelete);
            $this->db->execute("DELETE FROM pickem_picks WHERE game_id IN ({$delList})");
            $this->db->execute("DELETE FROM games WHERE id IN ({$delList})");
        }
    }

    public function ensureTiebreakerSelected(int $season, int $week): void
    {
        // Tiebreaker must be Monday Night Football or the game with the latest kickoff time in the week
        $latestGame = $this->db->queryOne(
            'SELECT id FROM games 
             WHERE season_year = :s AND week_number = :w 
             ORDER BY kickoff_time DESC, id DESC LIMIT 1',
            ['s' => $season, 'w' => $week]
        );

        if ($latestGame) {
            $targetId = (int) $latestGame['id'];
            $this->db->execute(
                'UPDATE games SET is_mnf = 0 WHERE season_year = :s AND week_number = :w',
                ['s' => $season, 'w' => $week]
            );
            $this->db->execute(
                'UPDATE games SET is_mnf = 1 WHERE id = :id',
                ['id' => $targetId]
            );
        }
    }
}
