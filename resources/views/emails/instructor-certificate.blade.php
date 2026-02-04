<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Authorization Certificate</title>
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
            background-color: #2196F3;
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
        .course-name {
            font-size: 18px;
            font-weight: bold;
            color: #2196F3;
            margin: 15px 0;
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
        <p>Dear {{ $instructorName }},</p>
        
        <div class="message">
            <p>We are pleased to inform you that you have been successfully authorized by <strong>{{ $accName }}</strong> to teach the following course:</p>
            
            <div class="course-name">{{ $courseName }}</div>
            
            <p>Your authorization certificate has been attached to this email. This certificate confirms that you are authorized to teach <strong>{{ $courseName }}</strong> with {{ $accName }}.</p>
            
            <p>We congratulate you on this achievement and wish you success in your teaching endeavors.</p>
        </div>
        
        <p>Best regards,<br>
        <strong>{{ $appName }}</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated email. Please do not reply to this message.</p>
    </div>
</body>
</html>

