<?php

use Illuminate\Support\Facades\Route;
use App\Events\TestPusherEvent;

Route::get('/test-pusher', function () {
    event(new \App\Events\TestPusherEvent());
    return 'Event fired';
});

Route::view('/pusher-test', 'pusher-test');

Route::get('/', function () {
    return view('welcome');
});
