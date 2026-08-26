<?php

use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\BookingOperationController as AdminBookingOperationController;
use App\Http\Controllers\Admin\BulkShowtimeController as AdminBulkShowtimeController;
use App\Http\Controllers\Admin\CinemaContextController as AdminCinemaContextController;
use App\Http\Controllers\Admin\CinemaController as AdminCinemaController;
use App\Http\Controllers\Admin\CinemaOperatingHoursController as AdminCinemaOperatingHoursController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DiscountController as AdminDiscountController;
use App\Http\Controllers\Admin\FoodController as AdminFoodController;
use App\Http\Controllers\Admin\FoodOrderController as AdminFoodOrderController;
use App\Http\Controllers\Admin\GenreController as AdminGenreController;
use App\Http\Controllers\Admin\MovieController as AdminMovieController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PaymentReconciliationController as AdminPaymentReconciliationController;
use App\Http\Controllers\Admin\PaymentReviewController as AdminPaymentReviewController;
use App\Http\Controllers\Admin\PresentationFormatController as AdminPresentationFormatController;
use App\Http\Controllers\Admin\PriceBookController as AdminPriceBookController;
use App\Http\Controllers\Admin\RealtimeFieldValidationController as AdminRealtimeFieldValidationController;
use App\Http\Controllers\Admin\RefundCaseController as AdminRefundCaseController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Admin\RoomLayoutTemplateController as AdminRoomLayoutTemplateController;
use App\Http\Controllers\Admin\RoomTypeController as AdminRoomTypeController;
use App\Http\Controllers\Admin\SeatController as AdminSeatController;
use App\Http\Controllers\Admin\SeatIncidentResolutionController as AdminSeatIncidentResolutionController;
use App\Http\Controllers\Admin\SeatMaintenanceController as AdminSeatMaintenanceController;
use App\Http\Controllers\Admin\ShowtimeController as AdminShowtimeController;
use App\Http\Controllers\Admin\ShowtimePreviewController as AdminShowtimePreviewController;
use App\Http\Controllers\Admin\ShowtimeScheduleCopyController as AdminShowtimeScheduleCopyController;
use App\Http\Controllers\Admin\UserCinemaAssignmentController as AdminUserCinemaAssignmentController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CinemaContextController;
use App\Http\Controllers\Payments\PaymentInitiationController;
use App\Http\Controllers\Payments\PaymentResumeController;
use App\Http\Controllers\Payments\PayOsCancellationController;
use App\Http\Controllers\Payments\PayOsReturnController;
use App\Http\Controllers\Payments\PayOsWebhookController;
use App\Http\Controllers\Payments\VnpayIpnController;
use App\Http\Controllers\Payments\VnpayReturnController;
use App\Http\Controllers\Payments\ZaloPayCallbackController;
use App\Http\Controllers\Payments\ZaloPayReturnController;
use App\Http\Controllers\Staff\CounterPaymentController as StaffCounterPaymentController;
use App\Http\Controllers\Staff\CounterSaleController as StaffCounterSaleController;
use App\Http\Controllers\Staff\FoodPickupVoucherPrintController as StaffFoodPickupVoucherPrintController;
use App\Http\Controllers\Staff\PrintAllController as StaffPrintAllController;
use App\Http\Controllers\Staff\TicketPrintController as StaffTicketPrintController;
use App\Http\Controllers\Staff\TicketWorkspaceController as StaffTicketWorkspaceController;
use App\Http\Controllers\Staff\WorkspaceController as StaffWorkspaceController;
use App\Http\Controllers\User\AiChatStreamController;
use App\Http\Controllers\User\AiController;
use App\Http\Controllers\User\AiConversationController;
use App\Http\Controllers\User\AiConversationMessageController;
use App\Http\Controllers\User\BookingCancellationController;
use App\Http\Controllers\User\BookingCheckoutConfirmController;
use App\Http\Controllers\User\BookingController;
use App\Http\Controllers\User\BookingFoodSelectionController;
use App\Http\Controllers\User\BookingHistoryController;
use App\Http\Controllers\User\BookingPromotionController;
use App\Http\Controllers\User\BookingReviewController;
use App\Http\Controllers\User\CinemaController as UserCinemaController;
use App\Http\Controllers\User\FoodController as UserFoodController;
use App\Http\Controllers\User\GuestBookingAccessController;
use App\Http\Controllers\User\HomeController;
use App\Http\Controllers\User\MovieController;
use App\Http\Controllers\User\OrderController as UserOrderController;
use App\Http\Controllers\User\RetiredBookingStoreController;
use App\Http\Controllers\User\ReviewController as UserReviewController;
use App\Http\Controllers\User\ShowtimeFilterController;
use App\Http\Controllers\User\TicketEmailResendController;
use App\Http\Middleware\ProtectBookingResponses;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/cinema-context', CinemaContextController::class)->name('cinema-context.update');
Route::get('/cinemas', [UserCinemaController::class, 'index'])->middleware('throttle:60,1')->name('cinemas.index');
Route::get('/cinemas/{cinema:code}', [UserCinemaController::class, 'show'])
    ->where('cinema', '[A-Za-z0-9-]+')->name('cinemas.show');
Route::get('/showtimes/filter', ShowtimeFilterController::class)->middleware('throttle:60,1')->name('showtimes.filter');
Route::post('/payments/zalopay/callback', ZaloPayCallbackController::class)
    ->middleware('throttle:120,1')
    ->name('payments.zalopay.callback');

