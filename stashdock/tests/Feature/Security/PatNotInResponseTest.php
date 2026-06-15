<?php
// Feature: stashdock, Property 5: PAT never appears in HTTP responses
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('PAT never appears in HTTP responses', function () {
    $knownPat = 'SUPERSECRET_PAT_VALUE_' . uniqid();
    $user     = User::factory()->create();
    $service  = app(SettingsService::class);

    $service->saveSettings([
        'github_nickname' => 'testuser',
        'github_email'    => 'test@example.com',
        'github_token'    => $knownPat,
    ]);

    $protectedRoutes = [
        ['GET', '/dashboard'],
        ['GET', '/projects'],
        ['GET', '/settings'],
    ];

    foreach ($protectedRoutes as [$method, $uri]) {
        $response = $this->actingAs($user)->call($method, $uri);
        expect($response->getContent())->not->toContain($knownPat);
    }
});
