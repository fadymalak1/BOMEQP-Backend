<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Connect Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #007bff;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 24px;
        }
        .content {
            margin-bottom: 30px;
        }
        .content p {
            margin-bottom: 15px;
            font-size: 16px;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            padding: 14px 30px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 14px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning p {
            margin: 5px 0;
            font-size: 14px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>{{ $appName }}</h1>
        </div>

        <div class="content">
            <p>Dear {{ $accountName }},</p>

            <p>We are pleased to inform you that your {{ $accountType }} account has been set up for Stripe Connect integration. To complete the setup and start receiving payments, please complete the onboarding process.</p>

            <div class="info-box">
                <p><strong>What is Stripe Connect?</strong></p>
                <p>Stripe Connect allows you to receive payments directly to your bank account. Once you complete the onboarding process, all payments will be automatically transferred to your account.</p>
            </div>

            <div class="button-container">
                <a href="{{ $onboardingUrl }}" class="button">Complete Stripe Setup</a>
            </div>

            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #007bff; font-size: 14px;">{{ $onboardingUrl }}</p>

            <div class="warning">
                <p><strong>Important:</strong></p>
                <p>• This link is valid for 24 hours</p>
                <p>• You will need to provide some business and banking information</p>
                <p>• The process usually takes 5-10 minutes</p>
                <p>• If you need assistance, please contact our support team</p>
            </div>

            <p>If you have any questions or need assistance during the setup process, please don't hesitate to contact our support team.</p>

            <p>Best regards,<br>
            <strong>{{ $appName }} Team</strong></p>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

