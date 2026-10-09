<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HandoverController;
use App\Http\Controllers\Admin\KnowledgeItemController;
use App\Http\Controllers\Admin\SpaceInventoryController;
use App\Http\Controllers\Admin\SpaceTypeController;
use App\Http\Controllers\Admin\VenueSettingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CoordinatorChatController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\VenuePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VenuePageController::class, 'index'])->name('home');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('space-types', SpaceTypeController::class)->except('show');
        Route::get('space-types/{spaceType}/inventory', [SpaceInventoryController::class, 'index'])->name('space-types.inventory.index');
        Route::post('space-types/{spaceType}/inventory', [SpaceInventoryController::class, 'store'])->name('space-types.inventory.store');
        Route::resource('knowledge-items', KnowledgeItemController::class)->except('show');

        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');

        Route::get('settings', [VenueSettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [VenueSettingController::class, 'update'])->name('settings.update');

        Route::get('handovers', [HandoverController::class, 'index'])->name('handovers.index');
        Route::get('handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');
        Route::post('handovers/{handover}/reply', [HandoverController::class, 'reply'])->name('handovers.reply');
        Route::post('handovers/{handover}/resolve', [HandoverController::class, 'resolve'])->name('handovers.resolve');
    });
});

Route::prefix('{venueSlug}')->group(function () {
    Route::get('/', [VenuePageController::class, 'show'])->name('venue.show');
    Route::get('spaces', [VenuePageController::class, 'spaces'])->name('venue.spaces');
    Route::get('spaces/{spaceSlug}', [VenuePageController::class, 'space'])->name('venue.space');
    Route::get('facilities', [VenuePageController::class, 'facilities'])->name('venue.facilities');
    Route::get('facilities/{facilityId}', [VenuePageController::class, 'facility'])->whereNumber('facilityId')->name('venue.facility');
    Route::get('info', [VenuePageController::class, 'info'])->name('venue.info');
    Route::get('staff', [VenuePageController::class, 'staff'])->name('venue.staff');
    Route::get('reservation', [VenuePageController::class, 'reservationScene'])->name('venue.reservation');

    Route::prefix('reservation')->name('reservation.')->middleware('throttle:20,1')->group(function () {
        Route::get('availability', [ReservationController::class, 'availability'])->name('availability');
        Route::post('quote', [ReservationController::class, 'quote'])->name('quote');
        Route::post('/', [ReservationController::class, 'store'])->name('store');
    });

    Route::prefix('coordinator')->name('coordinator.')->group(function () {
        Route::post('start', [CoordinatorChatController::class, 'start'])->middleware('throttle:coordinator-start')->name('start');
        Route::post('message', [CoordinatorChatController::class, 'message'])->middleware('throttle:coordinator-message')->name('message');
        Route::get('history', [CoordinatorChatController::class, 'history'])->middleware('throttle:coordinator-history')->name('history');
    });
});
