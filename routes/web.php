<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\CinemaController as AdminCinemaController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GenreController as AdminGenreController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\AiContentController as AdminAiContentController;
use App\Http\Controllers\Admin\MovieController as AdminMovieController;
use App\Http\Controllers\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Admin\SeatController as AdminSeatController;
use App\Http\Controllers\Admin\ShowtimeController as AdminShowtimeController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\TicketCheckController as StaffTicketCheckController;
use App\Http\Controllers\Payment\PayosController;
use App\Http\Controllers\User\HomeController;
use App\Http\Controllers\User\MovieController;
use App\Http\Controllers\User\BookingController;
use App\Http\Controllers\User\AiController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\ShowtimeController as UserShowtimeController;
use App\Http\Controllers\Admin\FoodController as AdminFoodController;
use App\Http\Controllers\Admin\FoodOrderController as AdminFoodOrderController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/ajax/showtimes', [HomeController::class, 'ajaxShowtimes'])->name('ajax.showtimes');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::get('/register', [RegisterController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->name('register.post');

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::post('/payment/payos/webhook', [PayosController::class, 'webhook'])->name('payment.payos.webhook');

Route::get('/movies', [MovieController::class, 'index'])->name('user.movies.index');

Route::get('/movies/{slug}', [MovieController::class, 'show'])->name('user.movies.show');

Route::get('/showtimes', [UserShowtimeController::class, 'index'])->name('user.showtimes.index');
Route::get('/showtimes/ajax', [UserShowtimeController::class, 'ajax'])->name('user.showtimes.ajax');

Route::middleware('user')->group(function () {
    Route::get('/payment/payos/return/{booking}', [PayosController::class, 'return'])->name('payment.payos.return');
    Route::get('/payment/payos/cancel/{booking}', [PayosController::class, 'cancel'])->name('payment.payos.cancel');

    Route::get('/booking/select-seat/{showtime}', [BookingController::class, 'selectSeat'])
        ->name('user.bookings.selectSeat');

    Route::get('/booking/checkout/{showtime}', [BookingController::class, 'checkout'])
        ->name('user.bookings.checkout');

    Route::post('/booking/store', [BookingController::class, 'store'])
        ->name('user.bookings.store');

    Route::get('/booking/success/{booking}', [BookingController::class, 'success'])
        ->name('user.bookings.success');

    Route::get('/my-ticket/{booking}', [BookingController::class, 'ticket'])
        ->name('user.bookings.ticket');

    Route::get('/booking-history', [BookingController::class, 'history'])
        ->name('user.bookings.history');

    Route::delete('/booking/{booking}/cancel', [BookingController::class, 'cancel'])
        ->name('user.bookings.cancel');
});

Route::get('/ai/recommend', [AiController::class, 'recommend'])->name('user.ai.recommend');
Route::post('/ai/recommend', [AiController::class, 'recommendStore'])->name('user.ai.recommend.submit');

Route::get('/ai/chatbot', [AiController::class, 'chatbot'])->name('user.ai.chatbot');
Route::post('/ai/chatbot', [AiController::class, 'chatbotStore'])->name('user.ai.chatbot.submit');

Route::middleware('user')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('user.profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('user.profile.update');
    Route::get('/loyalty-history', [ProfileController::class, 'loyaltyHistory'])->name('user.loyalty.history');
});

Route::get('/admin/login', function () {
    return view('admin.auth.login');
})->name('admin.login');

Route::get('/staff/login', function () {
    return view('staff.auth.login');
})->name('staff.login');

// Staff Routes
Route::prefix('staff')->name('staff.')->middleware('staff')->group(function () {
    Route::get('/dashboard', [StaffDashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/tickets/check', [StaffTicketCheckController::class, 'show'])
        ->name('tickets.check');

    Route::post('/tickets/check', [StaffTicketCheckController::class, 'check'])
        ->name('tickets.check.submit');

    Route::get('/tickets/not-found', [StaffTicketCheckController::class, 'notFound'])
        ->name('tickets.notFound');

    Route::get('/tickets/{booking}/valid', [StaffTicketCheckController::class, 'valid'])
        ->name('tickets.valid');

    Route::get('/tickets/{booking}/used', [StaffTicketCheckController::class, 'used'])
        ->name('tickets.used');

    Route::post('/tickets/{booking}/confirm-used', [StaffTicketCheckController::class, 'confirmUsed'])
        ->name('tickets.confirmUsed');

    Route::post('/tickets/{booking}/use', [StaffTicketCheckController::class, 'confirmUsed'])
        ->name('tickets.use');

    Route::get('/tickets', [StaffTicketCheckController::class, 'index'])
        ->name('tickets.index');

    Route::get('/counter-sale', function () {
        return view('staff.sales.counter');
    })->name('sales.counter');
});

// API endpoint for dynamic room loading (used by Showtime create/edit forms)
Route::get('/api/cinemas/{cinema}/rooms', function (App\Models\Cinema $cinema) {
    return $cinema->rooms()->select('id', 'name')->get();
})->middleware('admin');


Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('foods', AdminFoodController::class)->except(['show']);
    Route::resource('food-orders', AdminFoodOrderController::class)->only(['index', 'show']);
    Route::resource('vouchers', AdminVoucherController::class)->except(['show']);

    Route::resource('movies', AdminMovieController::class);
    Route::resource('genres', AdminGenreController::class)->except(['show']);
    Route::resource('cinemas', AdminCinemaController::class)->except(['show']);
    Route::resource('rooms', AdminRoomController::class)->except(['show']);

    Route::get('/seats', [AdminSeatController::class, 'index'])->name('seats.index');
    Route::get('/seats/manage/{room}', [AdminSeatController::class, 'manage'])->name('seats.manage');
    Route::post('/seats/generate/{room}', [AdminSeatController::class, 'generate'])->name('seats.generate');
    Route::patch('/seats/{seat}', [AdminSeatController::class, 'update'])->name('seats.update');

    Route::resource('showtimes', AdminShowtimeController::class)->except(['show']);

    Route::resource('bookings', AdminBookingController::class)->only(['index', 'show']);

    Route::resource('users', AdminUserController::class)->only(['index', 'show']);

    Route::get('/reviews', function () {
        return view('admin.reviews.index');
    })->name('reviews.index');

    Route::get('/analytics/revenue', [AdminAnalyticsController::class, 'revenue'])
        ->name('analytics.revenue');

    Route::get('/analytics/top-movies', [AdminAnalyticsController::class, 'topMovies'])
        ->name('analytics.topMovies');

    Route::get('/ai/movie-content', [AdminAiContentController::class, 'movieContent'])
        ->name('ai.movieContent');
    Route::post('/ai/movie-content', [AdminAiContentController::class, 'movieContentStore'])
        ->name('ai.movieContent.store');
});
