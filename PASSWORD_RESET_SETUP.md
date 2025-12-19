# Password Reset Setup Guide

## Overview
The password reset functionality has been implemented. Users can request a password reset link via email and use it to reset their password.

## Implementation Details

### Files Created/Modified

1. **app/Mail/ResetPasswordMail.php** - Mail class for sending password reset emails
2. **resources/views/emails/reset-password.blade.php** - Email template for password reset
3. **app/Http/Controllers/API/AuthController.php** - Updated `forgotPassword()` and `resetPassword()` methods

### API Endpoints

#### Forgot Password
**POST** `/auth/forgot-password`

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

**Response (200):**
```json
{
  "message": "Password reset link sent to your email"
}
```

**Response (404):**
```json
{
  "message": "We could not find a user with that email address."
}
```

#### Reset Password
**POST** `/auth/reset-password`

**Request Body:**
```json
{
  "token": "reset_token_from_email",
  "email": "user@example.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response (200):**
```json
{
  "message": "Password reset successfully"
}
```

**Response (400):**
```json
{
  "message": "Invalid or expired reset token"
}
```

## Configuration

### Environment Variables

Your `.env` file already has mail configuration:

```env
MAIL_MAILER=smtp
MAIL_HOST=aeroenix.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@aeroenix.com
MAIL_PASSWORD=S*.@}8yti5p{
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="no-reply@aeroenix.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Optional: Frontend URL

To customize the reset password link URL, add this to your `.env`:

```env
FRONTEND_URL=https://your-frontend-domain.com
```

If not set, the system will construct the URL from `APP_URL` by removing `/api` and `/v1` segments.

**Example:**
- `APP_URL=https://aeroenix.com/v1/api`
- Generated reset URL: `https://aeroenix.com/reset-password?token=...&email=...`

### Clear Config Cache

After updating `.env`, clear the config cache:

```bash
php artisan config:clear
php artisan cache:clear
```

## Email Template

The password reset email includes:
- A clickable reset button
- A direct URL link
- Instructions and security notice
- 60-minute expiration notice

## Security Features

1. **Token Expiration**: Reset tokens expire after 60 minutes
2. **One-Time Use**: Tokens are deleted after successful password reset
3. **Hashed Tokens**: Tokens are hashed before storage in the database
4. **Email Validation**: Email must exist in the users table

## Troubleshooting

### Email Not Sending

1. **Check Mail Configuration**
   - Verify SMTP credentials are correct
   - Test SMTP connection: Port 465 with SSL or Port 587 with TLS
   - Ensure firewall allows outbound SMTP connections

2. **Check Server Logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```
   Look for mail-related errors

3. **Test Mail Configuration**
   Create a test route to send a test email:
   ```php
   Route::get('/test-email', function () {
       try {
           Mail::raw('Test email', function ($message) {
               $message->to('your-email@example.com')
                       ->subject('Test Email');
           });
           return 'Email sent successfully';
       } catch (\Exception $e) {
           return 'Error: ' . $e->getMessage();
       }
   });
   ```

4. **Check SPAM Folder**
   - Emails might be going to spam
   - Check email server reputation

5. **Verify Email Account**
   - Ensure `no-reply@aeroenix.com` account exists and is active
   - Verify password is correct (check for special characters that might need escaping)

### Common Issues

#### Issue: "Unable to send password reset email"
- **Cause**: SMTP server connection failed
- **Solution**: Check mail server configuration and credentials

#### Issue: Reset link doesn't work
- **Cause**: Token expired or invalid
- **Solution**: Request a new reset link (tokens expire after 60 minutes)

#### Issue: Email received but URL is incorrect
- **Cause**: `FRONTEND_URL` not set or `APP_URL` incorrect
- **Solution**: Set `FRONTEND_URL` in `.env` or fix `APP_URL`

### Testing Mail in Development

For local development, you can use mail log driver:

```env
MAIL_MAILER=log
```

Emails will be logged to `storage/logs/laravel.log` instead of being sent.

## Database

The password reset tokens are stored in the `password_reset_tokens` table:

```sql
CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255),
    created_at TIMESTAMP
);
```

Tokens are automatically cleaned up:
- After successful password reset
- After expiration (60 minutes)

## Next Steps

1. **Test the functionality**:
   - Request password reset with a valid email
   - Check email inbox (and spam folder)
   - Click reset link or copy token
   - Reset password using the API endpoint

2. **Configure Frontend**:
   - Set up a reset password page
   - Extract token and email from URL parameters
   - Call the reset password API endpoint

3. **Monitor**:
   - Check logs for any mail errors
   - Monitor email delivery rates
   - Verify tokens are being deleted after use

