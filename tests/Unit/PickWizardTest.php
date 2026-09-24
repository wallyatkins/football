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

        // 6. Authentic Press Your Luck 18-Square Board & Larson Patterns
        $this->assertStringContainsString('class="pyl-chassis', $output);
        for ($sq = 1; $sq <= 18; $sq++) {
            $this->assertStringContainsString('id="pyl-sq-' . $sq . '"', $output);
        }
        $this->assertStringContainsString('LARSON_PATTERNS', $output);
        $this->assertStringContainsString('id="btnPylBuzzer"', $output);
        $this->assertStringContainsString('id="whammyCanvas"', $output);
        $this->assertStringContainsString('/media/press-your-luck-sound-board.mp3', $output);
        $this->assertStringContainsString('/media/nfl-theme.mp3', $output);

        // 7. Survivor Catch-Up & Handicap Endpoint
        $this->assertStringContainsString('/survivor/burn-handicap', $output);
        $this->assertStringContainsString('id="survivorGridList"', $output);
        $this->assertStringContainsString('id="survivorConfirmModal"', $output);

        // 8. Strict Navigation Labels (PREV & NEXT) & Score Generator
        $this->assertStringContainsString('>PREV<', $output);
        $this->assertStringContainsString('>NEXT<', $output);
        $this->assertStringContainsString('id="btnSpinTb"', $output);
        $this->assertStringContainsString('handleTiebreakerSpin', $output);

        // 9. Informational Onboarding Overlays & Help
        $this->assertStringContainsString('id="pickemIntroOverlay"', $output);
        $this->assertStringContainsString('id="survivorIntroOverlay"', $output);
        $this->assertStringContainsString('id="wizardHelpModal"', $output);
        $this->assertStringContainsString('id="btnWizardHelp"', $output);
        $this->assertStringContainsString('dismissPickemIntro', $output);
        $this->assertStringContainsString('dismissSurvivorIntro', $output);

        // 10. De-cluttered Card Bodies (No generic buttons, no division labels, no system text)
        $this->assertStringNotContainsString('Select Chargers', $output);
        $this->assertStringNotContainsString('Select Bills', $output);
        $this->assertStringNotContainsString('AFC West', $output);
        $this->assertStringNotContainsString('Auto-saves in real-time', $output);
    }

    public function testIndexPhpRegistersWizardAndSurvivorRoutes(): void
    {
        $indexPath = dirname(__DIR__, 2) . '/public/index.php';
        $content = file_get_contents($indexPath);

        $this->assertStringContainsString("case '/pickem/wizard':", $content);
        $this->assertStringContainsString("case '/survivor/burn-handicap':", $content);
        $this->assertStringContainsString("case '/api/user/dismiss-intro':", $content);
    }
}
