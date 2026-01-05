# Code Purchase API - FormData Content-Type Header Fix

## Problem

The code purchase request was sending FormData but with the wrong Content-Type header:
- **Data**: FormData (with file uploads like `payment_receipt`)
- **Content-Type**: `application/x-www-form-urlencoded` ❌

This is **incorrect**. When sending FormData (especially with file uploads), the Content-Type must be `multipart/form-data` with a boundary, or it should be **unset** and let the browser automatically set it.

## Root Cause

When manually setting the `Content-Type` header to `application/x-www-form-urlencoded` while using FormData, the browser cannot properly encode the file data, causing the backend to fail to receive the file upload.

## Solution

**DO NOT manually set the Content-Type header when using FormData.** Let the browser automatically set it to `multipart/form-data` with the correct boundary.

## Frontend Code Fix

### ❌ WRONG - Manual Content-Type Setting

```javascript
const formData = new FormData();
formData.append('acc_id', accId);
formData.append('course_id', courseId);
formData.append('quantity', quantity);
formData.append('payment_method', 'manual_payment');
formData.append('payment_receipt', receiptFile);
formData.append('payment_amount', amount);

axios.post('/api/training-center/codes/purchase', formData, {
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded', // ❌ WRONG!
    'Authorization': `Bearer ${token}`
  }
});
```

### ✅ CORRECT - Let Browser Set Content-Type

```javascript
const formData = new FormData();
formData.append('acc_id', accId);
formData.append('course_id', courseId);
formData.append('quantity', quantity);
formData.append('payment_method', 'manual_payment');
formData.append('payment_receipt', receiptFile);
formData.append('payment_amount', amount);

axios.post('/api/training-center/codes/purchase', formData, {
  headers: {
    'Authorization': `Bearer ${token}`
    // ✅ DO NOT set Content-Type - let browser set it automatically
  }
});
```

### ✅ CORRECT - Using Fetch API

```javascript
const formData = new FormData();
formData.append('acc_id', accId);
formData.append('course_id', courseId);
formData.append('quantity', quantity);
formData.append('payment_method', 'manual_payment');
formData.append('payment_receipt', receiptFile);
formData.append('payment_amount', amount);

fetch('/api/training-center/codes/purchase', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
    // ✅ DO NOT set Content-Type for FormData
  },
  body: formData
});
```

## Important Notes

1. **When using FormData with files**, you MUST let the browser set the Content-Type header automatically
2. **Never manually set** `Content-Type` to:
   - `application/x-www-form-urlencoded` ❌
   - `application/json` ❌
   - `multipart/form-data` ❌ (without boundary)
3. **The browser will automatically set** `Content-Type: multipart/form-data; boundary=----WebKitFormBoundary...` ✅
4. **For JSON requests** (without file uploads), you can manually set `Content-Type: application/json`

## Backend Validation

The backend now validates the Content-Type header and will return a clear error message if the wrong Content-Type is detected:

```json
{
  "message": "Invalid Content-Type header. When uploading payment_receipt, you must use multipart/form-data. Do NOT manually set Content-Type header when using FormData - let the browser set it automatically.",
  "error": "invalid_content_type",
  "received_content_type": "application/x-www-form-urlencoded",
  "expected_content_type": "multipart/form-data",
  "hint": "Remove the Content-Type header from your request when using FormData. The browser will automatically set it to multipart/form-data with the correct boundary."
}
```

## API Endpoint Details

**Endpoint:** `POST /api/training-center/codes/purchase`

**Content Types Supported:**
- `multipart/form-data` - Required when uploading `payment_receipt` file
- `application/json` - Can be used for credit_card and wallet payments (no file uploads)

**Required Fields (for manual_payment):**
- `acc_id` (integer)
- `course_id` (integer)
- `quantity` (integer)
- `payment_method` (string: "manual_payment")
- `payment_receipt` (file: PDF, JPG, JPEG, PNG, max 10MB) - **Requires multipart/form-data**
- `payment_amount` (number)

**Required Fields (for credit_card):**
- `acc_id` (integer)
- `course_id` (integer)
- `quantity` (integer)
- `payment_method` (string: "credit_card")
- `payment_intent_id` (string)

## Testing Checklist

- [ ] Remove manual Content-Type header setting when using FormData
- [ ] Verify file uploads work correctly for manual payment
- [ ] Test with different file types (PDF, JPG, PNG)
- [ ] Test with files under 10MB
- [ ] Verify error handling for files over 10MB
- [ ] Test credit_card payment method (can use JSON)
- [ ] Test wallet payment method (can use JSON)
- [ ] Verify Authorization header is still included

## Common Mistakes to Avoid

1. ❌ Setting `Content-Type: application/x-www-form-urlencoded` with FormData
2. ❌ Setting `Content-Type: multipart/form-data` manually (missing boundary)
3. ❌ Setting `Content-Type: application/json` when uploading files
4. ✅ Letting the browser automatically set Content-Type for FormData
5. ✅ Only setting `Authorization` header manually

## Support

If you encounter issues with file uploads, check:
1. The Content-Type header is NOT manually set
2. The file is being appended to FormData correctly
3. The file size is under 10MB
4. The file type is one of: PDF, JPG, JPEG, PNG

