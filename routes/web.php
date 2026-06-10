<?php

use Illuminate\Support\Facades\Route;

// The Vue SPA owns every route; Laravel only serves the shell.
Route::view('/{any?}', 'app')->where('any', '^(?!api|broadcasting).*$');
