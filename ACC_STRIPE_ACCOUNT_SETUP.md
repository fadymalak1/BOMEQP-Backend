# ACC Stripe Account Setup - Frontend Developer Guide

## Overview

ACCs (Accreditation Bodies) can now manage their Stripe Connect account ID through their profile settings. This enables automatic payment splitting using Stripe Destination Charges.

## API Endpoints

### 1. Get ACC Profile

**Endpoint**: `GET /api/acc/profile`

**Headers**:
```
Authorization: Bearer {token}
```

**Response (200)**:
```json
{
    "profile": {
        "id": 1,
        "name": "ABC Accreditation Body",
        "legal_name": "ABC Accreditation Body LLC",
        "registration_number": "ACC-001",
        "email": "info@abc.com",
        "phone": "+1234567890",
        "country": "Egypt",
        "address": "123 Main St",
        "website": "https://example.com",
        "logo_url": "https://example.com/logo.png",
        "status": "active",
        "commission_percentage": 10.00,
        "stripe_account_id": "acct_xxxxxxxxxxxxx",
        "stripe_account_configured": true,
        "user": {
            "id": 1,
            "name": "ABC Accreditation Body",
            "email": "info@abc.com",
            "role": "acc_admin",
            "status": "active"
        },
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    }
}
```

### 2. Update ACC Profile (Including Stripe Account ID)

**Endpoint**: `PUT /api/acc/profile`

**Headers**:
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body** (all fields optional):
```json
{
    "name": "ABC Accreditation Body",
    "legal_name": "ABC Accreditation Body LLC",
    "phone": "+1234567890",
    "country": "Egypt",
    "address": "123 Main St",
    "website": "https://example.com",
    "logo_url": "https://example.com/logo.png",
    "stripe_account_id": "acct_xxxxxxxxxxxxx"
}
```

**Response (200)**:
```json
{
    "message": "Profile updated successfully",
    "profile": {
        "id": 1,
        "name": "ABC Accreditation Body",
        "legal_name": "ABC Accreditation Body LLC",
        "registration_number": "ACC-001",
        "email": "info@abc.com",
        "phone": "+1234567890",
        "country": "Egypt",
        "address": "123 Main St",
        "website": "https://example.com",
        "logo_url": "https://example.com/logo.png",
        "status": "active",
        "commission_percentage": 10.00,
        "stripe_account_id": "acct_xxxxxxxxxxxxx",
        "stripe_account_configured": true,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T12:00:00.000000Z"
    }
}
```

**Error Response (422)**:
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "stripe_account_id": [
            "The Stripe account ID must start with \"acct_\" and be a valid Stripe account ID."
        ]
    }
}
```

## Stripe Account ID Format

- **Format**: Must start with `acct_` followed by alphanumeric characters
- **Example**: `acct_1A2B3C4D5E6F7G8H9I0J`
- **Length**: Typically 24 characters total
- **Validation**: Automatically validated by the API

## Frontend Implementation

### React Component Example

```jsx
import { useState, useEffect } from 'react';

