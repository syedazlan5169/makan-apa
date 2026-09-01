<?php

use App\Http\Controllers\MakanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MakanController::class, 'index'])
    ->name('makan.index');

Route::post('/pick', [MakanController::class, 'pick'])
    ->name('makan.pick');

Route::post('/accept', [MakanController::class, 'accept'])
    ->name('makan.accept');