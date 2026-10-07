<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_swagger_ui_is_available(): void
    {
        $this->get('/docs/api')
            ->assertOk()
            ->assertSee('swagger-ui', false);
    }

    public function test_openapi_yaml_is_served(): void
    {
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertSee('openapi:', false)
            ->assertSee('IRCUB API', false);
    }
}
