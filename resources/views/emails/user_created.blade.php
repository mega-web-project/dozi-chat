<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dozi Chat Account Created</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f6fa;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #2f3640;
            margin: 0;
        }
        .content {
            line-height: 1.6;
            color: #2f3640;
        }
        .button {
            display: inline-block;
            margin: 20px 0;
            padding: 12px 24px;
            background-color: #1e90ff;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #888888;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Welcome to Dozi Chat!</h1>
        </div>

        <div class="content">
            <p>Hello <strong>{{ $name }}</strong>,</p>

            <p>An account has been created for you at <strong>Dozi Chat</strong>.</p>

            <p>Your registered email is: <strong>{{ $email }}</strong></p>

            <p>To activate your account, please open the app and request your <strong>OTP</strong>. Once you receive it, verify your email to log in.</p>

            <p style="text-align:center;">
                <a href="{{ config('app.url') }}" class="button">Open Dozi Chat</a>
            </p>

            <p>Thanks,<br>
            <strong>The Dozi Chat Team</strong></p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Dozi Chat. All rights reserved.
        </div>
    </div>
</body>
</html>
