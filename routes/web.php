<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('user.home');
})->name('home');

Route::get('/login', function () {
    return view('user.auth.login');
})->name('login');

Route::get('/register', function () {
    return view('user.auth.register');
})->name('register');

Route::get('/movies', function () {
    return view('user.movies.index');
})->name('user.movies.index');

Route::get('/movies/{id}', function ($id) {
    return view('user.movies.show');
})->name('user.movies.show');

Route::get('/booking/select-seat', function () {
    return view('user.bookings.select-seat');
})->name('user.bookings.selectSeat');

Route::get('/booking/checkout', function () {
    return view('user.bookings.checkout');
})->name('user.bookings.checkout');

Route::get('/booking/success', function () {
    return view('user.bookings.success');
})->name('user.bookings.success');

Route::get('/my-ticket', function () {
    return view('user.bookings.ticket');
})->name('user.bookings.ticket');

Route::get('/booking-history', function () {
    return view('user.bookings.history');
})->name('user.bookings.history');

Route::get('/ai/recommend', function () {
    return view('user.ai.recommend');
})->name('user.ai.recommend');

Route::get('/ai/chatbot', function () {
    return view('user.ai.chatbot');
})->name('user.ai.chatbot');

Route::get('/profile', function () {
    return view('user.profile.index');
})->name('user.profile');
