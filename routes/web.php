<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/activity');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/activity', [GameController::class, 'index'])->name('game.index');
    Route::post('/activity/checkin', [GameController::class, 'checkin'])->name('game.checkin');
    Route::post('/activity/move', [GameController::class, 'move'])->name('game.move');
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/admin/recharge', [AdminController::class, 'recharge'])->name('admin.recharge');
    Route::post('/admin/winnings/{winning}/issue', [AdminController::class, 'issue'])->name('admin.winnings.issue');
});
