<?php

declare(strict_types=1);

namespace WallyFootball\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PickWizardTest extends TestCase
{
    public function testWizardTemplateRendersCompleteInterfaceAndDataContract(): void
    {
        $games = [
            [
                'id' => 101,
                'season_year' => 2026,
                'week_number' => 2,
                'home_team' => 'KC',
                'away_team' => 'BAL',
                'kickoff_time' => '2026-09-20 17:00:00+00',
                'status' => 'final',
                'home_score' => 27,
                'away_score' => 20,
                'is_mnf' => 0,
                'is_locked' => true,
                'user_pick' => 'KC',
                'winning_team' => 'KC',
                'pick_result' => 'correct',
            ],
            [
                'id' => 102,
                'season_year' => 2026,
                'week_number' => 2,
                'home_team' => 'LAR',
                'away_team' => 'NYG',
                'kickoff_time' => '2026-09-21 20:15:00-04',
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
                'is_mnf' => 1,
                'is_locked' => false,
                'user_pick' => null,
                'winning_team' => null,
                'pick_result' => 'pending',
            ],
        ];

        $userPicks = [101 => 'KC'];
        $season = 2026;
        $week = 2;
        $entry = [
            'id' => 1,
            'user_id' => 10,
            'mnf_total_points_prediction' => 48,
            'payment_status' => 'paid',
            'is_locked' => 0,
        ];
        $tiebreakerGame = $games[1];
        $isWeekLocked = false;

        ob_start();
        require dirname(__DIR__, 2) . '/templates/pickem/wizard.php';
        $output = ob_get_clean();

        // 1. Core Modal Structure
        $this->assertStringContainsString('id="pickWizardModal"', $output);
        $this->assertStringContainsString('id="btnWizardExit"', $output);
        $this->assertStringContainsString('Exit to Standard View', $output);

        // 2. Focused Landscape Matchup Elements
        $this->assertStringContainsString('id="wizardAwayCard"', $output);
        $this->assertStringContainsString('id="wizardHomeCard"', $output);
        $this->assertStringContainsString('id="wizardCardContainer"', $output);
        $this->assertStringContainsString('VS', $output);

        // 3. Navigation Controls
        $this->assertStringContainsString('id="btnWizardPrev"', $output);
        $this->assertStringContainsString('id="btnWizardNext"', $output);
        $this->assertStringContainsString('id="wizardTimeline"', $output);
        $this->assertStringContainsString('id="wizardProgressBar"', $output);

        // 4. Tiebreaker & Audio
        $this->assertStringContainsString('id="wizardTiebreakerBox"', $output);
        $this->assertStringContainsString('id="btnWizardAudioToggle"', $output);
        $this->assertStringContainsString('playPickSound', $output);
        $this->assertStringContainsString('playAdvanceSound', $output);
        $this->assertStringContainsString('playCelebrationSound', $output);

        // 5. Shared State & Autosave Hooks
        $this->assertStringContainsString('/pickem/autosave', $output);
        $this->assertStringContainsString('syncWithStandardGrid', $output);
        $this->assertStringContainsString('id="wizardCompletionView"', $output);
    }

    public function testIndexPhpRegistersWizardRoute(): void
    {
        $indexPath = dirname(__DIR__, 2) . '/public/index.php';
        $content = file_get_contents($indexPath);

        $this->assertStringContainsString("case '/pickem/wizard':", $content);
    }
}
