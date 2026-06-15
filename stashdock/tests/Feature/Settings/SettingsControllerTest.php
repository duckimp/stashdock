<?php
use App\Models\User;
use App\Models\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('settings page renders with pre-filled nickname and email but empty PAT field', function () {
    $user = User::factory()->create();
    SystemSettings::updateOrCreate(['id' => 1], [
        'github_nickname' => 'myuser',
        'github_email'    => 'my@email.com',
        'github_token'    => encrypt('secret-pat'),
    ]);

    $response = $this->actingAs($user)->get('/settings');
    $response->assertStatus(200);
    $response->assertSee('myuser');
    $response->assertSee('my@email.com');
    // PAT must NOT appear in the page
    $response->assertDontSee('secret-pat');
});

test('settings validation requires nickname and email', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->post('/settings', [
        'github_nickname' => '',
        'github_email'    => 'not-an-email',
    ]);
    $response->assertSessionHasErrors(['github_nickname', 'github_email']);
});

test('empty PAT on submit preserves existing encrypted token', function () {
    $user = User::factory()->create();
    $originalEncrypted = encrypt('original-secret-pat');
    SystemSettings::updateOrCreate(['id' => 1], [
        'github_nickname' => 'user',
        'github_email'    => 'user@example.com',
        'github_token'    => $originalEncrypted,
    ]);

    $this->actingAs($user)->post('/settings', [
        'github_nickname' => 'newuser',
        'github_email'    => 'new@example.com',
        'github_token'    => '', // empty
    ]);

    $updated = SystemSettings::first();
    expect($updated->getRawOriginal('github_token'))->toBe($originalEncrypted);
});
