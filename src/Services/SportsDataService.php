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

    public function syncWeek(int $seasonYear, int $weekNumber): array
    {
        $data = $this->fetchScoreboardData($seasonYear, $weekNumber);
        $events = $data['events'] ?? ($data['content']['sbData']['events'] ?? []);

        if (empty($events)) {
            // Fallback to offline schedule seed if feed is unavailable
            return $this->seedOfflineWeek($seasonYear, $weekNumber);
        }

        $synced = 0;
        $updated = 0;

        foreach ($events as $event) {
            $competition = $event['competitions'][0] ?? null;
            if (!$competition) {
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
            $isMnf = ($kickoffEt->format('N') === '1' && (int)$kickoffEt->format('G') >= 17);

            $statusType = $event['status']['type']['name'] ?? 'STATUS_SCHEDULED';
            $status = match ($statusType) {
                'STATUS_FINAL', 'STATUS_FINAL_OVERTIME' => 'final',
                'STATUS_IN_PROGRESS', 'STATUS_HALFTIME', 'STATUS_END_PERIOD' => 'in_progress',
                default => 'scheduled',
            };

            $homeTeam = '';
            $awayTeam = '';
            $homeScore = null;
            $awayScore = null;

            foreach ($competition['competitors'] ?? [] as $competitor) {
                $abbr = strtoupper($competitor['team']['abbreviation'] ?? '');
                $score = isset($competitor['score']) ? (int) $competitor['score'] : null;

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

            // Check if game already exists (in either orientation)
            $existing = $this->db->queryOne(
                'SELECT id FROM games 
                 WHERE season_year = :season AND week_number = :week 
                   AND ((home_team = :home AND away_team = :away) OR (home_team = :away AND away_team = :home))',
                [
                    'season' => $seasonYear,
                    'week' => $weekNumber,
                    'home' => $homeTeam,
                    'away' => $awayTeam,
                ]
            );

            if ($existing) {
                $this->db->execute(
                    'UPDATE games SET 
                        home_team = :home,
                        away_team = :away,
                        kickoff_time = :kickoff, 
                        home_score = :home_score, 
                        away_score = :away_score, 
                        status = :status 
                     WHERE id = :id',
                    [
                        'home' => $homeTeam,
                        'away' => $awayTeam,
                        'kickoff' => $kickoffUtc,
                        'home_score' => $homeScore,
                        'away_score' => $awayScore,
                        'status' => $status,
                        'id' => $existing['id'],
                    ]
                );
                $updated++;
            } else {
                $this->db->execute(
                    'INSERT INTO games (season_year, week_number, home_team, away_team, kickoff_time, is_mnf, home_score, away_score, status)
                     VALUES (:season, :week, :home, :away, :kickoff, 0, :home_score, :away_score, :status)',
                    [
                        'season' => $seasonYear,
                        'week' => $weekNumber,
                        'home' => $homeTeam,
                        'away' => $awayTeam,
                        'kickoff' => $kickoffUtc,
                        'home_score' => $homeScore,
                        'away_score' => $awayScore,
                        'status' => $status,
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
            $urls[] = sprintf('https://cdn.espn.com/core/nfl/scoreboard?xhr=1&dates=%d&seasontype=2&week=%d', $season, $week);
            $urls[] = sprintf('http://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates=%d&seasontype=2&week=%d', $season, $week);
        } else {
            $urls[] = 'https://cdn.espn.com/core/nfl/scoreboard?xhr=1';
            $urls[] = 'http://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard';
        }

        foreach ($urls as $url) {
            try {
                return $this->fetchJson($url);
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
        $mockMatchups = [
            ['KC', 'BAL', '2026-09-10 00:20:00+00', false],
            ['PHI', 'GB', '2026-09-11 00:15:00+00', false],
            ['ATL', 'PIT', '2026-09-13 17:00:00+00', false],
            ['BUF', 'ARI', '2026-09-13 17:00:00+00', false],
            ['CHI', 'TEN', '2026-09-13 17:00:00+00', false],
            ['CIN', 'NE', '2026-09-13 17:00:00+00', false],
            ['IND', 'HOU', '2026-09-13 17:00:00+00', false],
            ['MIA', 'JAX', '2026-09-13 17:00:00+00', false],
            ['NO', 'CAR', '2026-09-13 17:00:00+00', false],
            ['NYG', 'MIN', '2026-09-13 17:00:00+00', false],
            ['LAC', 'LV', '2026-09-13 20:05:00+00', false],
            ['SEA', 'DEN', '2026-09-13 20:05:00+00', false],
            ['CLE', 'DAL', '2026-09-13 20:25:00+00', false],
            ['TB', 'WSH', '2026-09-13 20:25:00+00', false],
            ['DET', 'LAR', '2026-09-14 00:20:00+00', false],
            ['SF', 'NYJ', '2026-09-15 00:15:00+00', false],
        ];

        $synced = 0;
        $updated = 0;

        foreach ($mockMatchups as [$home, $away, $kickoff, $isMnf]) {
            $home = \WallyFootball\Support\TeamData::normalize($home);
            $away = \WallyFootball\Support\TeamData::normalize($away);

            $existing = $this->db->queryOne(
                'SELECT id FROM games 
                 WHERE season_year = :season AND week_number = :week 
                   AND ((home_team = :home AND away_team = :away) OR (home_team = :away AND away_team = :home))',
                ['season' => $season, 'week' => $week, 'home' => $home, 'away' => $away]
            );

            if ($existing) {
                $updated++;
            } else {
                $this->db->execute(
                    'INSERT INTO games (season_year, week_number, home_team, away_team, kickoff_time, is_mnf, status)
                     VALUES (:season, :week, :home, :away, :kickoff, 0, "scheduled")',
                    ['season' => $season, 'week' => $week, 'home' => $home, 'away' => $away, 'kickoff' => $kickoff]
                );
                $synced++;
            }
        }

        $this->deduplicateWeek($season, $week);
        $this->ensureTiebreakerSelected($season, $week);

        return ['total_events' => count($mockMatchups), 'inserted' => $synced, 'updated' => $updated];
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
        $hasTiebreaker = (int) $this->db->queryValue(
            'SELECT COUNT(*) FROM games WHERE season_year = :s AND week_number = :w AND is_mnf = 1',
            ['s' => $season, 'w' => $week]
        );

        if ($hasTiebreaker === 0) {
            $games = $this->db->query(
                'SELECT id FROM games WHERE season_year = :s AND week_number = :w ORDER BY id ASC',
                ['s' => $season, 'w' => $week]
            );
            if (!empty($games)) {
                $idx = abs(crc32("random_tiebreaker_{$season}_{$week}")) % count($games);
                $selectedId = (int) $games[$idx]['id'];
                $this->db->execute('UPDATE games SET is_mnf = 1 WHERE id = :id', ['id' => $selectedId]);
            }
        }
    }
}
