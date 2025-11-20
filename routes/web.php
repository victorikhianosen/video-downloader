<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\YouTubeController;

Route::get('/', [YouTubeController::class, 'index'])->name('youtube.index');
Route::post('/youtube/formats', [YouTubeController::class, 'formats'])->name('youtube.formats');
Route::post('/youtube/download', [YouTubeController::class, 'download'])->name('youtube.download');
