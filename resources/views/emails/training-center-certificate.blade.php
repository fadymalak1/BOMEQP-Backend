<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Center Authorization Certificate</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 5px 5px;
        }
        .message {
            margin-bottom: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Congratulations!</h1>
    </div>
    <div class="content">
        <p>Dear {{ $trainingCenterName }},</p>
        
        <div class="message">
            <p>We are pleased to inform you that your training center has been successfully authorized by <strong>{{ $accName }}</strong>.</p>
            
            <p>Your authorization certificate has been attached to this email. This certificate confirms that your training center is now authorized to operate under {{ $accName }}.</p>
            
            <p>We congratulate you on this achievement and look forward to a successful partnership.</p>
        </div>
        
        <p>Best regards,<br>
        <strong>{{ $appName }}</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated email. Please do not reply to this message.</p>
    </div>
</body>
</html>

