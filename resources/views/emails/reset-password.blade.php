<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 10px;
            border: 1px solid #ddd;
        }
        h1 {
            color: #2c3e50;
            margin-top: 0;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #3498db;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background-color: #2980b9;
        }
        .token-info {
            background-color: #fff;
            padding: 15px;
            border-left: 4px solid #3498db;
            margin: 20px 0;
            word-break: break-all;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset Your Password</h1>
        <p>Hello,</p>
        <p>We received a request to reset the password for your account associated with <strong>{{ $email }}</strong>.</p>
        <p>Click the button below to reset your password:</p>
        
        <p style="text-align: center;">
            <a href="{{ $resetUrl }}" class="button">Reset Password</a>
        </p>
        
        <p>Or copy and paste the following URL into your browser:</p>
        <div class="token-info">
            {{ $resetUrl }}
        </div>
        
        <p><strong>This link will expire in 60 minutes.</strong></p>
        
        <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
        
        <div class="footer">
            <p>This is an automated message from {{ $appName }}. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

