<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TravelRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas de Autenticação
|--------------------------------------------------------------------------
| Estas rotas não exigem autenticação JWT.
*/
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Rotas que Exigem Autenticação JWT
|--------------------------------------------------------------------------
| Agrupadas sob o middleware 'jwt.verify'.
*/
Route::middleware('jwt.verify')->group(function () {

    // Rotas de autenticação que precisam de token válido
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('me', [AuthController::class, 'me']);

    // Rotas para Pedidos de Viagem (Travel Requests)
    Route::get('travel-requests', [TravelRequestController::class, 'index'])->name('travel-requests.index'); 
    Route::post('travel-requests', [TravelRequestController::class, 'store'])->name('travel-requests.store'); 
    Route::get('travel-requests/{id}', [TravelRequestController::class, 'show'])->name('travel-requests.show'); 
    Route::patch('travel-requests/{id}/status', [TravelRequestController::class, 'updateStatus'])->name('travel-requests.updateStatus');

});