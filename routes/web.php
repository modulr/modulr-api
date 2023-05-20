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
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('import/getIds/{id}', [ImportMlController::class, 'getIds']);
Route::get('import/getNewIds/{id}', [ImportMlController::class, 'getNewIds']);
Route::get('import/import/{id}', [ImportMlController::class, 'import']);
Route::get('import/save/{id}/{limit}', [ImportMlController::class, 'save']);

Route::get('export', [ExportController::class, 'export']);

