<?php
// Feature: stashdock, Property 2: protected routes redirect unauthenticated requests
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('protected routes redirect unauthenticated requests', function () {
    $protectedRoutes = [
        ['GET',  '/dashboard'],
        ['GET',  '/projects'],
        ['GET',  '/settings'],
        ['POST', '/settings'],
        ['POST', '/clone'],
        ['POST', '/dashboard/sync-logs'],
    ];

    foreach ($protectedRoutes as [$method, $uri]) {
        $response = $this->call($method, $uri);
        expect($response->status())->toBeIn([301, 302])
            ->and($response->headers->get('Location'))->toContain('/login');
    }
});
