<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());
        return response()->json([
            'message' => 'Registration successful',
            'user' => $user
        ], 201);
    }

    /**
     * Login user and return token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $deviceName = $request->input('device_name', null);

        $result = $this->authService->login($credentials, $deviceName);

        if (!$result) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'message' => 'Login successful',
            'user' => $result['user'],
            'token' => $result['token']
        ]);
    }

    /**
     * Logout current device
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Logout from all devices
     */
    public function logoutAllDevices(Request $request): JsonResponse
    {
        $this->authService->logoutFromAllDevices($request->user());
        return response()->json(['message' => 'Logged out from all devices']);
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->sendPasswordResetLink($request->email);
        return response()->json(['message' => __($status)]);
    }

    /**
     * Reset password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->resetPassword($request->validated());
        return response()->json(['message' => __($status)]);
    }

    /**
     * Update authenticated user password
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $success = $this->authService->updatePassword(
            $user,
            $request->current_password,
            $request->new_password
        );

        if (!$success) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        return response()->json(['message' => 'Password updated successfully']);
    }

    /**
     * Verify email manually (via link or token)
     */
    public function verifyEmail(User $user): JsonResponse
    {
        $this->authService->verifyEmail($user);
        return response()->json(['message' => 'Email verified successfully']);
    }

    /**
     * Social login / registration
     */
    public function socialLogin(Request $request, string $provider): JsonResponse
    {
        $token = $request->input('token', null);
        $deviceName = $request->input('device_name', null);

        $result = $this->authService->socialLogin($provider, $token, $deviceName);

        return response()->json([
            'message' => 'Social login successful',
            'user' => $result['user'],
            'token' => $result['token']
        ]);
    }

    /**
     * Enable MFA (TOTP)
     */
    public function enableMFA(Request $request): JsonResponse
    {
        $result = $this->authService->enableMFA($request->user());
        return response()->json([
            'message' => 'MFA enabled successfully',
            'secret' => $result['secret']
        ]);
    }

    /**
     * Revoke specific token (logout from specific device)
     */
    public function revokeToken(Request $request, string $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->firstOrFail();
        $this->authService->revokeToken($token);

        return response()->json(['message' => 'Token revoked successfully']);
    }
}
