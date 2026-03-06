<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\MouvementStockController;
use App\Http\Controllers\VenteController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DashboardController;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::apiResource('categories', CategorieController::class);
    Route::apiResource('articles', ArticleController::class);
    Route::apiResource('mouvement-stocks', MouvementStockController::class);
    Route::apiResource('ventes', VenteController::class);
    Route::apiResource('credits', CreditController::class);
    Route::post('credits/{credit}/payer', [CreditController::class, 'payer']);
});
