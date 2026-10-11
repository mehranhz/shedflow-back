<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;
Route::get('/', function () {
    return view('welcome');
});
