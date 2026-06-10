<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\MatchmakingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');

Route::middleware(['auth:sanctum', 'not_banned'])->group(function () {
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
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::get('/ranks', fn () => \App\Models\Rank::orderBy('min_elo')->get());

    // Admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::patch('/users/{user}/elo', [AdminController::class, 'setElo']);
        Route::patch('/users/{user}/ban', [AdminController::class, 'toggleBan']);
        Route::patch('/users/{user}/admin', [AdminController::class, 'toggleAdmin']);

        Route::get('/games', [AdminController::class, 'games']);
        Route::get('/games/{game}', [AdminController::class, 'game']);
        Route::post('/games/{game}/end', [AdminController::class, 'endGame']);

        Route::get('/queue', [AdminController::class, 'queue']);
        Route::delete('/queue/{user}', [AdminController::class, 'removeFromQueue']);
        Route::delete('/queue', [AdminController::class, 'clearQueue']);

        Route::post('/ranks', [AdminController::class, 'storeRank']);
        Route::patch('/ranks/{rank}', [AdminController::class, 'updateRank']);
        Route::delete('/ranks/{rank}', [AdminController::class, 'destroyRank']);
    });
});
