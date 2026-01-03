# ACC Address Fields API - Documentation

## Overview

The ACC Dashboard and Profile API now includes separate mailing address and physical address fields. ACC admins can manage both addresses independently through the dashboard and profile endpoints.

## What Changed?

### Before
- ACC had a single `address` field
- No distinction between mailing and physical addresses
- Limited address information

### After
- **Mailing Address**: Separate fields for street, city, country, and postal code
- **Physical Address**: Separate fields for street, city, country, and postal code
- Both addresses available in Dashboard and Profile endpoints
- Can be updated independently

## API Endpoints

### 1. Get ACC Dashboard

**Endpoint**: `GET /api/acc/dashboard`

**Description**: Get ACC dashboard data including mailing and physical addresses.

**Authentication**: Required (ACC Admin)

**Response (200 OK)**:
```json
{
    "pending_requests": 2,
    "active_training_centers": 1,
    "active_instructors": 9,
    "certificates_generated": 0,
    "revenue": {
        "monthly": 46700.00,
        "total": 46700.00
    },
    "mailing_address": {
        "street": "123 Main Street",
        "city": "Cairo",
        "country": "Egypt",
        "postal_code": "12345"
    },
    "physical_address": {
        "street": "456 Business Avenue",
        "city": "Cairo",
        "country": "Egypt",
        "postal_code": "67890"
    }
}
```

### 2. Get ACC Profile

**Endpoint**: `GET /api/acc/profile`

**Description**: Get ACC profile information including mailing and physical addresses.

**Authentication**: Required (ACC Admin)

**Response (200 OK)**:
```json
{
    "profile": {
        "id": 1,
        "name": "ABC Accreditation Body",
        "legal_name": "ABC Accreditation Body LLC",
        "registration_number": "REG123456",
        "email": "info@example.com",
        "phone": "+1234567890",
        "country": "Egypt",
        "address": "123 Main St",
        "mailing_address": {
            "street": "123 Main Street",
            "city": "Cairo",
            "country": "Egypt",
            "postal_code": "12345"
        },
        "physical_address": {
            "street": "456 Business Avenue",
            "city": "Cairo",
            "country": "Egypt",
            "postal_code": "67890"
        },
        "website": "https://example.com",
        "logo_url": "https://example.com/logo.png",
        "status": "active",
        "commission_percentage": 10.00,
        "stripe_account_id": "acct_xxxxxxxxxxxxx",
        "stripe_account_configured": true,
        "documents": [...],
        "user": {...},
        "created_at": "2024-01-01T08:00:00.000000Z",
        "updated_at": "2024-01-15T10:00:00.000000Z"
    }
}
```

### 3. Update ACC Profile (with Address Fields)

**Endpoint**: `PUT /api/acc/profile`

**Description**: Update ACC profile information including mailing and physical addresses.

**Authentication**: Required (ACC Admin)

**Content-Type**: `application/json` or `multipart/form-data`

**Request Body (JSON)**:
```json
{
    "mailing_street": "123 Main Street",
    "mailing_city": "Cairo",
    "mailing_country": "Egypt",
    "mailing_postal_code": "12345",
    "physical_street": "456 Business Avenue",
    "physical_city": "Cairo",
    "physical_country": "Egypt",
    "physical_postal_code": "67890"
}
```

**Request Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `mailing_street` | string | No | Mailing address street name |
| `mailing_city` | string | No | Mailing address city name |
| `mailing_country` | string | No | Mailing address country |
| `mailing_postal_code` | string | No | Mailing address postal code |
| `physical_street` | string | No | Physical address street name |
| `physical_city` | string | No | Physical address city name |
| `physical_country` | string | No | Physical address country |
| `physical_postal_code` | string | No | Physical address postal code |

**Validation Rules**:
- All address fields are optional (nullable)
- `mailing_street`: max 255 characters
- `mailing_city`: max 255 characters
- `mailing_country`: max 255 characters
- `mailing_postal_code`: max 20 characters
- `physical_street`: max 255 characters
- `physical_city`: max 255 characters
- `physical_country`: max 255 characters
- `physical_postal_code`: max 20 characters

**Response (200 OK)**:
```json
{
    "message": "Profile updated successfully",
    "profile": {
        "id": 1,
        "name": "ABC Accreditation Body",
        "legal_name": "ABC Accreditation Body LLC",
        "registration_number": "REG123456",
        "email": "info@example.com",
        "phone": "+1234567890",
        "country": "Egypt",
        "address": "123 Main St",
        "mailing_address": {
            "street": "123 Main Street",
            "city": "Cairo",
            "country": "Egypt",
            "postal_code": "12345"
        },
        "physical_address": {
            "street": "456 Business Avenue",
            "city": "Cairo",
            "country": "Egypt",
            "postal_code": "67890"
        },
        "website": "https://example.com",
        "logo_url": "https://example.com/logo.png",
        "status": "active",
        "commission_percentage": 10.00,
        "stripe_account_id": "acct_xxxxxxxxxxxxx",
        "stripe_account_configured": true,
        "documents": [...],
        "user": {...},
        "created_at": "2024-01-01T08:00:00.000000Z",
        "updated_at": "2024-01-20T10:00:00.000000Z"
    }
}
```

## Address Fields Structure

### Mailing Address

| Field | Type | Description |
|-------|------|-------------|
| `street` | string (nullable) | Street name and number |
| `city` | string (nullable) | City name |
| `country` | string (nullable) | Country name |
| `postal_code` | string (nullable) | Postal/ZIP code |

### Physical Address

