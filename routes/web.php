<?php

use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

// Google OAuth runs as classic redirects, outside the SPA. The pending/
// complete endpoints live here too (not in api.php) because they read the
// pending Google identity from the web session.
Route::middleware('guest')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
    Route::get('/auth/google/pending', [GoogleAuthController::class, 'pending']);
    Route::post('/auth/google/complete', [GoogleAuthController::class, 'complete']);
});

// The Vue SPA owns every route; Laravel only serves the shell.
Route::view('/{any?}', 'app')->where('any', '^(?!api|broadcasting|auth/google).*$');
