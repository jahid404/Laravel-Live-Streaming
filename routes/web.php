<?php

use App\Http\Controllers\StreamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/stream', [StreamController::class, 'createStream'])->name('stream.create');
Route::get('/stream/v2', [StreamController::class, 'createScreenShareStream'])->name('stream.screenshare.create');
Route::get('/stream/{stream_id}', [StreamController::class, 'watchStream'])->name('stream.watch');