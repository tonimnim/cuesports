<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private OtpService $otpService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            // Create user
            $user = User::create([
                'phone_number' => $validated['phone_number'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'country_id' => $validated['country_id'],
                'is_player' => true,
            ]);

            // Create player profile
            PlayerProfile::create([
                'user_id' => $user->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'nickname' => $validated['nickname'] ?? null,
                'date_of_birth' => $validated['date_of_birth'],
                'gender' => $validated['gender'],
                'geographic_unit_id' => $validated['geographic_unit_id'],
                'national_id_number' => $validated['national_id_number'] ?? null,
            ]);

            return $user;
        });

        // Send OTP for email verification
        $this->otpService->sendViaEmail(
            $user->email,
            OtpType::EMAIL_VERIFICATION,
            $validated['first_name']
        );

        // Load relationships
        $user->load(['country', 'playerProfile.geographicUnit']);

        return response()->json([
            'message' => 'Registration successful. Please verify your email.',
            'user' => new UserResource($user),
        ], 201);
    }

    public function verifyEmail(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $verified = $this->otpService->verify(
            $validated['email'],
            $validated['code'],
            OtpType::EMAIL_VERIFICATION
        );

        if (!$verified) {
            return response()->json([
                'message' => 'Invalid or expired verification code',
            ], 422);
        }

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            $user->email_verified_at = now();
            $user->save();

            // Load relationships and create token
            $user->load(['country', 'playerProfile.geographicUnit']);
            $token = $user->createToken('auth_token');

            return response()->json([
                'message' => 'Email verified successfully',
                'user' => new UserResource($user),
                'token' => [
                    'access_token' => $token->accessToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $token->token->expires_at,
                ],
            ]);
        }

        return response()->json([
            'message' => 'Email verified successfully',
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email is already verified',
            ], 422);
        }

        $this->otpService->sendViaEmail(
            $user->email,
            OtpType::EMAIL_VERIFICATION,
            $user->playerProfile?->first_name
        );

        return response()->json([
            'message' => 'Verification code sent',
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('phone_number', $validated['phone_number'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated',
            ], 403);
        }

        if (!$user->email_verified_at) {
            // Send new OTP
            $this->otpService->sendViaEmail(
                $user->email,
                OtpType::EMAIL_VERIFICATION,
                $user->playerProfile?->first_name
            );

            return response()->json([
                'message' => 'Please verify your email first. A new verification code has been sent.',
                'requires_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        // Revoke existing tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth_token');

        // Load relationships
        $user->load(['country', 'playerProfile.geographicUnit', 'organizerProfile']);

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user),
            'token' => [
                'access_token' => $token->accessToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->token->expires_at,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['country', 'playerProfile.geographicUnit', 'organizerProfile']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke current token
        $user->token()->revoke();

        // Create new token
        $token = $user->createToken('auth_token');

        return response()->json([
            'token' => [
                'access_token' => $token->accessToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->token->expires_at,
            ],
        ]);
    }
}
