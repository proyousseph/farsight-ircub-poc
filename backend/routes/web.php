<?php

use App\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

if (config('ircub.docs_enabled', false)) {
    Route::get('/docs/api', [ApiDocsController::class, 'ui'])->name('docs.api');
    Route::get('/docs/openapi.yaml', [ApiDocsController::class, 'openapi'])->name('docs.openapi');
}
