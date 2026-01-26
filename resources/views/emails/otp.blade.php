<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dozi-Chat OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background-color:#f7f7f7; padding:20px;">
    <div style="max-width:600px; margin:auto; background:white; padding:30px; border-radius:8px; text-align:center;">
        <h2 style="color:#333;">Hello, {{ $userName }}!</h2>
        <p style="color:#555;">Your One-Time Password (OTP) to verify your email for Dozi-Chat is:</p>
        <h1 style="font-size:36px; margin:20px 0; color:#007BFF;">{{ $otp }}</h1>
        <p style="color:#555;">This OTP is valid for 10 minutes.</p>
        <p style="color:#999; font-size:12px;">If you didn’t request this, please ignore this email.</p>
    </div>
</body>
</html>
