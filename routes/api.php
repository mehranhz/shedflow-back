<?php

use App\Http\Controllers\v1\AccountInitiationController;
use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\BookingController;
use App\Http\Controllers\v1\BookingPaymentReceiptController;
use App\Http\Controllers\v1\CustomerAuthController;
use App\Http\Controllers\v1\CustomerBookingController;
use App\Http\Controllers\v1\CustomerController;
use App\Http\Controllers\v1\CustomerProfileController;
use App\Http\Controllers\v1\CustomerSessionController;
use App\Http\Controllers\v1\EventTypeController;
use App\Http\Controllers\v1\InvitationController;
use App\Http\Controllers\v1\MeBookingController;
use App\Http\Controllers\v1\OrganizationController;
use App\Http\Controllers\v1\ProfileController;
use App\Http\Controllers\v1\PublicBookingController;
use App\Http\Controllers\v1\PublicSchedulingController;
use App\Http\Controllers\v1\ScheduleController;
use App\Http\Middleware\AuthenticateJwt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Nest-compatible unversioned auth paths: /api/auth/*
Route::prefix('/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'loginWithPassword']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/request-otp', [AuthController::class, 'loginWithOTP']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOTPForUsername']);

    Route::middleware(AuthenticateJwt::class)->group(function () {
        Route::get('/me', [ProfileController::class, 'getProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('/v1')->group(function () {
    Route::prefix('/auth')->group(function () {
        Route::post('/login', [AuthController::class, 'loginWithPassword']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/request-otp', [AuthController::class, 'loginWithOTP']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOTPForUsername']);
        Route::middleware(AuthenticateJwt::class)->group(function () {
            Route::get('/me', [ProfileController::class, 'getProfile']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::prefix('/customer/auth')->group(function () {
        Route::post('/register', [CustomerAuthController::class, 'register'])
            ->middleware('throttle:customer-auth-login');
        Route::post('/login', [CustomerAuthController::class, 'login'])
            ->middleware('throttle:customer-auth-login');
        Route::post('/claim', [CustomerAuthController::class, 'claim'])
            ->middleware('throttle:customer-auth-claim');
        Route::post('/claim/confirm', [CustomerAuthController::class, 'confirmClaim']);
    });

    Route::prefix('/public')->group(function () {
        Route::get('/orgs/{orgSlug}', [PublicSchedulingController::class, 'showOrganization']);
        Route::get('/orgs/{orgSlug}/event-types', [PublicSchedulingController::class, 'listEventTypes']);
        Route::get('/orgs/{orgSlug}/event-types/{eventSlug}', [PublicSchedulingController::class, 'showEventType']);
        Route::get('/orgs/{orgSlug}/event-types/{eventSlug}/slots', [PublicSchedulingController::class, 'listSlots']);
        Route::post('/bookings', [PublicBookingController::class, 'store']);
        Route::get('/bookings/{uid}', [PublicBookingController::class, 'show']);
        Route::post('/bookings/{uid}/cancel', [PublicBookingController::class, 'cancel']);
        Route::post('/bookings/{uid}/reschedule', [PublicBookingController::class, 'reschedule']);
        Route::post('/bookings/{uid}/payment-receipt', [PublicBookingController::class, 'storePaymentReceipt']);
    });

    Route::get(
        '/organizations/{organization}/bookings/{booking}/payment-receipt/{receipt}/download',
        [BookingPaymentReceiptController::class, 'download'],
    )->middleware('signed')->name('v1.bookings.payment-receipt.download');

    Route::middleware([AuthenticateJwt::class, 'customer'])->prefix('/customer')->group(function () {
        Route::get('/me', [CustomerSessionController::class, 'me']);
        Route::post('/businesses/{organizationId}/switch', [CustomerSessionController::class, 'switchBusiness']);

        Route::middleware('customer.org')->group(function () {
            Route::get('/profile', [CustomerProfileController::class, 'show']);
            Route::patch('/profile', [CustomerProfileController::class, 'update']);

            Route::get('/bookings', [CustomerBookingController::class, 'index']);
            Route::get('/bookings/{uid}', [CustomerBookingController::class, 'show']);

            Route::middleware(['idempotency', 'throttle:customer-portal-mutations'])->group(function () {
                Route::post('/bookings', [CustomerBookingController::class, 'store']);
                Route::post('/bookings/{uid}/cancel', [CustomerBookingController::class, 'cancel']);
                Route::post('/bookings/{uid}/reschedule', [CustomerBookingController::class, 'reschedule']);
            });
        });
    });

    Route::middleware(AuthenticateJwt::class)->group(function () {
        Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);
        Route::get('/user', fn (Request $request) => $request->user());
        Route::post('/initializeAccount', AccountInitiationController::class);
        Route::get('/me/bookings', [MeBookingController::class, 'index']);
        Route::post('/me/bookings/{booking}/cancel', [MeBookingController::class, 'cancel']);

        Route::prefix('/organizations')->middleware('staff')->group(function () {
            Route::get('/', [OrganizationController::class, 'index']);
            Route::post('/', [OrganizationController::class, 'store']);
            Route::get('/{organization}', [OrganizationController::class, 'show']);
            Route::patch('/{organization}', [OrganizationController::class, 'update']);
            Route::delete('/{organization}', [OrganizationController::class, 'destroy']);
            Route::post('/{organization}/switch', [OrganizationController::class, 'switchOrg']);
            Route::get('/{organization}/members', [OrganizationController::class, 'members']);
            Route::get('/{organization}/invitations', [InvitationController::class, 'index']);
            Route::post('/{organization}/invitations', [InvitationController::class, 'store']);
            Route::delete('/{organization}/invitations/{invitation}', [InvitationController::class, 'destroy']);

            Route::prefix('/{organization}/schedules')->group(function () {
                Route::get('/', [ScheduleController::class, 'index']);
                Route::post('/', [ScheduleController::class, 'store']);
                Route::post('/default', [ScheduleController::class, 'storeDefault']);
                Route::get('/{schedule}', [ScheduleController::class, 'show']);
                Route::patch('/{schedule}', [ScheduleController::class, 'update']);
                Route::delete('/{schedule}', [ScheduleController::class, 'destroy']);
                Route::put('/{schedule}/rules', [ScheduleController::class, 'replaceRules']);
                Route::put('/{schedule}/overrides/{date}', [ScheduleController::class, 'upsertOverride']);
                Route::delete('/{schedule}/overrides/{date}', [ScheduleController::class, 'destroyOverride']);
            });

            Route::prefix('/{organization}/event-types')->group(function () {
                Route::get('/', [EventTypeController::class, 'index']);
                Route::post('/', [EventTypeController::class, 'store']);
                Route::get('/{eventType}', [EventTypeController::class, 'show']);
                Route::patch('/{eventType}', [EventTypeController::class, 'update']);
                Route::delete('/{eventType}', [EventTypeController::class, 'destroy']);
            });

            Route::prefix('/{organization}/customers')->group(function () {
                Route::get('/', [CustomerController::class, 'index']);
                Route::get('/{customer}', [CustomerController::class, 'show']);
            });

            Route::prefix('/{organization}/bookings')->group(function () {
                Route::get('/', [BookingController::class, 'index']);
                Route::post('/', [BookingController::class, 'store']);
                Route::get('/{booking}', [BookingController::class, 'show']);
                Route::post('/{booking}/cancel', [BookingController::class, 'cancel']);
                Route::post('/{booking}/reschedule', [BookingController::class, 'reschedule']);
                Route::post('/{booking}/confirm', [BookingController::class, 'confirm']);
                Route::post('/{booking}/no-show', [BookingController::class, 'noShow']);
                Route::post('/{booking}/payment-receipt', [BookingPaymentReceiptController::class, 'store']);
                Route::get('/{booking}/payment-receipt', [BookingPaymentReceiptController::class, 'show']);
                Route::post('/{booking}/payment-receipt/approve', [BookingPaymentReceiptController::class, 'approve']);
                Route::post('/{booking}/payment-receipt/reject', [BookingPaymentReceiptController::class, 'reject']);
            });
        });
    });
});
