<?php

use App\Http\Controllers\Auth\StoreRegistrationController;
use App\Http\Controllers\Store\SwitchLocationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Store Registration Routes
Route::get('/register', [StoreRegistrationController::class, 'showRegistrationForm'])
    ->middleware('guest')
    ->name('store.register');

Route::post('/register', [StoreRegistrationController::class, 'register'])
    ->middleware('guest')
    ->name('store.register');

// Store location switching (store panel)
Route::post('/store/switch-location', SwitchLocationController::class)
    ->middleware(['auth', \App\Http\Middleware\SetCurrentStore::class])
    ->name('store.switch-location');
