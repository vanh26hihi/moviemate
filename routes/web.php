<?php

use App\Http\Controllers\Admin\CinemaController as AdminCinemaController;
use App\Http\Controllers\Admin\FoodController as AdminFoodController;
use App\Http\Controllers\Admin\FoodOrderController as AdminFoodOrderController;
use App\Http\Controllers\Admin\GenreController as AdminGenreController;
use App\Http\Controllers\Admin\MovieController as AdminMovieController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Admin\SeatController as AdminSeatController;
use App\Http\Controllers\Admin\ShowtimeController as AdminShowtimeController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\User\BookingController;
use App\Http\Controllers\User\FoodController as UserFoodController;
use App\Http\Controllers\User\HomeController;
use App\Http\Controllers\User\MovieController;
use App\Http\Controllers\User\OrderController as UserOrderController;
use App\Models\Cinema;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('user.auth.login');
    })->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');

    Route::get('/register', function () {
        return view('user.auth.register');
    })->name('register');

    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

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

Route::get('/my-ticket/{booking}', [BookingController::class, 'ticket'])
    ->name('user.bookings.ticket');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/booking-history', function () {
        return view('user.bookings.history');
    })->name('user.bookings.history');

    Route::get('/profile', function () {
        return view('user.profile.index');
    })->name('user.profile');
});

Route::get('/ai/recommend', function () {
    return view('user.ai.recommend');
})->name('user.ai.recommend');

Route::get('/ai/chatbot', function () {
    return view('user.ai.chatbot');
})->name('user.ai.chatbot');

Route::get('/api/cinemas/{cinema}/rooms', function (Cinema $cinema) {
    return $cinema->rooms()
        ->select('id', 'name', 'room_type')
        ->orderBy('name')
        ->get();
})->name('api.cinemas.rooms');

Route::get('/foods', [UserFoodController::class, 'index'])->name('foods.index');
Route::post('/foods/add', [UserFoodController::class, 'addToCart'])->name('foods.add');
Route::get('/foods/cart', [UserOrderController::class, 'cart'])->name('foods.cart');
Route::get('/foods/checkout', [UserOrderController::class, 'checkout'])->name('foods.checkout');
Route::post('/foods/store', [UserOrderController::class, 'store'])->name('foods.store');
Route::get('/foods/success/{order}', [UserOrderController::class, 'success'])->name('foods.success');

Route::prefix('admin')->name('admin.')
    ->middleware(['auth', 'active', 'permission:admin.access'])
    ->group(function () {
        Route::get('/', fn () => view('admin.dashboard'))
            ->middleware('permission:dashboard.view')->name('dashboard');

        Route::resource('foods', AdminFoodController::class)->except(['show'])
            ->middlewareFor('index', 'permission:foods.view')
            ->middlewareFor(['create', 'store'], 'permission:foods.create')
            ->middlewareFor(['edit', 'update'], 'permission:foods.update')
            ->middlewareFor('destroy', 'permission:foods.delete');

        Route::resource('movies', AdminMovieController::class)
            ->middlewareFor(['index', 'show'], 'permission:movies.view')
            ->middlewareFor(['create', 'store'], 'permission:movies.create')
            ->middlewareFor(['edit', 'update'], 'permission:movies.update')
            ->middlewareFor('destroy', 'permission:movies.delete');

        Route::resource('genres', AdminGenreController::class)->except(['show'])
            ->middlewareFor('index', 'permission:genres.view')
            ->middlewareFor(['create', 'store'], 'permission:genres.create')
            ->middlewareFor(['edit', 'update'], 'permission:genres.update')
            ->middlewareFor('destroy', 'permission:genres.delete');

        Route::resource('cinemas', AdminCinemaController::class)->except(['show'])
            ->middlewareFor('index', 'permission:cinema.view')
            ->middlewareFor(['create', 'store'], 'permission:cinema.create')
            ->middlewareFor(['edit', 'update'], 'permission:cinema.update')
            ->middlewareFor('destroy', 'permission:cinema.delete');

        Route::resource('rooms', AdminRoomController::class)->except(['show'])
            ->middlewareFor('index', 'permission:rooms.view')
            ->middlewareFor(['create', 'store'], 'permission:rooms.create')
            ->middlewareFor(['edit', 'update'], 'permission:rooms.update')
            ->middlewareFor('destroy', 'permission:rooms.delete');

        Route::get('/seats', [AdminSeatController::class, 'index'])
            ->middleware('permission:seats.view')->name('seats.index');
        Route::get('/seats/manage/{room}', [AdminSeatController::class, 'manage'])
            ->middleware('permission:seats.manage')->name('seats.manage');
        Route::post('/seats/generate/{room}', [AdminSeatController::class, 'generate'])
            ->middleware('permission:seats.manage')->name('seats.generate');
        Route::patch('/seats/{seat}', [AdminSeatController::class, 'update'])
            ->middleware('permission:seats.manage')->name('seats.update');

        Route::resource('showtimes', AdminShowtimeController::class)->except(['show'])
            ->middlewareFor('index', 'permission:showtimes.view')
            ->middlewareFor(['create', 'store'], 'permission:showtimes.create')
            ->middlewareFor(['edit', 'update'], 'permission:showtimes.update')
            ->middlewareFor('destroy', 'permission:showtimes.delete');

        Route::get('/food-orders', [AdminFoodOrderController::class, 'index'])
            ->middleware('permission:food-orders.view')->name('food-orders.index');
        Route::get('/food-orders/{order}', [AdminFoodOrderController::class, 'show'])
            ->middleware('permission:food-orders.view')->name('food-orders.show');

        Route::get('/users', [AdminUserController::class, 'index'])
            ->middleware('permission:users.view')->name('users.index');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])
            ->middleware('permission:users.view')->name('users.edit');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])
            ->middleware('permission:users.manage-role')->name('users.role.update');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])
            ->middleware('permission:users.manage-status')->name('users.status.update');

        Route::get('/roles', [AdminRoleController::class, 'index'])
            ->middleware('permission:roles.view')->name('roles.index');
        Route::get('/roles/{role}/edit', [AdminRoleController::class, 'edit'])
            ->middleware('permission:roles.manage')->name('roles.edit');
        Route::patch('/roles/{role}/permissions', [AdminRoleController::class, 'update'])
            ->middleware('permission:roles.manage')->name('roles.permissions.update');
    });

Route::get('/manager', fn () => redirect()->route('admin.dashboard'))
    ->middleware(['auth', 'active', 'role:manager,admin', 'permission:admin.access'])
    ->name('manager.dashboard');

Route::prefix('staff')->name('staff.')
    ->middleware(['auth', 'active', 'role:staff,manager,admin'])
    ->group(function () {
        Route::get('/', fn () => view('staff.dashboard'))
            ->middleware('permission:dashboard.view')->name('dashboard');
        Route::get('/tickets', fn () => view('staff.tickets.index'))
            ->middleware('permission:bookings.view')->name('tickets.index');
        Route::get('/tickets/check', fn () => view('staff.tickets.check'))
            ->middleware('permission:tickets.checkin')->name('tickets.check');
        Route::get('/sales/counter', fn () => view('staff.sales.counter'))
            ->middleware('permission:bookings.operate')->name('sales.counter');
    });
