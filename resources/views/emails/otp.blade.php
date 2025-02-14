<!DOCTYPE html>
<html>
<head>
    <title>Password Reset OTP</title>
</head>
<body>
    <p>Dear User,</p>
    <p>Your OTP for password reset is: <strong>{{ $otp }}</strong></p>
    <p>This OTP will expire in 15 minutes.</p>
    <p>Thank you,</p>
    <p>{{ config('app.name') }} Team</p>
</body>
</html>