Route::post('/payments/payos/webhook', PayOsWebhookController::class)
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->middleware('throttle:240,1')
    ->name('payments.payos.webhook');

Route::get('/payments/payos/return', PayOsReturnController::class)
    ->middleware(ProtectBookingResponses::class)
    ->defaults('payos_return_mode', 'return')
    ->name('payments.payos.return');

Route::get('/payments/payos/cancel', PayOsReturnController::class)
    ->middleware(ProtectBookingResponses::class)
    ->defaults('payos_return_mode', 'cancel')
    ->name('payments.payos.cancel');

Route::get('/payments/zalopay/return', ZaloPayReturnController::class)
    ->middleware(ProtectBookingResponses::class)
    ->name('payments.zalopay.return');

Route::get('/payments/vnpay/ipn', VnpayIpnController::class)
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->name('payments.vnpay.ipn');

Route::get('/payments/vnpay/return', VnpayReturnController::class)
    ->middleware(ProtectBookingResponses::class)
    ->name('payments.vnpay.return');

Route::post('/payments/zalopay/bookings/{booking}', PaymentInitiationController::class)
    ->middleware([ProtectBookingResponses::class, 'throttle:20,1'])
    ->defaults('payment_provider', 'zalopay')
    ->name('payments.zalopay.initiate');

Route::post('/payments/vnpay/bookings/{booking}', PaymentInitiationController::class)
    ->middleware([ProtectBookingResponses::class, 'throttle:20,1'])
    ->defaults('payment_provider', 'vnpay')
    ->name('payments.vnpay.initiate');

Route::post('/payments/payos/bookings/{booking}', PaymentInitiationController::class)
    ->middleware([ProtectBookingResponses::class, 'throttle:20,1'])
    ->defaults('payment_provider', 'payos')
    ->name('payments.payos.initiate');

Route::post('/payments/bookings/{booking}/resume', PaymentResumeController::class)
    ->middleware([ProtectBookingResponses::class, 'throttle:20,1'])
    ->name('payments.resume');

Route::post('/payments/payos/bookings/{booking}/cancel', PayOsCancellationController::class)
    ->middleware(['auth', 'active', 'throttle:10,1'])
    ->name('payments.payos.cancel-attempt');

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('user.auth.login', [
            'googleAuthConfigured' => GoogleAuthController::isConfigured(),
        ]);
    })->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');

    Route::get('/register', function () {
        return view('user.auth.register', [
            'googleAuthConfigured' => GoogleAuthController::isConfigured(),
        ]);
    })->name('register');

    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->name('register.store');

    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');

    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/movies', [MovieController::class, 'index'])->name('user.movies.index');

Route::get('/movies/{slug}', [MovieController::class, 'show'])->name('user.movies.show');

Route::get('/booking/select-seat/{showtime}', [BookingController::class, 'selectSeat'])
    ->name('user.bookings.selectSeat');

Route::get('/booking/checkout/{showtime}', [BookingController::class, 'checkout'])
    ->middleware([ProtectBookingResponses::class, 'throttle:30,1'])
    ->name('user.bookings.checkout');

Route::get('/booking/food', [BookingFoodSelectionController::class, 'show'])
    ->middleware([ProtectBookingResponses::class, 'throttle:30,1'])
    ->name('user.bookings.food');

Route::post('/booking/food', [BookingFoodSelectionController::class, 'store'])
    ->middleware([ProtectBookingResponses::class, 'throttle:booking-food-mutation'])
    ->name('user.bookings.food.store');

Route::get('/booking/review', BookingReviewController::class)
    ->middleware([ProtectBookingResponses::class, 'throttle:30,1'])
    ->name('user.bookings.review');

Route::post('/booking/promotions', BookingPromotionController::class)
    ->middleware([ProtectBookingResponses::class, 'throttle:20,1'])
    ->name('user.bookings.promotions');

Route::post('/booking/confirm', BookingCheckoutConfirmController::class)
    ->middleware([ProtectBookingResponses::class, 'throttle:booking-hold-creation'])
    ->name('user.bookings.confirm');

Route::post('/booking/store', RetiredBookingStoreController::class)
    ->middleware(ProtectBookingResponses::class)
    ->name('user.bookings.store');

Route::get('/booking/success/{booking}', [BookingController::class, 'success'])
    ->middleware(ProtectBookingResponses::class)
    ->name('user.bookings.success');

Route::get('/booking/payment/pending/{booking}', [BookingController::class, 'success'])
    ->middleware(ProtectBookingResponses::class)
    ->name('user.bookings.pending');

Route::get('/booking/payment/failed/{booking}', [BookingController::class, 'success'])
    ->middleware(ProtectBookingResponses::class)
    ->name('user.bookings.failed');

Route::get('/booking/payment/review/{booking}', [BookingController::class, 'success'])
    ->middleware(ProtectBookingResponses::class)
    ->name('user.bookings.payment-review');

Route::get('/booking/payment/expired/{booking}', [BookingController::class, 'success'])
    ->middleware(ProtectBookingResponses::class)
    ->name('user.bookings.expired');

Route::get('/bookings/{booking}/ticket', [BookingController::class, 'ticket'])
    ->middleware(ProtectBookingResponses::class)
    ->name('user.bookings.ticket');

Route::get('/my-ticket/{booking}', [BookingController::class, 'ticket'])
    ->middleware(ProtectBookingResponses::class)
    ->name('user.bookings.ticket.legacy');

Route::post('/bookings/{booking}/ticket-email/resend', TicketEmailResendController::class)
    ->middleware([ProtectBookingResponses::class, 'throttle:3,1'])
    ->name('user.bookings.ticket-email.resend');

