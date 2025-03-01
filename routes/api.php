<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\SubscriptionController;
use App\Http\Controllers\API\ContentController;
use App\Http\Controllers\API\TrendController;
use App\Http\Controllers\API\ProfileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User profile
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [ProfileController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Subscription management
    Route::prefix('subscription')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::post('/create', [SubscriptionController::class, 'create']);
        Route::put('/update', [SubscriptionController::class, 'update']);
        Route::delete('/cancel', [SubscriptionController::class, 'cancel']);
        Route::get('/invoices', [SubscriptionController::class, 'invoices']);
    });

    // Content generation
    Route::prefix('content')->group(function () {
        Route::post('/generate', [ContentController::class, 'generate']);
        Route::get('/', [ContentController::class, 'index']);
        Route::get('/{id}', [ContentController::class, 'show']);
        Route::put('/{id}', [ContentController::class, 'update']);
        Route::delete('/{id}', [ContentController::class, 'delete']);
    });

    // Trending topics
    Route::prefix('trends')->group(function () {
        Route::get('/', [TrendController::class, 'index']);
        Route::get('/{niche}', [TrendController::class, 'byNiche']);
    });
});
