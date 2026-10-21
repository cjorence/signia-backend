<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckProductionReadiness extends Command
{
    protected $signature = 'signia:check-production';
    protected $description = 'Check deployment safety settings without printing credentials or changing data';

    public function handle(): int
    {
        $turnstile = (string) config('services.turnstile.secret_key');
        $origins = config('cors.allowed_origins', []);
        $checks = [
            'APP_ENV must be production' => app()->environment('production'),
            'APP_DEBUG must be false' => ! config('app.debug'),
            'APP_URL must use HTTPS' => str_starts_with((string) config('app.url'), 'https://'),
            'Turnstile must use a real production secret' => $turnstile !== '' && ! preg_match('/^[123]x0+AA$/', $turnstile),
            'CORS origins must be explicit HTTPS origins' => count($origins) > 0 && collect($origins)->every(fn ($origin) => str_starts_with($origin, 'https://') && ! str_contains($origin, '*')),
            'FSL_SERVICE_TOKEN must contain at least 32 characters' => strlen((string) config('recognition.token')) >= 32,
            'Mail must use a delivery transport' => ! in_array(config('mail.default'), ['log', 'array'], true),
        ];
        $failed = false;
        foreach ($checks as $message => $passed) {
            if ($passed) $this->info('PASS: '.$message);
            else { $this->error('FAIL: '.$message); $failed = true; }
        }
        $this->line('This checks configuration only. Verify backups, HTTPS, real mail, payments and recognition separately.');
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
