<?php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('hard-reset without confirm token returns 422', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/projects/some-project/git', [
        'action'    => 'hard-reset',
        'commit_id' => 'HEAD',
        // confirm intentionally missing
    ]);
    expect($response->status())->toBe(422);
    expect($response->json('status'))->toBe('error');
});

test('clean without confirm token returns 422', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/projects/some-project/git', [
        'action' => 'clean',
        // confirm intentionally missing
    ]);
    expect($response->status())->toBe(422);
    expect($response->json('status'))->toBe('error');
});
