<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-migration', function () {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    return 'Migration and Config Clear Success!';
});

Route::view('/privacy-policy', 'privacy')->name('privacy');

// Default password reset route name used by Laravel password broker.
// Redirect users to Ionic reset page with token and email query params.
Route::get('/password/reset/{token}', function (string $token) {
    $clientUrl = rtrim(env('APP_CLIENT_URL', 'http://localhost:55459'), '/');
    $email = request()->query('email');
    return redirect($clientUrl . '/#/reset-password?token=' . urlencode($token) . '&email=' . urlencode((string) $email));
})->name('password.reset');

Route::prefix('admin')->group(function () {
    // Admin auth
    Route::get('/login', [\App\Http\Controllers\AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [\App\Http\Controllers\AdminAuthController::class, 'login'])->name('admin.login.post');
    Route::get('/password/forgot', [\App\Http\Controllers\AdminAuthController::class, 'showForgot'])->name('admin.password.request');
    Route::post('/password/email', [\App\Http\Controllers\AdminAuthController::class, 'sendResetLink'])->name('admin.password.email');
    Route::get('/password/reset/{token}', [\App\Http\Controllers\AdminAuthController::class, 'showResetForm'])->name('admin.password.reset');
    Route::post('/password/reset', [\App\Http\Controllers\AdminAuthController::class, 'resetPassword'])->name('admin.password.update');
    Route::post('/logout', [\App\Http\Controllers\AdminAuthController::class, 'logout'])->name('admin.logout');

    // Protect admin routes with a simple admin-role check middleware
    Route::middleware(['web', \App\Http\Middleware\EnsureAdmin::class])->group(function () {
    Route::get('/', [\App\Http\Controllers\AdminController::class, 'dashboard'])->name('admin.dashboard');

    // Vehicles
    Route::get('/vehicles', [\App\Http\Controllers\AdminController::class, 'vehicles'])->name('admin.vehicles');
    Route::get('/vehicles/create', [\App\Http\Controllers\AdminController::class, 'createVehicle'])->name('admin.vehicles.create');
    Route::post('/vehicles', [\App\Http\Controllers\AdminController::class, 'storeVehicle'])->name('admin.vehicles.store');
    Route::get('/vehicles/{vehicle}/edit', [\App\Http\Controllers\AdminController::class, 'editVehicle'])->name('admin.vehicles.edit');
    Route::put('/vehicles/{vehicle}', [\App\Http\Controllers\AdminController::class, 'updateVehicle'])->name('admin.vehicles.update');
    Route::delete('/vehicles/{vehicle}', [\App\Http\Controllers\AdminController::class, 'deleteVehicle'])->name('admin.vehicles.delete');

    // Schedules
    Route::get('/schedules', [\App\Http\Controllers\AdminController::class, 'schedules'])->name('admin.schedules');
    Route::get('/schedules/create', [\App\Http\Controllers\AdminController::class, 'createSchedule'])->name('admin.schedules.create');
    Route::post('/schedules', [\App\Http\Controllers\AdminController::class, 'storeSchedule'])->name('admin.schedules.store');
    Route::get('/schedules/{schedule}/edit', [\App\Http\Controllers\AdminController::class, 'editSchedule'])->name('admin.schedules.edit');
    Route::put('/schedules/{schedule}', [\App\Http\Controllers\AdminController::class, 'updateSchedule'])->name('admin.schedules.update');
    Route::delete('/schedules/{schedule}', [\App\Http\Controllers\AdminController::class, 'deleteSchedule'])->name('admin.schedules.delete');

    // Drivers/Users
    Route::get('/users', [\App\Http\Controllers\AdminController::class, 'users'])->name('admin.users');
    Route::get('/users/create', [\App\Http\Controllers\AdminController::class, 'createUser'])->name('admin.users.create');
    Route::post('/users', [\App\Http\Controllers\AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::get('/users/{user}/edit', [\App\Http\Controllers\AdminController::class, 'editUser'])->name('admin.users.edit');
    Route::put('/users/{user}', [\App\Http\Controllers\AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/users/{user}', [\App\Http\Controllers\AdminController::class, 'deleteUser'])->name('admin.users.delete');

    // Bookings Monitoring & Verification
    Route::get('/bookings', [\App\Http\Controllers\AdminController::class, 'bookings'])->name('admin.bookings');
    Route::get('/verifications', [\App\Http\Controllers\AdminController::class, 'verifications'])->name('admin.bookings.verifications');
    Route::post('/bookings/{booking}/confirm', [\App\Http\Controllers\AdminController::class, 'confirmBookingPayment'])->name('admin.bookings.confirm');
    Route::post('/bookings/{booking}/reject', [\App\Http\Controllers\AdminController::class, 'rejectBookingPayment'])->name('admin.bookings.reject');

    // Route Templates (Auto-Generation)
    Route::get('/route-templates', [\App\Http\Controllers\RouteTemplateController::class, 'index'])->name('admin.route-templates.index');
    Route::post('/route-templates', [\App\Http\Controllers\RouteTemplateController::class, 'store'])->name('admin.route-templates.store');
    Route::post('/route-templates/{routeTemplate}/toggle', [\App\Http\Controllers\RouteTemplateController::class, 'update'])->name('admin.route-templates.toggle');
    Route::delete('/route-templates/{routeTemplate}', [\App\Http\Controllers\RouteTemplateController::class, 'destroy'])->name('admin.route-templates.destroy');
    Route::post('/route-templates/generate', [\App\Http\Controllers\RouteTemplateController::class, 'generate'])->name('admin.route-templates.generate');

    // Trips Monitoring
    Route::get('/trips', [\App\Http\Controllers\AdminController::class, 'trips'])->name('admin.trips');
    Route::get('/active-trips-locations', [\App\Http\Controllers\AdminController::class, 'activeTripsLocations'])->name('admin.trips.locations');
    });
});
