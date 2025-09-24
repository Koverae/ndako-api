<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\CompanyInvitationController;
use App\Http\Controllers\Auth\CompanyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes are versioned under /api/v1
|
*/

Route::prefix('api/v1')->group(function () {

    // Public auth endpoints
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/social/{provider}', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('auth/social/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');

    // Password resets
    Route::post('auth/password/forgot', [PasswordResetController::class, 'sendResetLink']);
    Route::post('auth/password/reset', [PasswordResetController::class, 'reset']);

    // Company invitations
    Route::get('company-invite/{token}', [CompanyInvitationController::class, 'showJoinPage']);
    Route::post('company-invite/{token}/accept', [CompanyInvitationController::class, 'acceptInvitation']);

    // Protected endpoints
    Route::middleware(['auth:sanctum', 'resolve.company'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Company CRUD (only accessible to authorized roles — policies should enforce)
        Route::apiResource('companies', CompanyController::class)->only(['index','store','show','update','destroy']);
    });

});
