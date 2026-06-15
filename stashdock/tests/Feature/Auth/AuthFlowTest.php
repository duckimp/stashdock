<?php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login with valid credentials redirects to dashboard', function () {
    $user = User::factory()->create([
        'email'    => 'admin@local.test',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post('/login', [
        'email'    => 'admin@local.test',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('logout invalidates session and redirects to login', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/logout')
         ->assertRedirect('/');
    $this->assertGuest();
});
