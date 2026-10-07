<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class ApiDocsController extends Controller
{
    public function ui()
    {
        return response()->view('swagger');
    }

    public function openapi(): Response
    {
        $path = dirname(base_path()).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'openapi.yaml';

        if (! is_file($path)) {
            $path = base_path('docs/openapi.yaml');
        }

        if (! is_file($path)) {
            return response("OpenAPI spec not found at {$path}", 404)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }
}
