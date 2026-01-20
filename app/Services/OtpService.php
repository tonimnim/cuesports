<?php

namespace App\Services;

use App\Enums\OtpType;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;

class OtpService
{
    public function generate(string $identifier, OtpType $type): OtpCode
    {
        // Invalidate any existing OTPs for this identifier and type
        OtpCode::forIdentifier($identifier)
            ->ofType($type)
            ->valid()
            ->delete();

        // Generate new 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return OtpCode::create([
            'identifier' => $identifier,
            'code' => $code,
            'type' => $type,
            'expires_at' => now()->addMinutes($type->expiryMinutes()),
        ]);
    }

    public function verify(string $identifier, string $code, OtpType $type): bool
    {
        $otp = OtpCode::forIdentifier($identifier)
            ->ofType($type)
            ->valid()
            ->where('code', $code)
            ->first();

        if (!$otp) {
            return false;
        }

        $otp->markAsVerified();
        return true;
    }

    public function sendViaEmail(string $email, OtpType $type, ?string $userName = null): OtpCode
    {
        $otp = $this->generate($email, $type);

        Mail::to($email)->queue(new OtpMail($otp->code, $type, $userName));

        return $otp;
    }

    public function cleanup(): int
    {
        return OtpCode::where('expires_at', '<', now()->subDay())->delete();
    }
}
