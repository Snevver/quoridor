<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\MatchmakingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Matchmaking
    Route::post('/matchmaking/join', [MatchmakingController::class, 'join']);
    Route::post('/matchmaking/leave', [MatchmakingController::class, 'leave']);
    Route::get('/matchmaking/status', [MatchmakingController::class, 'status']);

    // Game
    Route::get('/games/{game}', [GameController::class, 'show']);
    Route::post('/games/{game}/move', [GameController::class, 'move']);
    Route::post('/games/{game}/resign', [GameController::class, 'resign']);
    Route::get('/games/{game}/legal-moves', [GameController::class, 'legalMoves']);

    // User
    Route::get('/user/me', fn () => auth()->user());
    Route::get('/leaderboard', [UserController::class, 'leaderboard']);
});
