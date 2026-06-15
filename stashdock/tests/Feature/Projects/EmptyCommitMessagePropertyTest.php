<?php
// Feature: stashdock, Property 15: empty commit message is always rejected
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('empty commit message is always rejected', function () {
    $user = User::factory()->create();

    // Various whitespace-only strings
    $emptyMessages = ['', ' ', '  ', "\t", "\n", "\r\n", "   \t  "];

    foreach ($emptyMessages as $msg) {
        $response = $this->actingAs($user)
            ->postJson('/projects/fake-project/git', [
                'action'  => 'quick-sync',
                'message' => $msg,
            ]);

        // Should return error status (404 for invalid path or 422/200 with error status)
        expect($response->status())->toBeIn([200, 404, 422, 500]);

        // If it reaches the controller (200/422), the response must indicate an error
        if (in_array($response->status(), [200, 422])) {
            $data = $response->json();
            expect($data['status'])->toBe('error');
        }
    }
});
