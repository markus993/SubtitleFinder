<?php

use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VideoController::class, 'index'])->name('videos.index');
Route::post('/scan', [VideoController::class, 'scan'])->name('videos.scan');
Route::post('/videos/{video}/process', [VideoController::class, 'process'])->name('videos.process');
Route::get('/videos', [VideoController::class, 'getVideos'])->name('videos.list');
