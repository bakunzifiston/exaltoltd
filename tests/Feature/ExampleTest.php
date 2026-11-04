<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_loads_storefront_homepage(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Exalto Green Ltd');
    }
}
