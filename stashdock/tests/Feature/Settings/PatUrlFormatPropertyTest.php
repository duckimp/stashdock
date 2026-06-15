<?php
// Feature: stashdock, Property 4: PAT-injected URL format correctness
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('PAT-injected URL matches required format', function () {
    $service = app(SettingsService::class);

    for ($i = 0; $i < 20; $i++) {
        $pat      = bin2hex(random_bytes(20));
        $nickname = 'user' . $i;
        $repoName = 'repo-' . $i;

        $service->saveSettings([
            'github_nickname' => $nickname,
            'github_email'    => 'test@example.com',
            'github_token'    => $pat,
        ]);

        $url = $service->buildPatUrl($repoName);

        expect($url)->toMatch('#^https://[^@]+@github\.com/[^/]+/[^/]+\.git$#');

        // URL must NOT be stored in the database
        $dbToken = \App\Models\SystemSettings::first()->getRawOriginal('github_token');
        expect($dbToken)->not->toContain($pat);
    }
});
