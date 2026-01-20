<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    public function __construct(
        private OtpService $otpService
    ) {}

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        $this->otpService->sendViaEmail(
            $user->email,
            OtpType::PASSWORD_RESET,
            $user->playerProfile?->first_name
        );

        return response()->json([
            'message' => 'Password reset code sent to your email',
        ]);
    }

    public function verifyResetCode(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $otp = \App\Models\OtpCode::forIdentifier($validated['email'])
            ->ofType(OtpType::PASSWORD_RESET)
            ->valid()
            ->where('code', $validated['code'])
            ->first();

        if (!$otp) {
            return response()->json([
                'message' => 'Invalid or expired reset code',
                'valid' => false,
            ], 422);
        }

        return response()->json([
            'message' => 'Code is valid',
            'valid' => true,
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $verified = $this->otpService->verify(
            $validated['email'],
            $validated['code'],
            OtpType::PASSWORD_RESET
        );

        if (!$verified) {
            return response()->json([
                'message' => 'Invalid or expired reset code',
            ], 422);
        }

        $user = User::where('email', $validated['email'])->first();
        $user->password = Hash::make($validated['password']);
        $user->save();

        // Revoke all existing tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successful. Please login with your new password.',
        ]);
    }
}
