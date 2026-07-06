<?php

namespace App\Console\Commands;

use App\Mail\BoatIntakeConfirmationMail;
use App\Models\BoatIntake;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailCommand extends Command
{
    protected $signature   = 'mail:test {email} {--intake= : Use a real intake ID for a full template test}';
    protected $description = 'Send a test email to diagnose SMTP delivery issues';

    public function handle(): int
    {
        $to = $this->argument('email');
        $this->info("Sending test email to: {$to}");
        $this->info('SMTP host:   ' . config('mail.mailers.smtp.host'));
        $this->info('SMTP port:   ' . config('mail.mailers.smtp.port'));
        $this->info('SMTP scheme: ' . (config('mail.mailers.smtp.scheme') ?: '(none)'));
        $this->info('From:        ' . config('mail.from.address'));

        // ── Plain raw email test ──────────────────────────────────────────
        $this->line('');
        $this->line('Step 1: Plain text email...');
        try {
            Mail::raw("Dit is een testmail van Schepenkring ({$to}).", function ($m) use ($to) {
                $m->to($to)->subject('Schepenkring mail test');
            });
            $this->info('  ✓ Plain text email sent successfully');
        } catch (\Throwable $e) {
            $this->error('  ✗ Plain text email FAILED: ' . $e->getMessage());
            $this->error('    Class: ' . get_class($e));
            $this->error('    File:  ' . $e->getFile() . ':' . $e->getLine());
            return self::FAILURE;
        }

        // ── Full Mailable test ────────────────────────────────────────────
        $intakeId = $this->option('intake');
        if ($intakeId) {
            $intake = BoatIntake::with('location')->find($intakeId);
            if (! $intake) {
                $this->error("Intake #{$intakeId} not found.");
                return self::FAILURE;
            }
        } else {
            // Use the most recent intake as sample data
            $intake = BoatIntake::with('location')->latest()->first();
        }

        if ($intake) {
            $this->line('');
            $this->line("Step 2: BoatIntakeConfirmationMail (intake #{$intake->id})...");
            try {
                $fakeScore = [
                    'total'              => 60,
                    'breakdown'          => ['fields' => 100, 'photos' => 50, 'description' => 4, 'documents' => 0],
                    'missing'            => [],
                    'photo_count'        => 6,
                    'photo_target'       => 12,
                    'description_length' => 50,
                    'description_target' => 500,
                ];
                Mail::to($to)->send(new BoatIntakeConfirmationMail($intake, $fakeScore, 'https://schepen-kring.nl/nl/boot-aanmelden/aanvullen?token=test'));
                $this->info('  ✓ BoatIntakeConfirmationMail sent successfully');
            } catch (\Throwable $e) {
                $this->error('  ✗ BoatIntakeConfirmationMail FAILED: ' . $e->getMessage());
                $this->error('    Class: ' . get_class($e));
                $this->error('    File:  ' . $e->getFile() . ':' . $e->getLine());
                $this->error('    Trace: ' . $e->getTraceAsString());
                return self::FAILURE;
            }
        } else {
            $this->warn('No intakes found — skipped full template test.');
        }

        $this->line('');
        $this->info('All tests passed. Check your inbox (and spam folder).');
        return self::SUCCESS;
    }
}
