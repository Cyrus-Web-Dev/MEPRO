<?php

use Illuminate\Support\Facades\Route;

// SPA Vue: semua URL non-API diarahkan ke satu halaman
Route::view('/{any?}', 'app')->where('any', '^(?!api|up|broadcasting|storage).*$');
