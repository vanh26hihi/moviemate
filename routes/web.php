<?php

use App\Http\Controllers\User\HomeController;
use App\Http\Controllers\Admin\CinemaController as AdminCinemaController;
use App\Http\Controllers\Admin\GenreController as AdminGenreController;
use App\Http\Controllers\Admin\MovieController as AdminMovieController;
use App\Http\Controllers\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Admin\SeatController as AdminSeatController;
use App\Http\Controllers\Admin\ShowtimeController as AdminShowtimeController;
use App\Http\Controllers\User\BookingController;
use App\Http\Controllers\User\MovieController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/login', function () {
    return view('user.auth.login');
})->name('login');

Route::get('/register', function () {
    return view('user.auth.register');
})->name('register');

Route::get('/movies', [MovieController::class, 'index'])->name('user.movies.index');

Route::get('/movies/{slug}', [MovieController::class, 'show'])->name('user.movies.show');

Route::get('/booking/select-seat/{showtime}', [BookingController::class, 'selectSeat'])
    ->name('user.bookings.selectSeat');

Route::get('/booking/checkout/{showtime}', [BookingController::class, 'checkout'])
    ->name('user.bookings.checkout');

Route::post('/booking/store', [BookingController::class, 'store'])
    ->name('user.bookings.store');

Route::get('/booking/success/{booking}', [BookingController::class, 'success'])
    ->name('user.bookings.success');

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

Route::get('/api/cinemas/{cinema}/rooms', function (App\Models\Cinema $cinema) {
    return $cinema->rooms()
        ->select('id', 'name', 'room_type')
        ->orderBy('name')
        ->get();
})->name('api.cinemas.rooms');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::resource('movies', AdminMovieController::class);
    Route::resource('genres', AdminGenreController::class)->except(['show']);
    Route::resource('cinemas', AdminCinemaController::class)->except(['show']);
    Route::resource('rooms', AdminRoomController::class)->except(['show']);

    Route::get('/seats', [AdminSeatController::class, 'index'])->name('seats.index');
    Route::get('/seats/manage/{room}', [AdminSeatController::class, 'manage'])->name('seats.manage');
    Route::post('/seats/generate/{room}', [AdminSeatController::class, 'generate'])->name('seats.generate');
    Route::patch('/seats/{seat}', [AdminSeatController::class, 'update'])->name('seats.update');

    Route::resource('showtimes', AdminShowtimeController::class)->except(['show']);
});