| Field | Type | Description |
|-------|------|-------------|
| `street` | string (nullable) | Street name and number |
| `city` | string (nullable) | City name |
| `country` | string (nullable) | Country name |
| `postal_code` | string (nullable) | Postal/ZIP code |

## Use Cases

### 1. Update Mailing Address Only

```json
PUT /api/acc/profile
{
    "mailing_street": "789 Mail Street",
    "mailing_city": "Alexandria",
    "mailing_country": "Egypt",
    "mailing_postal_code": "54321"
}
```

### 2. Update Physical Address Only

```json
PUT /api/acc/profile
{
    "physical_street": "321 Office Road",
    "physical_city": "Giza",
    "physical_country": "Egypt",
    "physical_postal_code": "98765"
}
```

### 3. Update Both Addresses

```json
PUT /api/acc/profile
{
    "mailing_street": "123 Main Street",
    "mailing_city": "Cairo",
    "mailing_country": "Egypt",
    "mailing_postal_code": "12345",
    "physical_street": "456 Business Avenue",
    "physical_city": "Cairo",
    "physical_country": "Egypt",
    "physical_postal_code": "67890"
}
```

### 4. Clear Address Fields

Set fields to `null` or empty string to clear:

```json
PUT /api/acc/profile
{
    "mailing_street": null,
    "mailing_city": null,
    "mailing_country": null,
    "mailing_postal_code": null
}
```

### 5. Partial Update

Update only specific fields:

```json
PUT /api/acc/profile
{
    "mailing_postal_code": "99999",
    "physical_city": "New City"
}
```

## Response Format

### Dashboard Response

Addresses are returned as nested objects:

```json
{
    "mailing_address": {
        "street": "123 Main Street",
        "city": "Cairo",
        "country": "Egypt",
        "postal_code": "12345"
    },
    "physical_address": {
        "street": "456 Business Avenue",
        "city": "Cairo",
        "country": "Egypt",
        "postal_code": "67890"
    }
}
```

### Profile Response

Addresses are returned as nested objects within the profile:

```json
{
    "profile": {
        "id": 1,
        "name": "ABC Accreditation Body",
        "mailing_address": {
            "street": "123 Main Street",
            "city": "Cairo",
            "country": "Egypt",
            "postal_code": "12345"
        },
        "physical_address": {
            "street": "456 Business Avenue",
            "city": "Cairo",
            "country": "Egypt",
            "postal_code": "67890"
        }
    }
}
```

## Field Details

### Mailing Address Fields

**mailing_street**
- Type: String (nullable)
- Max Length: 255 characters
- Description: Street name and number for mailing address
- Example: `"123 Main Street"`

**mailing_city**
- Type: String (nullable)
- Max Length: 255 characters
- Description: City name for mailing address
- Example: `"Cairo"`

**mailing_country**
- Type: String (nullable)
- Max Length: 255 characters
- Description: Country name for mailing address
- Example: `"Egypt"`

**mailing_postal_code**
- Type: String (nullable)
- Max Length: 20 characters
- Description: Postal/ZIP code for mailing address
- Example: `"12345"`

### Physical Address Fields

**physical_street**
- Type: String (nullable)
- Max Length: 255 characters
- Description: Street name and number for physical address
- Example: `"456 Business Avenue"`

**physical_city**
- Type: String (nullable)
- Max Length: 255 characters
- Description: City name for physical address
- Example: `"Cairo"`

**physical_country**
- Type: String (nullable)
- Max Length: 255 characters
- Description: Country name for physical address
- Example: `"Egypt"`

**physical_postal_code**
- Type: String (nullable)
- Max Length: 20 characters
- Description: Postal/ZIP code for physical address
- Example: `"67890"`

## Validation Rules Summary

### All Address Fields
- Optional (nullable)
- Can be updated independently
- Can be set to `null` or empty string to clear
- Max length validation applies

### Field-Specific Rules
- Street fields: max 255 characters
- City fields: max 255 characters
- Country fields: max 255 characters
- Postal code fields: max 20 characters

## Error Responses

### 422 Validation Error

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "mailing_street": [
            "The mailing street must not be greater than 255 characters."
        ],
        "mailing_postal_code": [
            "The mailing postal code must not be greater than 20 characters."
        ]
    }
}
```

## Notes

1. **Optional Fields**: All address fields are optional and can be omitted during updates.

2. **Null Values**: All address fields can be set to `null` or empty string to clear existing values.

3. **Independent Updates**: Mailing and physical addresses can be updated independently. You can update one without affecting the other.

4. **Partial Updates**: You can update individual fields within an address without providing all fields.

5. **Backward Compatibility**: The existing `address` field remains unchanged and continues to work as before.

6. **Dashboard Access**: Addresses are available in the dashboard endpoint for quick access.

7. **Profile Access**: Addresses are included in the profile endpoint along with all other profile information.

8. **Transaction Safety**: Address updates are included in the transaction rollback mechanism, ensuring data consistency.

9. **Response Format**: Addresses are returned as nested objects for better organization and readability.

10. **Database Storage**: Address fields are stored as separate columns in the `accs` table.

## Summary

✅ **Mailing Address** - Separate fields for street, city, country, postal code  
✅ **Physical Address** - Separate fields for street, city, country, postal code  
✅ **Dashboard Integration** - Addresses available in dashboard endpoint  
✅ **Profile Integration** - Addresses available in profile endpoint  
✅ **Update Support** - Can be updated independently or together  
✅ **Optional Fields** - All fields are optional and nullable  
✅ **Transaction Safety** - Updates are transaction-protected  

The ACC Dashboard and Profile API now provide complete address management functionality with separate mailing and physical addresses.



