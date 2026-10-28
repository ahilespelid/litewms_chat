<?php

use App\Http\Controllers\PageController;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/app', [PageController::class, 'app'])->name('chat.app');
Route::get('/embed', [PageController::class, 'embed'])->name('chat.embed');
Route::get('/l/{secret}', [PageController::class, 'link'])->where('secret', '[A-Za-z0-9]{20,100}')->name('chat.link');

Route::get('/m/{token}', [PageController::class, 'shared'])->where('token', '[a-z0-9]{20,64}')->name('chat.shared');

Route::get('/docs', [PageController::class, 'docs'])->name('docs');
Scramble::registerJsonSpecificationRoute('docs/openapi.json')->name('docs.openapi');
