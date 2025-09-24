<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Company\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * AuthController
 *
 * Handles register/login/logout/me flows.
 */
class AuthController extends Controller
{
    /**
     * Register a user and create company (owner).
     *
     * - Creates company using provided company_name
     * - Creates user, assigns company_id & owner role
     * - Issues a Sanctum token and returns user resource with token in meta
     */
    public function register(RegisterRequest $request)
    {

        // create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            // 'company_id' => $company->id,
            // 'current_company_id' => $company->id,
        ]);

        // create company
        $company = Company::create([
            'owner_id' => $user->id, // placeholder, will update after user created
            'name' => $request->company_name,
            'email' => $request->company_email ?? null,
            'city' => $request->company_city ?? null,
            'country_id' => $request->company_country_id ?? null,
            'website' => $request->company_website ?? null,
        ]);

        // set user company
        $user->update(['company_id' => $company->id, 'current_company_id' => $company->id]);

        // assign owner role (spatie)
        $user->assignRole('owner');

        // token
        $token = $user->createToken('api-token')->plainTextToken;

        return (new UserResource($user))->additional(['meta' => ['token' => $token]])->response()->setStatusCode(201);
    }

    /**
     * Login, returns user + token
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        // basic check
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // optional: check active status
        if (!$user->is_active) {
            return response()->json(['message' => 'Account is deactivated. Contact support.'], 403);
        }

        // update last_login
        $user->update([
            'last_login_ip' => request()->ip(),
            'last_login_at' => now(),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return (new UserResource($user))->additional(['meta' => ['token' => $token]]);
    }

    /**
     * Returns current authenticated user
     */
    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    /**
     * Logout the current token
     */
    public function logout(Request $request)
    {
        // delete current token (for mobile/regenerable tokens)
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }
        return response()->json(['message' => 'Logged out.']);
    }
}
