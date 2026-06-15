<?php

namespace App\Services;

use App\Models\SystemSettings;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    public function getSettings(): ?SystemSettings
    {
        return SystemSettings::first();
    }

    public function saveSettings(array $data): void
    {
        $payload = [
            'github_nickname' => $data['github_nickname'] ?? null,
            'github_email'    => $data['github_email']    ?? null,
        ];

        // Only update the token if a new non-empty PAT was provided
        if (!empty($data['github_token'])) {
            $payload['github_token'] = Crypt::encryptString($data['github_token']);
        }

        SystemSettings::updateOrCreate(['id' => 1], $payload);
    }

    public function buildPatUrl(string $repoName): string
    {
        $settings = SystemSettings::first();

        if (!$settings) {
            throw new \RuntimeException('GitHub settings not configured. Please visit Settings first.');
        }

        // $token is a local variable only — discarded after this method returns
        $token = Crypt::decryptString($settings->github_token);

        return "https://{$token}@github.com/{$settings->github_nickname}/{$repoName}.git";
    }
}
