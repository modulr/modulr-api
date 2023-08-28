<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutopartController;
use App\Http\Controllers\AutopartImageController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\SideController;
use App\Http\Controllers\ConditionController;
use App\Http\Controllers\OriginController;
use App\Http\Controllers\MakeController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\YearController;
use App\Http\Controllers\MlController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) { return $request->user(); });
    Route::post('autoparts/searchByUser', [AutopartController::class, 'searchByUser']);

    Route::post('autoparts/store', [AutopartController::class, 'store']);
    Route::put('autoparts/update/{id}', [AutopartController::class, 'update']);
    Route::delete('autoparts/destroy/{id}', [AutopartController::class, 'destroy']);

    Route::post('autoparts/images/uploadTemp', [AutopartImageController::class, 'uploadTemp']);
    Route::post('autoparts/images/upload/{id}', [AutopartImageController::class, 'upload']);
    Route::delete('autoparts/images/destroy/{id}', [AutopartImageController::class, 'destroy']);
});

Route::post('autoparts/search', [AutopartController::class, 'search']);
Route::get('autoparts/{id}', [AutopartController::class, 'show']);

Route::get('categories', [CategoryController::class, 'index']);
Route::get('positions', [PositionController::class, 'index']);
Route::get('sides', [SideController::class, 'index']);
Route::get('conditions', [ConditionController::class, 'index']);
Route::get('origins', [OriginController::class, 'index']);
Route::get('makes', [MakeController::class, 'index']);
Route::get('models', [ModelController::class, 'index']);
Route::get('years', [YearController::class, 'index']);


// Mercado libre
Route::get('/ml/auth', [MlController::class, 'auth']);
Route::post('/ml/notifications', [MlController::class, 'notifications']);
