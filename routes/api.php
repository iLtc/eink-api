<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/status', function (Request $request) {
    return response()->json(['status' => 'success']);
});

use App\Http\Controllers\ApiController;

Route::get('/weather', [ApiController::class, 'weather']);
Route::get('/habitica', [ApiController::class, 'habitica']);
Route::get('/omnifocus', [ApiController::class, 'omnifocus']);
Route::get('/calendar', [ApiController::class, 'calendar']);
Route::get('/all_in_one', [ApiController::class, 'all_in_one']);
