<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

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

Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{uuid}', [UserController::class, 'show']);
Route::get('/users', [UserController::class, 'index']);
Route::put('/users/{uuid}', [UserController::class, 'update']);
Route::delete('/users/{uuid}', [UserController::class, 'destroy']);