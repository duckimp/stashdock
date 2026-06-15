<?php
// Feature: stashdock, Property 3: PAT encryption round-trip
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

test('PAT encryption round-trip', function () {
    $service = app(SettingsService::class);

    for ($i = 0; $i < 30; $i++) {
        $originalPat = bin2hex(random_bytes(20)); // 40-char hex PAT

        $service->saveSettings([
            'github_nickname' => 'testuser',
            'github_email'    => 'test@example.com',
            'github_token'    => $originalPat,
        ]);

        $stored = \App\Models\SystemSettings::first();

        // Stored value must differ from plaintext
        expect($stored->getRawOriginal('github_token'))->not->toBe($originalPat);

        // Decryption must return original
        $decrypted = Crypt::decryptString($stored->getRawOriginal('github_token'));
        expect($decrypted)->toBe($originalPat);
    }
});
