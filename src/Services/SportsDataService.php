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

    public function syncWeek(int $seasonYear, int $weekNumber, bool $force = false): array
    {
        $data = $this->fetchScoreboardData($seasonYear, $weekNumber);
        $events = $data['events'] ?? ($data['content']['sbData']['events'] ?? []);

        if (empty($events)) {
            // Fallback to offline schedule seed if live feed is unavailable
            return $this->seedOfflineWeek($seasonYear, $weekNumber);
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
            $urls[] = sprintf('https://site.web.api.espn.com/apis/v2/scoreboard/header?sport=football&league=nfl&dates=%d&seasontype=2&week=%d', $season, $week);
            $urls[] = sprintf('https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates=%d&seasontype=2&week=%d', $season, $week);
            $urls[] = sprintf('http://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates=%d&seasontype=2&week=%d', $season, $week);
        } else {
            $urls[] = 'https://site.web.api.espn.com/apis/v2/scoreboard/header?sport=football&league=nfl';
            $urls[] = 'https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard';
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
                    $json['events'] = $events;
                    return $json;
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
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/plain, */*',
                'Accept-Language: en-US,en;q=0.9',
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

    private function seedOfflineWeek(int $season, int $week): array
    {
        // Verified 2026 NFL Regular Season Official Schedule (16 games / week)
        $official2026Schedule = [
            1 => [
                ['SEA', 'NE', '2026-09-10 00:20:00+00', false], // Kickoff: Wed Sep 9 8:20 PM ET (Tonight!)
                ['LAR', 'SF', '2026-09-11 00:35:00+00', false], // Thu Sep 10 8:35 PM ET
                ['CIN', 'TB', '2026-09-13 17:00:00+00', false], // Sun Sep 13 1:00 PM ET
                ['DET', 'NO', '2026-09-13 17:00:00+00', false], // Sun Sep 13 1:00 PM ET
                ['TEN', 'NYJ', '2026-09-13 17:00:00+00', false], // Sun Sep 13 1:00 PM ET
                ['IND', 'BAL', '2026-09-13 17:00:00+00', false], // Sun Sep 13 1:00 PM ET
                ['PIT', 'ATL', '2026-09-13 17:00:00+00', false], // Sun Sep 13 1:00 PM ET
                ['CAR', 'CHI', '2026-09-13 17:00:00+00', false], // Sun Sep 13 1:00 PM ET
                ['JAX', 'CLE', '2026-09-13 17:00:00+00', false], // Sun Sep 13 1:00 PM ET
                ['HOU', 'BUF', '2026-09-13 17:00:00+00', false], // Sun Sep 13 1:00 PM ET
                ['LV', 'MIA', '2026-09-13 20:25:00+00', false], // Sun Sep 13 4:25 PM ET
                ['MIN', 'GB', '2026-09-13 20:25:00+00', false], // Sun Sep 13 4:25 PM ET
                ['PHI', 'WAS', '2026-09-13 20:25:00+00', false], // Sun Sep 13 4:25 PM ET
                ['LAC', 'ARI', '2026-09-13 20:25:00+00', false], // Sun Sep 13 4:25 PM ET
                ['NYG', 'DAL', '2026-09-14 00:20:00+00', false], // Sun Sep 13 8:20 PM ET (SNF)
                ['KC', 'DEN', '2026-09-15 00:15:00+00', true],   // Mon Sep 14 8:15 PM ET (MNF Tiebreaker)
            ],
        ];

        $matchups = $official2026Schedule[$week] ?? [];
        if (empty($matchups)) {
            return ['total_events' => 0, 'inserted' => 0, 'updated' => 0];
        }

        $synced = 0;
        $updated = 0;
        $officialKeys = [];

        foreach ($matchups as [$home, $away, $kickoff, $isMnf]) {
            $home = \WallyFootball\Support\TeamData::normalize($home);
            $away = \WallyFootball\Support\TeamData::normalize($away);

            $teams = [$home, $away];
            sort($teams);
            $key = $teams[0] . '_' . $teams[1];
            $officialKeys[$key] = true;

            $existing = $this->db->queryOne(
                'SELECT id FROM games 
                 WHERE season_year = :season AND week_number = :week 
                   AND ((home_team = :home AND away_team = :away) OR (home_team = :away AND away_team = :home))',
                ['season' => $season, 'week' => $week, 'home' => $home, 'away' => $away]
            );

            if ($existing) {
                $this->db->execute(
                    'UPDATE games SET 
                        home_team = :home,
                        away_team = :away,
                        kickoff_time = :kickoff, 
                        is_mnf = :is_mnf
                     WHERE id = :id',
                    ['home' => $home, 'away' => $away, 'kickoff' => $kickoff, 'is_mnf' => $isMnf ? 1 : 0, 'id' => $existing['id']]
                );
                $updated++;
            } else {
                $this->db->execute(
                    'INSERT INTO games (season_year, week_number, home_team, away_team, kickoff_time, is_mnf, status)
                     VALUES (:season, :week, :home, :away, :kickoff, :is_mnf, "scheduled")',
                    ['season' => $season, 'week' => $week, 'home' => $home, 'away' => $away, 'kickoff' => $kickoff, 'is_mnf' => $isMnf ? 1 : 0]
                );
                $synced++;
            }
        }

        // Purge obsolete games
        $existingGames = $this->db->query(
            'SELECT id, home_team, away_team FROM games WHERE season_year = :s AND week_number = :w',
            ['s' => $season, 'w' => $week]
        );
        foreach ($existingGames as $eg) {
            $h = \WallyFootball\Support\TeamData::normalize($eg['home_team']);
            $a = \WallyFootball\Support\TeamData::normalize($eg['away_team']);
            $t = [$h, $a];
            sort($t);
            $k = $t[0] . '_' . $t[1];
            if (!isset($officialKeys[$k])) {
                $this->db->execute('DELETE FROM pickem_picks WHERE game_id = :gid', ['gid' => $eg['id']]);
                $this->db->execute('DELETE FROM games WHERE id = :id', ['id' => $eg['id']]);
            }
        }

        $this->deduplicateWeek($season, $week);
        $this->ensureTiebreakerSelected($season, $week);

        return ['total_events' => count($matchups), 'inserted' => $synced, 'updated' => $updated];
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
