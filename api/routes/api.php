<?php

use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

Route::get('/bookmarks', [BookmarkController::class, 'index']);

Route::get('/tags', [TagController::class, 'index']);
