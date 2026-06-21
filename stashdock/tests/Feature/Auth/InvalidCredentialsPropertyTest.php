<?php
// Feature: stashdock, Property 1: invalid credentials always produce an error
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

test('invalid credentials always produce an error', function () {
    // Create one real user
    $realUser = User::factory()->create([
        'username' => 'realuser',
        'password' => bcrypt('correct-password'),
    ]);

    // Clear any existing rate limiting
    RateLimiter::clear('realuser|127.0.0.1');

    // Run 20 iterations with random wrong credentials
    for ($i = 0; $i < 20; $i++) {
        $fakeUsername = 'fake_' . $i . '_' . uniqid();
        $fakePassword = 'wrong-password-' . $i;

        // Clear rate limiter for this username to avoid throttling
        RateLimiter::clear(strtolower($fakeUsername) . '|127.0.0.1');

        $response = $this->post('/login', [
            'username' => $fakeUsername,
            'password' => $fakePassword,
        ]);

        // Must NOT redirect to dashboard — must redirect back to login
        expect($response->status())->toBeIn([302, 422]);
        if ($response->status() === 302) {
            expect($response->headers->get('Location'))->not->toContain('/dashboard');
        }
    }

    // Also test with the real username but wrong password
    RateLimiter::clear('realuser|127.0.0.1');
    $response = $this->post('/login', [
        'username' => 'realuser',
        'password' => 'wrong-password',
    ]);
    expect($response->status())->toBeIn([302, 422]);
    if ($response->status() === 302) {
        expect($response->headers->get('Location'))->not->toContain('/dashboard');
    }
});
