<?php

use Illuminate\Support\Facades\Route;

/*
| L'application (écrans) est une page unique : public/app.html.
| Elle fonctionne aussi hors ligne grâce au service worker (public/sw.js).
*/
Route::get('/', function () {
    return response()->file(public_path('app.html'), [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-cache',
    ]);
});
