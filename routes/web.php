<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportMlController;
use App\Http\Controllers\ExportController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
    //return ['Laravel' => app()->version()];
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('import/getIds/{id}', [ImportMlController::class, 'getIds']);
    Route::get('import/getNewIds/{id}', [ImportMlController::class, 'getNewIds']);
    Route::get('import/import/{id}/{limit}', [ImportMlController::class, 'import']);
    Route::get('import/save/{id}/{limit}', [ImportMlController::class, 'save']);

    Route::get('export', [ExportController::class, 'export']);
});

require __DIR__.'/auth.php';
