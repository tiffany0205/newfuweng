<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExperienceController;
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
    Route::get('/activity/center', [ExperienceController::class, 'center'])->name('experience.center');
    Route::post('/activity/tasks/{task}/claim', [ExperienceController::class, 'claimTask'])->name('experience.tasks.claim');
    Route::post('/activity/milestones/{milestone}/claim', [ExperienceController::class, 'claimMilestone'])->name('experience.milestones.claim');
    Route::post('/activity/items/{item}/use', [ExperienceController::class, 'useItem'])->name('experience.items.use');
    Route::post('/activity/skins/{skin}/equip', [ExperienceController::class, 'equipSkin'])->name('experience.skins.equip');
    Route::post('/activity/winnings/{winning}/claim', [ExperienceController::class, 'submitClaim'])->name('experience.winnings.claim');
    Route::post('/activity/messages/read', [ExperienceController::class, 'readMessages'])->name('experience.messages.read');
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/admin/recharge', [AdminController::class, 'recharge'])->name('admin.recharge');
    Route::post('/admin/winnings/{winning}/issue', [AdminController::class, 'issue'])->name('admin.winnings.issue');
    Route::post('/admin/activity', [AdminController::class, 'updateActivity'])->name('admin.activity.update');
    Route::post('/admin/chances', [AdminController::class, 'adjustChance'])->name('admin.chances.adjust');
    Route::post('/admin/claims/{claim}', [AdminController::class, 'updateClaim'])->name('admin.claims.update');
    Route::post('/admin/tasks/{task}/toggle', [AdminController::class, 'toggleTask'])->name('admin.tasks.toggle');
});
