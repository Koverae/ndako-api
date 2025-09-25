<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\CompanyInvitationController;
use App\Http\Controllers\Auth\CompanyController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes are versioned under /api/v1
|
*/

Route::prefix('api/v1')->group(function () {

    /**
     * Public Auth Routes
     * ------------------
     * No authentication required.
     */

    Route::prefix('auth')->group(function () {
        // User Registration
        Route::post('/register', [AuthController::class, 'register'])
            ->name('auth.register');

        // Login (returns Sanctum token)
        Route::post('/login', [AuthController::class, 'login'])
            ->name('auth.login');

        // Forgot Password (send reset link)
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->name('auth.password.forgot');

        // Reset Password (via token)
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->name('auth.password.reset');

        // Social Login (Google, Facebook, GitHub, etc.)
        Route::post('/social/{provider}', [AuthController::class, 'socialLogin'])
            ->name('auth.social.login');

        // Email Verification (via signed URL)
        Route::get('/verify-email/{user}', [AuthController::class, 'verifyEmail'])
            ->middleware('signed')
            ->name('auth.verify-email');
    });

    /**
     * Protected Auth Routes
     * ---------------------
     * Require valid Sanctum token.
     */
    Route::middleware(['auth:sanctum'])->prefix('auth')->group(function () {
        // Logout current device
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('auth.logout');

        // Logout from all devices
        Route::post('/logout-all', [AuthController::class, 'logoutAllDevices'])
            ->name('auth.logout-all');

        // Update password (requires current password)
        Route::post('/update-password', [AuthController::class, 'updatePassword'])
            ->name('auth.password.update');

        // Enable MFA (returns secret to configure authenticator app)
        Route::post('/mfa/enable', [AuthController::class, 'enableMFA'])
            ->name('auth.mfa.enable');

        // Revoke specific token (device-based logout)
        Route::delete('/tokens/{tokenId}', [AuthController::class, 'revokeToken'])
            ->name('auth.tokens.revoke');
    });

    /**
     * Example: Protected Route for Verified Users Only
     * -----------------------------------------------
     * Add this as a blueprint for future domain routes.
     */
    Route::middleware(['auth:sanctum', 'verified'])->get('/me', function (Request $request) {
        return $request->user();
    })->name('user.profile');

});