function ACCProfileSettings() {
    const [profile, setProfile] = useState(null);
    const [loading, setLoading] = useState(true);
    const [stripeAccountId, setStripeAccountId] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        fetchProfile();
    }, []);

    const fetchProfile = async () => {
        try {
            const response = await fetch('/api/acc/profile', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                },
            });
            const data = await response.json();
            setProfile(data.profile);
            setStripeAccountId(data.profile.stripe_account_id || '');
            setLoading(false);
        } catch (error) {
            console.error('Error fetching profile:', error);
        }
    };

    const handleSaveStripeAccount = async () => {
        setSaving(true);
        try {
            const response = await fetch('/api/acc/profile', {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    stripe_account_id: stripeAccountId || null,
                }),
            });

            const data = await response.json();
            
            if (response.ok) {
                setProfile(data.profile);
                alert('Stripe account ID updated successfully!');
            } else {
                alert('Error updating Stripe account ID');
            }
        } catch (error) {
            console.error('Error updating profile:', error);
            alert('Error updating Stripe account ID');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div>Loading...</div>;

    return (
        <div className="profile-settings">
            <h2>Stripe Account Settings</h2>
            
            <div className="form-group">
                <label htmlFor="stripe_account_id">
                    Stripe Connect Account ID
                </label>
                <input
                    type="text"
                    id="stripe_account_id"
                    value={stripeAccountId}
                    onChange={(e) => setStripeAccountId(e.target.value)}
                    placeholder="acct_xxxxxxxxxxxxx"
                    pattern="acct_[a-zA-Z0-9]+"
                />
                <small>
                    Your Stripe Connect account ID (starts with "acct_")
                </small>
            </div>

            <div className="status-info">
                <p>
                    Status: {profile.stripe_account_configured ? (
                        <span className="success">✓ Configured</span>
                    ) : (
                        <span className="warning">⚠ Not Configured</span>
                    )}
                </p>
                {profile.stripe_account_configured && (
                    <p className="info">
                        Payments will be automatically split between your account and the platform.
                    </p>
                )}
            </div>

            <button 
                onClick={handleSaveStripeAccount}
                disabled={saving}
            >
                {saving ? 'Saving...' : 'Save Stripe Account ID'}
            </button>
        </div>
    );
}
```

### Vue Component Example

```vue
<template>
    <div class="profile-settings">
        <h2>Stripe Account Settings</h2>
        
        <div class="form-group">
            <label for="stripe_account_id">
                Stripe Connect Account ID
            </label>
            <input
                id="stripe_account_id"
                v-model="stripeAccountId"
                type="text"
                placeholder="acct_xxxxxxxxxxxxx"
                pattern="acct_[a-zA-Z0-9]+"
            />
            <small>
                Your Stripe Connect account ID (starts with "acct_")
            </small>
        </div>

        <div class="status-info">
            <p>
                Status: 
                <span v-if="profile.stripe_account_configured" class="success">
                    ✓ Configured
                </span>
                <span v-else class="warning">
                    ⚠ Not Configured
                </span>
            </p>
            <p v-if="profile.stripe_account_configured" class="info">
                Payments will be automatically split between your account and the platform.
            </p>
        </div>

        <button @click="saveStripeAccount" :disabled="saving">
            {{ saving ? 'Saving...' : 'Save Stripe Account ID' }}
        </button>
    </div>
</template>

<script>
export default {
    data() {
        return {
            profile: null,
            stripeAccountId: '',
            saving: false,
            loading: true
        };
    },
    mounted() {
        this.fetchProfile();
    },
    methods: {
        async fetchProfile() {
            try {
                const response = await fetch('/api/acc/profile', {
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                    },
                });
                const data = await response.json();
                this.profile = data.profile;
                this.stripeAccountId = data.profile.stripe_account_id || '';
                this.loading = false;
            } catch (error) {
                console.error('Error fetching profile:', error);
            }
        },
        async saveStripeAccount() {
            this.saving = true;
            try {
                const response = await fetch('/api/acc/profile', {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        stripe_account_id: this.stripeAccountId || null,
                    }),
                });

                const data = await response.json();
                
                if (response.ok) {
                    this.profile = data.profile;
                    alert('Stripe account ID updated successfully!');
                } else {
                    alert('Error updating Stripe account ID');
                }
            } catch (error) {
                console.error('Error updating profile:', error);
                alert('Error updating Stripe account ID');
            } finally {
                this.saving = false;
            }
        }
    }
}
</script>
```

### Vanilla JavaScript Example

```javascript
// Fetch ACC Profile
async function fetchACCProfile() {
    const response = await fetch('/api/acc/profile', {
        headers: {
            'Authorization': `Bearer ${token}`,
        },
    });
    return await response.json();
}

