<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Instructor Account Credentials</title>
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
        .credentials-box {
            background-color: #fff;
            padding: 20px;
            border-left: 4px solid #3498db;
            margin: 20px 0;
            border-radius: 5px;
        }
        .credential-item {
            margin: 15px 0;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 3px;
        }
        .credential-label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }
        .credential-value {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #2c3e50;
            word-break: break-all;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
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
        <h1>Welcome to {{ $appName }}!</h1>
        <p>Hello {{ $instructorName }},</p>
        <p>Your instructor account has been created by <strong>{{ $trainingCenterName }}</strong>.</p>
        <p>You can now log in to the system using the following credentials:</p>
        
        <div class="credentials-box">
            <div class="credential-item">
                <span class="credential-label">Email:</span>
                <span class="credential-value">{{ $email }}</span>
            </div>
            <div class="credential-item">
                <span class="credential-label">Password:</span>
                <span class="credential-value">{{ $password }}</span>
            </div>
        </div>
        
        <div class="warning">
            <strong>⚠️ Important:</strong> Please change your password after your first login for security purposes.
        </div>
        
        <p style="text-align: center;">
            <a href="{{ $loginUrl }}" class="button">Login to Your Account</a>
        </p>
        
        <p>Or copy and paste the following URL into your browser:</p>
        <div class="credentials-box">
            {{ $loginUrl }}
        </div>
        
        <p>If you have any questions or need assistance, please contact your training center or our support team.</p>
        
        <div class="footer">
            <p>This is an automated message from {{ $appName }}. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

