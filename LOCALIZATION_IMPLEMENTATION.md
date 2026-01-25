# Localization Implementation Documentation

## Overview
The backend notification system has been updated to support multi-language notifications. Users can now select their preferred language, and all notifications sent from the backend will appear in their selected language.

## Supported Languages
- **en** - English (Default)
- **hi** - Hindi
- **zh-CN** - Chinese Simplified

## User Language Preference

### Setting User Language
Users can update their language preference through the existing profile update endpoint.

**Endpoint:** `PUT /auth/profile`

**Request Body:**
```json
{
  "language": "hi"
}
```

**Valid Language Values:**
- `"en"` - English
- `"hi"` - Hindi
- `"zh-CN"` - Chinese Simplified

**Response:**
The user object will be returned with the updated `language` field.

### Getting User Language
The user's language preference is included in the user object returned by:
- `GET /auth/profile` - Get user profile
- `POST /auth/login` - Login response
- Any endpoint that returns user information

**User Object Structure:**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "language": "en",
  ...
}
```

## Notification Behavior

### Automatic Localization
All notifications sent from the backend are automatically localized based on the user's language preference:

1. **Default Behavior:** If a user has not set a language preference, notifications default to English (`en`)

2. **Language Selection:** When a user updates their language preference, all future notifications will be sent in that language

3. **Fallback:** If a translation is missing for a specific notification type in the selected language, the system automatically falls back to English

4. **Real-time Updates:** Language changes take effect immediately for all new notifications

### Notification Types Localized
All notification types are now localized, including but not limited to:
- ACC application notifications
- Training Center application notifications
- Instructor authorization notifications
- Subscription and payment notifications
- Certificate generation notifications
- Status change notifications
- Commission notifications
- Manual payment notifications
- And all other notification types in the system

## Frontend Implementation Guide

### 1. Language Selection UI
**Recommended Implementation:**
- Add a language selector in the user profile/settings page
- Display available languages as:
  - English
  - Hindi (हिंदी)
  - 简体中文 (Chinese Simplified)
- Save the language code (`en`, `hi`, `zh-CN`) when user selects a language

### 2. Updating User Language
**API Call Example:**
When user selects a language, call the profile update endpoint:
```
PUT /auth/profile
Content-Type: application/json
Authorization: Bearer {token}

{
  "language": "hi"
}
```

### 3. Displaying User Language
**Profile/Settings Page:**
- Show the current user's language preference
- Allow users to change their language preference
- Update the UI immediately after language change

### 4. Notification Display
**No Frontend Changes Required:**
- The backend automatically sends notifications in the user's preferred language
- Frontend can continue displaying notifications as received
- Title and message fields will already be in the correct language

### 5. User Registration/Onboarding
**Recommended:**
- During user registration or onboarding, allow users to select their preferred language
- Set the language preference when creating the user account
- If not set during registration, default to English

## Important Notes

### Language Persistence
- Language preference is stored per user in the database
- Each user can have their own language preference
- Language preference persists across sessions

### Notification Format
- Notification structure remains the same
- Only the `title` and `message` fields are localized
- All other notification data (IDs, amounts, dates, etc.) remain unchanged

### Backward Compatibility
- Existing users without a language preference will receive notifications in English
- All existing API endpoints continue to work as before
- No breaking changes to notification structure

## Testing Checklist

### For Frontend Developers:
1. ✅ Test language selection in user profile/settings
2. ✅ Verify language preference is saved correctly
3. ✅ Check that notifications appear in selected language
4. ✅ Test with all three supported languages (en, hi, zh-CN)
5. ✅ Verify fallback to English when translation is missing
6. ✅ Test language change takes effect immediately
7. ✅ Verify language preference persists after logout/login

## Migration Notes

### Database Changes
- A new `language` column has been added to the `users` table
- Default value is `'en'` (English)
- Existing users will have `'en'` as their default language

### No Breaking Changes
- All existing API endpoints continue to work
- Notification structure remains unchanged
- Only the content (title/message) is localized

## Support

### Language Codes Reference
| Code | Language | Display Name |
|------|----------|--------------|
| en | English | English |
| hi | Hindi | हिंदी |
| zh-CN | Chinese Simplified | 简体中文 |

### Adding New Languages
If you need to add support for additional languages in the future, contact the backend team. The system is designed to be extensible, but new language files need to be added to the backend.

## Summary

The localization system is now fully functional. Frontend developers need to:
1. Add a language selector UI component
2. Call the profile update endpoint when user changes language
3. Display the user's current language preference
4. Continue displaying notifications as before (they'll automatically be in the correct language)

No changes are required to how notifications are displayed - the backend handles all translation automatically based on the user's language preference.

