<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // GET / redirects to the dashboard (login required), so expect 302
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
