<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CueSports Africa</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: #ffffff; margin: 0; font-size: 28px;">CueSports Africa</h1>
        <p style="color: #a0a0a0; margin: 10px 0 0 0;">Professional Pool Management</p>
    </div>

    <div style="background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;">
        @if($userName)
        <p style="font-size: 16px;">Hello <strong>{{ $userName }}</strong>,</p>
        @else
        <p style="font-size: 16px;">Hello,</p>
        @endif

        @switch($type)
            @case(\App\Enums\OtpType::EMAIL_VERIFICATION)
                <p>Thank you for registering with CueSports Africa. Please use the code below to verify your email address:</p>
                @break
            @case(\App\Enums\OtpType::PASSWORD_RESET)
                <p>You requested to reset your password. Use the code below to proceed:</p>
                @break
            @case(\App\Enums\OtpType::LOGIN)
                <p>Use the code below to complete your login:</p>
                @break
            @default
                <p>Your verification code is:</p>
        @endswitch

        <div style="background: #f8f9fa; border: 2px dashed #1a1a2e; border-radius: 10px; padding: 25px; text-align: center; margin: 25px 0;">
            <span style="font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #1a1a2e;">{{ $code }}</span>
        </div>

        <p style="color: #666; font-size: 14px;">
            This code will expire in <strong>{{ $type->expiryMinutes() }} minutes</strong>.
        </p>

        <p style="color: #999; font-size: 13px; margin-top: 30px;">
            If you didn't request this code, please ignore this email or contact support if you have concerns.
        </p>
    </div>

    <div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">
        <p>&copy; {{ date('Y') }} CueSports Africa. All rights reserved.</p>
        <p>Making pool a professional sport across Africa.</p>
    </div>
</body>
</html>
