<?php

use Illuminate\Support\Facades\Route;

Route::get('/geo/{name}', function (string $name) {
    abort_unless(preg_match('/^[a-zA-Z0-9._-]+$/', $name), 404);
    $path = public_path('geo/'.$name);
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Content-Type' => 'application/json']);
});

Route::view('/{any?}', 'app')->where('any', '.*');