Route::get('/booking/access/{booking}', [GuestBookingAccessController::class, 'show'])
    ->middleware([ProtectBookingResponses::class, 'throttle:30,1'])
    ->name('user.bookings.access.show');

Route::post('/booking/access/{booking}', [GuestBookingAccessController::class, 'exchange'])
    ->middleware([ProtectBookingResponses::class, 'throttle:10,1'])
    ->name('user.bookings.access.exchange');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/ai/conversations', [AiConversationController::class, 'index'])
        ->name('user.ai.conversations.index');
    Route::post('/ai/conversations', [AiConversationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('user.ai.conversations.store');
    Route::get('/ai/conversations/{conversation}', [AiConversationController::class, 'show'])
        ->whereNumber('conversation')
        ->name('user.ai.conversations.show');
    Route::patch('/ai/conversations/{conversation}', [AiConversationController::class, 'update'])
        ->whereNumber('conversation')
        ->middleware('throttle:20,1')
        ->name('user.ai.conversations.update');
    Route::delete('/ai/conversations/{conversation}', [AiConversationController::class, 'destroy'])
        ->whereNumber('conversation')
        ->middleware('throttle:20,1')
        ->name('user.ai.conversations.destroy');
    Route::get('/ai/conversations/{conversation}/messages', [AiConversationMessageController::class, 'index'])
        ->whereNumber('conversation')
        ->name('user.ai.conversations.messages.index');
    Route::post('/ai/conversations/{conversation}/messages', AiConversationMessageController::class)
        ->whereNumber('conversation')
        ->middleware('throttle:ai-chat')
        ->name('user.ai.conversations.messages.store');
    Route::post('/ai/conversations/{conversation}/messages/stream', [AiChatStreamController::class, 'authenticated'])
        ->whereNumber('conversation')->middleware('throttle:ai-chat')
        ->name('user.ai.conversations.messages.stream');
    Route::post('/ai/conversations/{conversation}/messages/{message}/retry-stream', [AiChatStreamController::class, 'retryAuthenticated'])
        ->whereNumber('conversation')->whereNumber('message')->middleware('throttle:ai-chat')
        ->name('user.ai.conversations.messages.retry');

    Route::get('/booking-history', BookingHistoryController::class)
        ->name('user.bookings.history');

    Route::delete('/bookings/{booking}', BookingCancellationController::class)
        ->middleware('throttle:10,1')
        ->name('user.bookings.cancel');

    Route::get('/profile', function () {
        return view('user.profile.index');
    })->name('user.profile');
    Route::get('/my-reviews', [UserReviewController::class, 'index'])->name('user.reviews.index');
    Route::post('/movies/{movie}/reviews', [UserReviewController::class, 'store'])->middleware('throttle:10,1')->name('user.reviews.store');
});

Route::get('/ai/recommend', [AiController::class, 'recommend'])->name('user.ai.recommend');
Route::post('/ai/recommend', [AiController::class, 'recommendStore'])
    ->middleware('throttle:ai-recommendation')->name('user.ai.recommend.submit');

Route::get('/ai/chatbot', [AiController::class, 'chatbot'])->name('user.ai.chatbot');
Route::post('/ai/chatbot', [AiController::class, 'chatbotStore'])
    ->middleware('throttle:ai-chat')->name('user.ai.chatbot.submit');
Route::post('/ai/chatbot/stream', [AiChatStreamController::class, 'guest'])
    ->middleware('throttle:ai-chat')->name('user.ai.chatbot.stream');
Route::post('/ai/chatbot/stream/retry', [AiChatStreamController::class, 'retryGuest'])
    ->middleware('throttle:ai-chat')->name('user.ai.chatbot.stream.retry');

Route::get('/admin/rooms/{room}/layout/preview', [AdminSeatController::class, 'preview'])
    ->middleware(['auth', 'active', 'permission:admin.access', 'permission:seats.view'])
    ->name('admin.rooms.layout.preview');

Route::get('/foods', [UserFoodController::class, 'index'])->name('foods.index');
Route::post('/foods/add', [UserOrderController::class, 'retired'])->name('foods.add');
Route::get('/foods/cart', [UserOrderController::class, 'retired'])->name('foods.cart');
Route::get('/foods/checkout', [UserOrderController::class, 'retired'])->name('foods.checkout');
Route::post('/foods/store', [UserOrderController::class, 'retired'])->name('foods.store');
Route::get('/foods/success/{order}', [UserOrderController::class, 'retired'])
    ->whereNumber('order')
    ->name('foods.success');

