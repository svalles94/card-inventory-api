<?php

use App\Http\Controllers\Auth\StoreRegistrationController;
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
