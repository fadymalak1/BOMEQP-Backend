<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-ACC Instructor Achievement Certificate</title>
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
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            margin: 0 0 8px 0;
            font-size: 24px;
        }
        .header p {
            margin: 0;
            opacity: 0.85;
            font-size: 14px;
        }
        .badge {
            display: inline-block;
            background: #ffd700;
            color: #1a237e;
            font-weight: bold;
            padding: 6px 16px;
            border-radius: 20px;
            margin-top: 12px;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            border: 1px solid #e0e0e0;
            border-top: none;
        }
        .message {
            margin-bottom: 24px;
        }
        .acc-list {
            background-color: #e8eaf6;
            border-left: 4px solid #3f51b5;
            padding: 14px 18px;
            margin: 16px 0;
            border-radius: 0 6px 6px 0;
        }
        .acc-list p {
            margin: 0 0 8px 0;
            font-weight: bold;
            color: #283593;
        }
        .acc-list ol {
            margin: 0;
            padding-left: 22px;
        }
        .acc-list ol li {
            font-size: 15px;
            font-weight: bold;
            color: #283593;
            margin: 5px 0;
        }
        .highlight-box {
            background-color: #fff8e1;
            border: 1px solid #ffca28;
            border-radius: 6px;
            padding: 14px 16px;
            margin: 16px 0;
            font-size: 14px;
            color: #5d4037;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #888;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏆 Outstanding Achievement!</h1>
        <p>Multi-Accreditation Body Recognition</p>
        <span class="badge">★ Certified by 3+ Accreditation Bodies ★</span>
    </div>
    <div class="content">
        <p>Dear <strong>{{ $instructorName }}</strong>,</p>

        <div class="message">
            <p>We are proud to inform you that you have achieved a distinguished milestone — you have been authorized by
            <strong>{{ count($accNames) }} Accreditation Bodies (ACCs)</strong> affiliated with <strong>{{ $appName }}</strong>.</p>

            <div class="acc-list">
                <p>Authorizing Accreditation Bodies:</p>
                <ol>
                    @foreach ($accNames as $name)
                        <li>{{ $name }}</li>
                    @endforeach
                </ol>
            </div>

            <div class="highlight-box">
                🎓 This multi-accreditation recognition is a testament to your expertise, dedication, and commitment to excellence
                in training and education. Very few instructors achieve this distinction.
            </div>

            <p>Your achievement certificate has been attached to this email as a PDF. We encourage you to share this
            accomplishment with your network.</p>

            <p>Congratulations on this remarkable achievement!</p>
        </div>

        <p>Best regards,<br>
        <strong>{{ $appName }}</strong></p>
    </div>

    <div class="footer">
        <p>This is an automated email. Please do not reply to this message.</p>
    </div>
</body>
</html>
