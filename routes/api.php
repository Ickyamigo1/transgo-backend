<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\DriverAttendanceController;
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

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
});

// Route::middleware(['auth:sanctum'])->group(function () {
Route::prefix('schedules')->group(function () {
    Route::get('/', [ScheduleController::class, 'index']);
    Route::get('/reserve', [ScheduleController::class, 'historyReserve']);
    Route::get('/conductor-reserve', [ScheduleController::class, 'conductorReserveTicket']);
    Route::post('/reserve', [ScheduleController::class, 'reserveTicket']);
    Route::get('/reservation/{id}/date-departure', [ScheduleController::class, 'getDateDeparture']);
    Route::post('/update-reserve', [ScheduleController::class, 'updateReserveTicket']);
});

Route::put('/Driver/status/{id}', [DriverAttendanceController::class, 'updateStatus']);
Route::get('/Driver', [DriverAttendanceController::class, 'DataDriver']);
Route::get('/Bus', [DriverAttendanceController::class, 'DataBus']);