// Update Stripe Account ID
async function updateStripeAccountId(stripeAccountId) {
    const response = await fetch('/api/acc/profile', {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            stripe_account_id: stripeAccountId || null,
        }),
    });
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Failed to update Stripe account ID');
    }
    
    return await response.json();
}

// Usage
const profile = await fetchACCProfile();
console.log('Stripe Account Configured:', profile.profile.stripe_account_configured);

// Update Stripe account ID
try {
    const result = await updateStripeAccountId('acct_xxxxxxxxxxxxx');
    console.log('Updated:', result.profile.stripe_account_id);
} catch (error) {
    console.error('Error:', error.message);
}
```

## UI/UX Recommendations

### 1. Stripe Account Setup Section

Create a dedicated section in the ACC profile/settings page:

```
┌─────────────────────────────────────────┐
│  Stripe Payment Settings               │
├─────────────────────────────────────────┤
│                                         │
│  Stripe Connect Account ID:            │
│  [acct_xxxxxxxxxxxxx          ]        │
│                                         │
│  Status: ✓ Configured                  │
│                                         │
│  ℹ️ When configured, payments will be   │
│     automatically split between your    │
│     account and the platform.           │
│                                         │
│  [Save Changes]                         │
└─────────────────────────────────────────┘
```

### 2. Status Indicators

- **Configured**: Show green checkmark ✓
- **Not Configured**: Show warning icon ⚠
- **Help Text**: Explain benefits of configuring Stripe account

### 3. Validation Feedback

- Show real-time validation as user types
- Display error message if format is incorrect
- Highlight input field in red if invalid

### 4. Success Message

After successful update:
- Show success notification
- Update status indicator
- Display confirmation message

## How to Get Stripe Account ID

### Step 1: Create Stripe Connect Account

1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to **Connect** → **Accounts**
3. Click **Create Account** or use Stripe Connect onboarding

### Step 2: Complete Onboarding

1. Fill in business information
2. Provide bank account details
3. Complete verification process

### Step 3: Get Account ID

1. Go to **Connect** → **Accounts**
2. Click on your account
3. Copy the **Account ID** (starts with `acct_`)

### Step 4: Add to Profile

1. Go to ACC Profile Settings
2. Paste the Account ID in the Stripe Account ID field
3. Click **Save**

## Benefits of Configuring Stripe Account

✅ **Automatic Payment Splitting**: Money goes directly to ACC account  
✅ **Real-time Transfers**: Funds available immediately  
✅ **No Manual Processing**: Stripe handles everything automatically  
✅ **Transparent**: Clear commission breakdown in transactions  
✅ **Secure**: Stripe handles all PCI compliance  

## Important Notes

1. **Format Validation**: Account ID must start with `acct_`
2. **Can be Removed**: Set to `null` or empty string to remove
3. **Immediate Effect**: Changes take effect on next payment
4. **Fallback**: If not configured, uses standard payment flow

## Error Handling

### Invalid Format

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "stripe_account_id": [
            "The Stripe account ID must start with \"acct_\" and be a valid Stripe account ID."
        ]
    }
}
```

**Frontend Handling**:
```javascript
if (response.status === 422) {
    const errors = await response.json();
    if (errors.errors.stripe_account_id) {
        // Show validation error to user
        showError(errors.errors.stripe_account_id[0]);
    }
}
```

## Testing

### Test Cases

1. **Get Profile**: Verify `stripe_account_configured` field
2. **Update with Valid ID**: Should succeed
3. **Update with Invalid Format**: Should return 422 error
4. **Remove Account ID**: Set to `null` or empty string
5. **Update Other Fields**: Should not affect Stripe account ID

## Summary

- **Endpoint**: `PUT /api/acc/profile`
- **Field**: `stripe_account_id`
- **Format**: Must start with `acct_`
- **Optional**: Can be `null` or empty
- **Effect**: Enables automatic payment splitting
- **Status**: Check `stripe_account_configured` field in profile response

ACCs can now easily manage their Stripe Connect account ID through their profile settings, enabling automatic payment splitting for all transactions!

