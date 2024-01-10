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
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoresMlController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\LocationsController;
use App\Http\Controllers\AutopartCommentController;
use App\Http\Controllers\BulbPositionController;
use App\Http\Controllers\BulbTechController;
use App\Http\Controllers\ShippingTypeController;
use App\Http\Controllers\TeikerController;

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
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $request->user()->roles;
        return $user;
    });
    Route::post('autoparts/searchInventory', [AutopartController::class, 'searchInventory']);
    Route::post('autoparts/searchSales', [AutopartController::class, 'searchSales']);
    Route::get('autoparts/showByUser/{id}', [AutopartController::class, 'showByUser']);
    
    Route::post('autoparts/store', [AutopartController::class, 'store']);
    Route::put('autoparts/update/{id}', [AutopartController::class, 'update']);
    Route::delete('autoparts/destroy/{id}', [AutopartController::class, 'destroy']);
    Route::post('autoparts/clone', [AutopartController::class, 'clone']);
    Route::put('autoparts/updateStatus/{id}', [AutopartController::class, 'updateStatus']);
    
    Route::get('autoparts/description', [AutopartController::class, 'getDescription']);
    
    Route::post('autoparts/images/uploadTemp', [AutopartImageController::class, 'uploadTemp']);
    Route::post('autoparts/images/upload/{id}', [AutopartImageController::class, 'upload']);
    Route::post('autoparts/images/sort',  [AutopartImageController::class, 'sort']);
    Route::post('autoparts/images/destroyTemp', [AutopartImageController::class, 'destroyTemp']);
    Route::delete('autoparts/images/destroy/{id}', [AutopartImageController::class, 'destroy']);
   
    Route::post('/autoparts/comments/store', [AutopartCommentController::class, 'store']);
    Route::delete('/autoparts/comments/destroy/{id}', [AutopartCommentController::class, 'destroy']);

    Route::get('/autoparts/qr/{id}', [AutopartController::class, 'qr']);
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
Route::get('stores', [StoreController::class, 'index']);
Route::get('statuses', [StatusController::class, 'index']);
Route::get('locations', [LocationsController::class, 'index']);
Route::get('bulb_positions', [BulbPositionController::class, 'index']);
Route::get('bulb_technologies', [BulbTechController::class, 'index']);
Route::get('shipping_types', [ShippingTypeController::class, 'index']);

//Stores ML
Route::get('stores_ml', [StoresMlController::class, 'index']);
Route::post('stores_ml/store', [StoresMlController::class, 'store']);
Route::put('stores_ml/update/{id}', [StoresMlController::class, 'update']);
Route::delete('stores_ml/destroy/{id}', [StoresMlController::class, 'destroy']);

//Locations
Route::get('locations', [LocationsController::class, 'index']);
Route::get('locations/{id}', [LocationsController::class, 'show']);
Route::post('locations/store', [LocationsController::class, 'store']);
Route::put('locations/update/{id}', [LocationsController::class, 'update']);
Route::delete('locations/destroy/{id}', [LocationsController::class, 'destroy']);
Route::get('locations/{store_id}/{id}', [LocationsController::class, 'qr']);

// Mercado libre
Route::get('/ml/auth', [MlController::class, 'auth']);
Route::post('/ml/notifications', [MlController::class, 'notifications']);

// Teiker
Route::post('/teiker/quotation', [TeikerController::class, 'quotation']);