Route::prefix('admin')->name('admin.')
    ->middleware(['auth', 'active', 'permission:admin.access', 'admin.cinema.scope'])
    ->group(function () {
        Route::post('/validation/field', AdminRealtimeFieldValidationController::class)
            ->middleware(['permission:dashboard.view', 'throttle:60,1'])->name('validation.field');
        Route::get('/', AdminDashboardController::class)
            ->middleware('permission:dashboard.view')->name('dashboard');
        Route::get('/reports', AdminReportController::class)
            ->middleware('permission:reports.view')->name('reports.index');
        Route::get('/reports/export', [AdminReportController::class, 'export'])
            ->middleware('permission:reports.view')->name('reports.export');

        Route::get('/activity-logs', [AdminActivityLogController::class, 'index'])
            ->middleware('permission:activity_logs.view')->name('activity-logs.index');
        Route::get('/activity-logs/{activityLog}', [AdminActivityLogController::class, 'show'])
            ->whereNumber('activityLog')
            ->middleware('permission:activity_logs.view')->name('activity-logs.show');

        Route::get('/bookings', [AdminBookingController::class, 'index'])
            ->middleware('permission:bookings.view')->name('bookings.index');
        Route::get('/bookings/{booking}', [AdminBookingController::class, 'show'])
            ->whereNumber('booking')
            ->middleware('permission:bookings.view')->name('bookings.show');
        Route::post('/bookings/{booking}/ticket-email/resend', [AdminBookingOperationController::class, 'resendTicket'])
            ->whereNumber('booking')
            ->middleware('permission:ticket_deliveries.retry')->name('bookings.ticket-email.resend');
        Route::post('/bookings/{booking}/payment-query', [AdminBookingOperationController::class, 'queryPayment'])
            ->whereNumber('booking')
            ->middleware('permission:payments.reconcile')->name('bookings.payment-query');
        Route::post('/bookings/{booking}/cancel', [AdminBookingOperationController::class, 'cancel'])
            ->whereNumber('booking')
            ->middleware('permission:bookings.operate')->name('bookings.cancel');
        Route::resource('foods', AdminFoodController::class)->except(['show'])
            ->middlewareFor('index', 'permission:foods.view')
            ->middlewareFor(['create', 'store'], ['role:admin', 'permission:foods.create'])
            ->middlewareFor(['edit', 'update'], ['role:admin', 'permission:foods.update'])
            ->middlewareFor('destroy', ['role:admin', 'permission:foods.delete']);

        Route::resource('discounts', AdminDiscountController::class)->except(['show', 'destroy'])
            ->middlewareFor('index', 'permission:discounts.view')
            ->middlewareFor(['create', 'store', 'edit', 'update'], 'permission:discounts.manage');
        Route::patch('/discounts/{discount}/archive', [AdminDiscountController::class, 'archive'])
            ->middleware('permission:discounts.manage')->name('discounts.archive');

        Route::get('/price-books', [AdminPriceBookController::class, 'index'])
            ->middleware('permission:pricing.view')->name('price-books.index');
        Route::get('/price-books/preview', [AdminPriceBookController::class, 'previewRedirect'])
            ->middleware('permission:pricing.view')->name('price-books.preview.redirect');
        Route::post('/price-books/preview', [AdminPriceBookController::class, 'preview'])
            ->middleware(['permission:pricing.view', 'throttle:60,1'])->name('price-books.preview');
        Route::get('/price-books/versions/{version}', [AdminPriceBookController::class, 'show'])
            ->whereNumber('version')->middleware('permission:pricing.view')->name('price-books.versions.show');
        Route::post('/price-books/versions/{version}/copy', [AdminPriceBookController::class, 'copy'])
            ->whereNumber('version')->middleware('permission:pricing.manage')->name('price-books.versions.copy');
        Route::patch('/price-books/versions/{version}', [AdminPriceBookController::class, 'update'])
            ->whereNumber('version')->middleware('permission:pricing.manage')->name('price-books.versions.update');
        Route::patch('/price-books/versions/{version}/simple-prices', [AdminPriceBookController::class, 'updateSimplePrices'])
            ->whereNumber('version')->middleware('permission:pricing.manage')->name('price-books.versions.simple-prices.update');
        Route::post('/price-books/versions/{version}/adjustments', [AdminPriceBookController::class, 'storeAdjustment'])
            ->whereNumber('version')->middleware('permission:pricing.manage')->name('price-books.versions.adjustments.store');
        Route::patch('/price-books/versions/{version}/adjustments/{adjustment}', [AdminPriceBookController::class, 'updateAdjustment'])
            ->whereNumber(['version', 'adjustment'])->middleware('permission:pricing.manage')->name('price-books.versions.adjustments.update');
        Route::delete('/price-books/versions/{version}/adjustments/{adjustment}', [AdminPriceBookController::class, 'destroyAdjustment'])
            ->whereNumber(['version', 'adjustment'])->middleware('permission:pricing.manage')->name('price-books.versions.adjustments.destroy');
        Route::post('/price-books/versions/{version}/publish', [AdminPriceBookController::class, 'publish'])
            ->whereNumber('version')->middleware('permission:pricing.manage')->name('price-books.versions.publish');
        Route::post('/price-books/versions/{version}/retire', [AdminPriceBookController::class, 'retire'])
            ->whereNumber('version')->middleware('permission:pricing.manage')->name('price-books.versions.retire');
        Route::get('/price-books/versions/{version}/schedule-change/preview', [AdminPriceBookController::class, 'scheduleChangePreviewRedirect'])
            ->whereNumber('version')->middleware('permission:pricing.manage')->name('price-books.versions.schedule-change.preview.redirect');
        Route::post('/price-books/versions/{version}/schedule-change/preview', [AdminPriceBookController::class, 'previewScheduleChange'])
            ->whereNumber('version')->middleware(['permission:pricing.manage', 'throttle:30,1'])->name('price-books.versions.schedule-change.preview');
        Route::post('/price-books/versions/{version}/schedule-change/apply', [AdminPriceBookController::class, 'applyScheduleChange'])
            ->whereNumber('version')->middleware(['permission:pricing.manage', 'throttle:10,1'])->name('price-books.versions.schedule-change.apply');
        Route::get('/reviews', [AdminReviewController::class, 'index'])->middleware('permission:reviews.view')->name('reviews.index');
        Route::patch('/reviews/{review}', [AdminReviewController::class, 'moderate'])->middleware('permission:reviews.moderate')->name('reviews.moderate');
        Route::resource('movies', AdminMovieController::class)->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:movies.view')
            ->middlewareFor(['create', 'store'], ['role:admin', 'permission:movies.create'])
            ->middlewareFor(['edit', 'update'], ['role:admin', 'permission:movies.update']);

        Route::post('/movies/{movie}/lifecycle', [AdminMovieController::class, 'lifecycle'])
            ->middleware(['role:admin', 'permission:movies.lifecycle'])->name('movies.lifecycle');

        Route::resource('layout-templates', AdminRoomLayoutTemplateController::class)
            ->parameters(['layout-templates' => 'layout_template'])->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:layout_templates.view')
            ->middlewareFor(['create', 'store', 'edit', 'update'], 'permission:layout_templates.manage');
        Route::post('/layout-templates/{layout_template}/activate', [AdminRoomLayoutTemplateController::class, 'activate'])
            ->middleware('permission:layout_templates.manage')->name('layout-templates.activate');
        Route::post('/layout-templates/{layout_template}/archive', [AdminRoomLayoutTemplateController::class, 'archive'])
            ->middleware('permission:layout_templates.manage')->name('layout-templates.archive');

        Route::resource('genres', AdminGenreController::class)->except(['show'])
            ->middlewareFor('index', 'permission:genres.view')
            ->middlewareFor(['create', 'store'], ['role:admin', 'permission:genres.create'])
            ->middlewareFor(['edit', 'update'], ['role:admin', 'permission:genres.update'])
            ->middlewareFor('destroy', ['role:admin', 'permission:genres.delete']);

        Route::post('/cinema-context', AdminCinemaContextController::class)
            ->middleware('permission:cinemas.view')->name('cinema-context.update');
        Route::get('/cinema', [AdminCinemaController::class, 'legacyShow'])
            ->middleware('permission:cinema.view')->name('cinema.show');
        Route::patch('/cinema', [AdminCinemaController::class, 'legacyUpdate'])
            ->middleware('permission:cinema.update')->name('cinema.update');
        Route::resource('cinemas', AdminCinemaController::class)->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:cinemas.view')
            ->middlewareFor(['create', 'store', 'edit', 'update'], 'permission:cinemas.manage');
        Route::patch('/cinemas/{cinema}/operating-hours', [AdminCinemaOperatingHoursController::class, 'update'])
            ->middleware('permission:cinemas.operations.manage')->name('cinemas.operating-hours.update');

        Route::get('/room-types', [AdminRoomTypeController::class, 'index'])
            ->middleware('permission:room_types.view')->name('room-types.index');
        Route::get('/room-types/create', [AdminRoomTypeController::class, 'create'])
            ->middleware('permission:room_types.manage')->name('room-types.create');
        Route::post('/room-types', [AdminRoomTypeController::class, 'store'])
            ->middleware('permission:room_types.manage')->name('room-types.store');
        Route::get('/room-types/{roomType}/edit', [AdminRoomTypeController::class, 'edit'])
            ->whereNumber('roomType')->middleware('permission:room_types.manage')->name('room-types.edit');
        Route::put('/room-types/{roomType}', [AdminRoomTypeController::class, 'update'])
            ->whereNumber('roomType')->middleware('permission:room_types.manage')->name('room-types.update');
        Route::patch('/room-types/{roomType}/status', [AdminRoomTypeController::class, 'status'])
            ->whereNumber('roomType')->middleware('permission:room_types.manage')->name('room-types.status');

        Route::get('/presentation-formats', [AdminPresentationFormatController::class, 'index'])
            ->middleware('permission:presentation_formats.view')->name('presentation-formats.index');
        Route::get('/presentation-formats/create', [AdminPresentationFormatController::class, 'create'])
            ->middleware('permission:presentation_formats.manage')->name('presentation-formats.create');
        Route::post('/presentation-formats', [AdminPresentationFormatController::class, 'store'])
            ->middleware('permission:presentation_formats.manage')->name('presentation-formats.store');
        Route::get('/presentation-formats/{presentationFormat}/edit', [AdminPresentationFormatController::class, 'edit'])
            ->whereNumber('presentationFormat')->middleware('permission:presentation_formats.manage')->name('presentation-formats.edit');
        Route::put('/presentation-formats/{presentationFormat}', [AdminPresentationFormatController::class, 'update'])
            ->whereNumber('presentationFormat')->middleware('permission:presentation_formats.manage')->name('presentation-formats.update');
        Route::patch('/presentation-formats/{presentationFormat}/archive', [AdminPresentationFormatController::class, 'archive'])
            ->whereNumber('presentationFormat')->middleware('permission:presentation_formats.manage')->name('presentation-formats.archive');

        Route::patch('/rooms/{room}/status', [AdminRoomController::class, 'updateStatus'])
            ->middleware('permission:rooms.update')->name('rooms.status.update');
        Route::resource('rooms', AdminRoomController::class)->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:rooms.view')
            ->middlewareFor(['create', 'store'], 'permission:rooms.create')
            ->middlewareFor(['edit', 'update'], 'permission:rooms.update');
        Route::delete('/rooms/{room}', [AdminRoomController::class, 'destroy'])
            ->middleware('permission:rooms.update')->name('rooms.destroy');
        Route::post('/rooms/{room}/layout/apply-template', [AdminRoomController::class, 'applyTemplate'])
            ->middleware('permission:room_layouts.apply_template')->name('rooms.layout.apply-template');

        Route::get('/rooms/{room}/layout', [AdminSeatController::class, 'layout'])
            ->middleware('permission:seats.view')->name('rooms.layout.show');
        Route::post('/rooms/{room}/layout/draft', [AdminSeatController::class, 'createDraft'])
            ->middleware('permission:seats.manage')->name('rooms.layout.draft');
        Route::patch('/rooms/{room}/layout/draft', [AdminSeatController::class, 'saveDraft'])
            ->middleware('permission:seats.manage')->name('rooms.layout.update');
        Route::post('/rooms/{room}/layout/publish', [AdminSeatController::class, 'publish'])
            ->middleware('permission:seats.manage')->name('rooms.layout.publish');

        Route::get('/rooms/{room}/seat-maintenance', [AdminSeatMaintenanceController::class, 'index'])
            ->whereNumber('room')
            ->middleware('permission:seats.maintenance.view')->name('rooms.seat-maintenance.index');
        Route::get('/rooms/{room}/seat-maintenance/incidents/{incident}', [AdminSeatMaintenanceController::class, 'showIncident'])
            ->whereNumber('room')->whereNumber('incident')
            ->middleware('permission:seats.maintenance.view')->name('rooms.seat-incidents.show');
        Route::post('/rooms/{room}/seat-maintenance/incidents/{incident}/impacts/{impact}/relocate', [AdminSeatIncidentResolutionController::class, 'relocate'])
            ->whereNumber('room')->whereNumber('incident')->whereNumber('impact')
            ->middleware('permission:seats.maintenance.update')->name('rooms.seat-incidents.relocate');
        Route::post('/rooms/{room}/seat-maintenance/incidents/{incident}/impacts/{impact}/requires-refund', [AdminSeatIncidentResolutionController::class, 'requireRefund'])
            ->whereNumber('room')->whereNumber('incident')->whereNumber('impact')
            ->middleware('permission:seats.maintenance.update')->name('rooms.seat-incidents.requires-refund');
        Route::patch('/rooms/{room}/seat-maintenance/{seat}', [AdminSeatMaintenanceController::class, 'update'])
            ->whereNumber('room')->whereNumber('seat')->scopeBindings()
            ->middleware('permission:seats.maintenance.update')->name('rooms.seat-maintenance.update');
        Route::post('/rooms/{room}/seat-maintenance/bulk', [AdminSeatMaintenanceController::class, 'bulk'])
            ->whereNumber('room')
            ->middleware('permission:seats.maintenance.update')->name('rooms.seat-maintenance.bulk');

        Route::get('/seats', [AdminSeatController::class, 'index'])
            ->middleware('permission:seats.maintenance.view')->name('seats.index');
        Route::get('/seats/manage/{room}', [AdminSeatController::class, 'manage'])
            ->middleware('permission:seats.manage')->name('seats.manage');
        Route::post('/seats/generate/{room}', [AdminSeatController::class, 'generate'])
            ->middleware('permission:seats.manage')->name('seats.generate');
        Route::post('/showtimes/preview', AdminShowtimePreviewController::class)
            ->middleware(['permission:showtimes.create', 'throttle:60,1'])
            ->name('showtimes.preview');

        Route::get('/showtimes/copy', [AdminShowtimeScheduleCopyController::class, 'index'])
            ->middleware('permission:showtimes.create')
            ->name('showtimes.copy.index');
        Route::post('/showtimes/copy/generate', [AdminShowtimeScheduleCopyController::class, 'generate'])
            ->middleware(['permission:showtimes.create', 'throttle:60,1'])
            ->name('showtimes.copy.generate');

        Route::get('/showtimes/bulk', [AdminBulkShowtimeController::class, 'index'])
            ->middleware('permission:showtimes.create')
            ->name('showtimes.bulk.index');
        Route::post('/showtimes/bulk/preview', [AdminBulkShowtimeController::class, 'preview'])
            ->middleware(['permission:showtimes.create', 'throttle:60,1'])
            ->name('showtimes.bulk.preview');
        Route::post('/showtimes/bulk', [AdminBulkShowtimeController::class, 'store'])
            ->middleware(['permission:showtimes.create', 'throttle:60,1'])
            ->name('showtimes.bulk.store');

        Route::get('/showtimes/{showtime}/cancellation', [AdminShowtimeController::class, 'cancellation'])
            ->whereNumber('showtime')->middleware('permission:showtimes.delete')->name('showtimes.cancellation');

        Route::resource('showtimes', AdminShowtimeController::class)
            ->middlewareFor(['index', 'show'], 'permission:showtimes.view')
            ->middlewareFor(['create', 'store'], 'permission:showtimes.create')
            ->middlewareFor(['edit', 'update'], 'permission:showtimes.update')
            ->middlewareFor('destroy', 'permission:showtimes.delete');

        Route::get('/food-orders', [AdminFoodOrderController::class, 'index'])
            ->middleware('permission:food-orders.view')->name('food-orders.index');
        Route::get('/food-orders/{order}', [AdminFoodOrderController::class, 'show'])
            ->middleware('permission:food-orders.view')->name('food-orders.show');

        Route::get('/payments', [AdminPaymentController::class, 'index'])
            ->middleware('permission:payments.view')->name('payments.index');
        Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])
            ->whereNumber('payment')
            ->middleware('permission:payments.view')->name('payments.show');
        Route::get('/refunds', [AdminRefundCaseController::class, 'index'])
            ->middleware('permission:refunds.view')->name('refunds.index');
        Route::patch('/refunds/{refundCase}', [AdminRefundCaseController::class, 'update'])
            ->whereNumber('refundCase')->middleware(['permission:refunds.resolve', 'throttle:12,1'])->name('refunds.update');
        Route::get('/payment-reconciliation', [AdminPaymentReconciliationController::class, 'index'])
            ->middleware('permission:payments.reconcile')->name('payment-reconciliation.index');
        Route::get('/payment-reconciliation/export', [AdminPaymentReconciliationController::class, 'export'])
            ->middleware('permission:payments.reconcile')->name('payment-reconciliation.export');
        Route::post('/payments/{payment}/query-provider', [AdminPaymentReconciliationController::class, 'queryProvider'])
            ->whereNumber('payment')
            ->middleware(['permission:payments.reconcile', 'throttle:12,1'])
            ->name('payments.query-provider');
        Route::post('/payments/{payment}/reconcile', [AdminPaymentReconciliationController::class, 'reconcile'])
            ->whereNumber('payment')
            ->middleware(['permission:payments.reconcile', 'throttle:12,1'])
            ->name('payments.reconcile');

        Route::get('/payment-reviews', [AdminPaymentReviewController::class, 'index'])
            ->middleware('permission:payments.reconcile')->name('payment-reviews.index');
        Route::post('/payment-reviews/{paymentId}/reconcile', [AdminPaymentReviewController::class, 'resolve'])
            ->whereNumber('paymentId')
            ->middleware(['permission:payments.reconcile', 'throttle:6,1'])
            ->name('payment-reviews.resolve');

        Route::get('/users', [AdminUserController::class, 'index'])
            ->middleware('permission:users.view')->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])
            ->whereNumber('user')->middleware('permission:users.view')->name('users.show');
        Route::get('/users/{user}/bookings/export', [AdminUserController::class, 'exportBookings'])
            ->whereNumber('user')->middleware(['permission:users.view', 'throttle:20,1'])->name('users.bookings.export');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])
            ->middleware('permission:users.view')->name('users.edit');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])
            ->middleware('permission:users.manage-role')->name('users.role.update');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])
            ->middleware('permission:users.manage-status')->name('users.status.update');
        Route::post('/users/{user}/cinema-assignments', [AdminUserCinemaAssignmentController::class, 'store'])
            ->middleware('permission:cinema_assignments.manage')->name('users.cinema-assignments.store');
        Route::delete('/users/{user}/cinema-assignments/{assignment}', [AdminUserCinemaAssignmentController::class, 'destroy'])
            ->middleware('permission:cinema_assignments.manage')
            ->name('users.cinema-assignments.destroy');

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
        Route::get('/rooms/{room}/layout/preview', [AdminSeatController::class, 'preview'])
            ->middleware('permission:seats.view')->name('rooms.layout.preview');
        Route::get('/', [StaffWorkspaceController::class, 'dashboard'])
            ->middleware('permission:dashboard.view')->name('dashboard');
        Route::get('/sales', [StaffWorkspaceController::class, 'sales'])
            ->middleware('permission:counter_sales.view')->name('sales.index');
        Route::get('/print-queue', [StaffWorkspaceController::class, 'prints'])
            ->middleware('permission:tickets.print')->name('prints.index');
        Route::get('/tickets', [StaffTicketWorkspaceController::class, 'index'])
            ->middleware('permission:tickets.lookup')->name('tickets.index');
        Route::post('/tickets/resolve', [StaffTicketWorkspaceController::class, 'resolve'])
            ->middleware(['permission:tickets.lookup', 'throttle:30,1'])->name('tickets.resolve');
        Route::get('/tickets/{booking}/operations', [StaffTicketWorkspaceController::class, 'operations'])
            ->whereNumber('booking')->middleware('permission:tickets.lookup')->name('tickets.operations');
        Route::post('/tickets/{booking}/print-all', StaffPrintAllController::class)
            ->whereNumber('booking')->middleware(['permission:tickets.print', 'throttle:6,1'])->name('tickets.print-all');
        Route::post('/tickets/{booking}/print', [StaffTicketPrintController::class, 'start'])
            ->whereNumber('booking')->middleware(['permission:tickets.print', 'throttle:12,1'])->name('tickets.print.start');
        Route::post('/tickets/{booking}/reprint', [StaffTicketPrintController::class, 'reprint'])
            ->whereNumber('booking')->middleware(['permission:tickets.print', 'throttle:12,1'])->name('tickets.print.reprint');
        Route::get('/tickets/{booking}/print', [StaffTicketPrintController::class, 'show'])
            ->whereNumber('booking')->middleware('permission:tickets.print')->name('tickets.print.show');
        Route::post('/tickets/{booking}/print/succeed', [StaffTicketPrintController::class, 'succeed'])
            ->whereNumber('booking')->middleware(['permission:tickets.print', 'throttle:12,1'])->name('tickets.print.succeed');
        Route::post('/tickets/{booking}/print/fail', [StaffTicketPrintController::class, 'fail'])
            ->whereNumber('booking')->middleware(['permission:tickets.print', 'throttle:12,1'])->name('tickets.print.fail');
        Route::post('/tickets/{booking}/print/recover-expired', [StaffTicketPrintController::class, 'recoverExpired'])
            ->whereNumber('booking')->middleware(['permission:tickets.print', 'throttle:12,1'])->name('tickets.print.recover-expired');
        Route::post('/admission-tickets/{admissionTicket}/print', [StaffTicketPrintController::class, 'startTicket'])
            ->whereNumber('admissionTicket')->middleware(['permission:tickets.print', 'throttle:12,1'])->name('admission-tickets.print.start');
        Route::post('/admission-tickets/{admissionTicket}/reprint', [StaffTicketPrintController::class, 'reprintTicket'])
            ->whereNumber('admissionTicket')->middleware(['permission:tickets.print', 'throttle:12,1'])->name('admission-tickets.print.reprint');
        Route::post('/admission-tickets/{admissionTicket}/incident-reprint/{resolution}', [StaffTicketPrintController::class, 'incidentReprintTicket'])
            ->whereNumber('admissionTicket')->whereNumber('resolution')
            ->middleware(['permission:tickets.print', 'throttle:12,1'])->name('admission-tickets.print.incident-reprint');
        Route::get('/admission-tickets/{admissionTicket}/print', [StaffTicketPrintController::class, 'showTicket'])
            ->whereNumber('admissionTicket')->middleware('permission:tickets.print')->name('admission-tickets.print.show');
        Route::post('/admission-tickets/{admissionTicket}/print/succeed', [StaffTicketPrintController::class, 'succeedTicket'])
            ->whereNumber('admissionTicket')->middleware(['permission:tickets.print', 'throttle:12,1'])->name('admission-tickets.print.succeed');
        Route::post('/admission-tickets/{admissionTicket}/print/fail', [StaffTicketPrintController::class, 'failTicket'])
            ->whereNumber('admissionTicket')->middleware(['permission:tickets.print', 'throttle:12,1'])->name('admission-tickets.print.fail');
        Route::post('/admission-tickets/{admissionTicket}/print/recover-expired', [StaffTicketPrintController::class, 'recoverExpiredTicket'])
            ->whereNumber('admissionTicket')->middleware(['permission:tickets.print', 'throttle:12,1'])->name('admission-tickets.print.recover-expired');
        Route::post('/food-pickup-vouchers/{foodPickupVoucher}/print', StaffFoodPickupVoucherPrintController::class)
            ->whereNumber('foodPickupVoucher')->middleware(['permission:tickets.print', 'throttle:12,1'])->name('food-pickup-vouchers.print');
        Route::get('/counter', [StaffCounterSaleController::class, 'index'])
            ->middleware('permission:counter_sales.view')->name('counter.index');
        Route::post('/counter/cinema', [StaffCounterSaleController::class, 'selectCinema'])
            ->middleware('permission:counter_sales.view')->name('counter.cinema');
        Route::get('/counter/showtimes/{showtime}/seats', [StaffCounterSaleController::class, 'seats'])
            ->whereNumber('showtime')->middleware('permission:counter_sales.create')->name('counter.seats');
        Route::post('/counter/showtimes/{showtime}/hold', [StaffCounterSaleController::class, 'hold'])
            ->whereNumber('showtime')->middleware(['permission:counter_sales.create', 'throttle:20,1'])->name('counter.hold');
        Route::get('/counter/bookings/{booking}/food', [StaffCounterSaleController::class, 'food'])
            ->whereNumber('booking')->middleware('permission:counter_sales.create')->name('counter.food');
        Route::post('/counter/bookings/{booking}/food', [StaffCounterSaleController::class, 'updateFood'])
            ->whereNumber('booking')->middleware('permission:counter_sales.create')->name('counter.food.update');
        Route::get('/counter/bookings/{booking}/review', [StaffCounterSaleController::class, 'review'])
            ->whereNumber('booking')->middleware('permission:counter_sales.view')->name('counter.review');
        Route::post('/counter/bookings/{booking}/promotion', [StaffCounterSaleController::class, 'applyPromotion'])
            ->whereNumber('booking')->middleware(['permission:counter_sales.settle', 'throttle:12,1'])->name('counter.promotion');
        Route::post('/counter/bookings/{booking}/cash', [StaffCounterSaleController::class, 'cash'])
            ->whereNumber('booking')->middleware(['permission:counter_sales.settle', 'throttle:12,1'])->name('counter.cash');
        Route::post('/counter/bookings/{booking}/payments/{provider}', [StaffCounterPaymentController::class, 'initiate'])
            ->whereNumber('booking')->whereIn('provider', ['vnpay', 'payos'])
            ->middleware(['permission:counter_sales.settle', 'throttle:12,1'])->name('counter.payments.initiate');
        Route::post('/counter/bookings/{booking}/payment/resume', [StaffCounterPaymentController::class, 'resume'])
            ->whereNumber('booking')->middleware(['permission:counter_sales.settle', 'throttle:12,1'])->name('counter.payment.resume');
        Route::post('/counter/bookings/{booking}/payment/reconcile', [StaffCounterPaymentController::class, 'reconcile'])
            ->whereNumber('booking')->middleware(['permission:counter_sales.settle', 'throttle:12,1'])->name('counter.payment.reconcile');
        Route::post('/counter/bookings/{booking}/payment/cancel-payos-attempt', [StaffCounterPaymentController::class, 'cancelPayOsAttempt'])
            ->whereNumber('booking')->middleware(['permission:counter_sales.settle', 'throttle:12,1'])->name('counter.payment.cancel-payos-attempt');
        Route::get('/counter/bookings/{booking}/payment-result', [StaffCounterPaymentController::class, 'result'])
            ->whereNumber('booking')->middleware('permission:counter_sales.view')->name('counter.payment-result');
        Route::post('/counter/bookings/{booking}/cancel', [StaffCounterSaleController::class, 'cancel'])
            ->whereNumber('booking')->middleware(['permission:counter_sales.cancel', 'throttle:12,1'])->name('counter.cancel');
    });
