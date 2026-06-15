<?php
// Feature: stashdock, Property 1: invalid credentials always produce an error
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

test('invalid credentials always produce an error', function () {
    // Create one real user
    $realUser = User::factory()->create([
        'email'    => 'real@example.com',
        'password' => bcrypt('correct-password'),
    ]);

    // Clear any existing rate limiting
    RateLimiter::clear('real@example.com|127.0.0.1');

    // Run 20 iterations with random wrong credentials
    for ($i = 0; $i < 20; $i++) {
        $fakeEmail    = 'fake_' . $i . '_' . uniqid() . '@random.test';
        $fakePassword = 'wrong-password-' . $i;

        // Clear rate limiter for this email to avoid throttling
        RateLimiter::clear(strtolower($fakeEmail) . '|127.0.0.1');

        $response = $this->post('/login', [
            'email'    => $fakeEmail,
            'password' => $fakePassword,
        ]);

        // Must NOT redirect to dashboard — must redirect back to login
        expect($response->status())->toBeIn([302, 422]);
        if ($response->status() === 302) {
            expect($response->headers->get('Location'))->not->toContain('/dashboard');
        }
    }

    // Also test with the real email but wrong password
    RateLimiter::clear('real@example.com|127.0.0.1');
    $response = $this->post('/login', [
        'email'    => 'real@example.com',
        'password' => 'wrong-password',
    ]);
    expect($response->status())->toBeIn([302, 422]);
    if ($response->status() === 302) {
        expect($response->headers->get('Location'))->not->toContain('/dashboard');
    }
});
