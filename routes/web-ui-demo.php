<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MovieMate UI Demo Routes
|--------------------------------------------------------------------------
| ÄÃ¢y lÃ  route test giao diá»‡n tÄ©nh.
| Sau nÃ y khi lÃ m Controller tháº­t, báº¡n cÃ³ thá»ƒ thay cÃ¡c route nÃ y.
*/

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

Route::prefix('staff')->name('staff.')->group(function () {
    Route::get('/login', function () {
        return view('staff.auth.login');
    })->name('login');

    Route::get('/dashboard', function () {
        return view('staff.dashboard');
    })->name('dashboard');

    Route::get('/tickets/check', function () {
        return view('staff.tickets.check');
    })->name('tickets.check');

    Route::get('/tickets/valid', function () {
        return view('staff.tickets.valid');
    })->name('tickets.valid');

    Route::get('/tickets/used', function () {
        return view('staff.tickets.used');
    })->name('tickets.used');

    Route::get('/tickets/not-found', function () {
        return view('staff.tickets.not-found');
    })->name('tickets.notFound');

    Route::get('/tickets', function () {
        return view('staff.tickets.index');
    })->name('tickets.index');

    Route::get('/counter-sale', function () {
        return view('staff.sales.counter');
    })->name('sales.counter');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', function () {
        return view('admin.auth.login');
    })->name('login');

    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    Route::get('/movies', function () {
        return view('admin.movies.index');
    })->name('movies.index');

    Route::get('/movies/create', function () {
        return view('admin.movies.create');
    })->name('movies.create');

    Route::get('/movies/{id}/edit', function ($id) {
        return view('admin.movies.edit');
    })->name('movies.edit');

    Route::get('/movies/{id}', function ($id) {
        return view('admin.movies.show');
    })->name('movies.show');

    Route::get('/genres', function () {
        return view('admin.genres.index');
    })->name('genres.index');

    Route::get('/genres/create', function () {
        return view('admin.genres.create');
    })->name('genres.create');

    Route::get('/genres/{id}/edit', function ($id) {
        return view('admin.genres.edit');
    })->name('genres.edit');

    Route::get('/cinemas', function () {
        return view('admin.cinemas.index');
    })->name('cinemas.index');

    Route::get('/cinemas/create', function () {
        return view('admin.cinemas.create');
    })->name('cinemas.create');

    Route::get('/cinemas/{id}/edit', function ($id) {
        return view('admin.cinemas.edit');
    })->name('cinemas.edit');

    Route::get('/rooms', function () {
        return view('admin.rooms.index');
    })->name('rooms.index');

    Route::get('/rooms/create', function () {
        return view('admin.rooms.create');
    })->name('rooms.create');

    Route::get('/rooms/{id}/edit', function ($id) {
        return view('admin.rooms.edit');
    })->name('rooms.edit');

    Route::get('/seats', function () {
        return view('admin.seats.index');
    })->name('seats.index');

    Route::get('/seats/manage', function () {
        return view('admin.seats.manage');
    })->name('seats.manage');

    Route::get('/showtimes', function () {
        return view('admin.showtimes.index');
    })->name('showtimes.index');

    Route::get('/showtimes/create', function () {
        return view('admin.showtimes.create');
    })->name('showtimes.create');

    Route::get('/showtimes/{id}/edit', function ($id) {
        return view('admin.showtimes.edit');
    })->name('showtimes.edit');

    Route::get('/bookings', function () {
        return view('admin.bookings.index');
    })->name('bookings.index');

    Route::get('/bookings/{id}', function ($id) {
        return view('admin.bookings.show');
    })->name('bookings.show');

    Route::get('/users', function () {
        return view('admin.users.index');
    })->name('users.index');

    Route::get('/users/{id}', function ($id) {
        return view('admin.users.show');
    })->name('users.show');

    Route::get('/reviews', function () {
        return view('admin.reviews.index');
    })->name('reviews.index');

    Route::get('/analytics/revenue', function () {
        return view('admin.analytics.revenue');
    })->name('analytics.revenue');

    Route::get('/analytics/top-movies', function () {
        return view('admin.analytics.top-movies');
    })->name('analytics.topMovies');

    Route::get('/ai/movie-content', function () {
        return view('admin.ai.movie-content');
    })->name('ai.movieContent');
});
