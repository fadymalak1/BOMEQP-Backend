# Accreditation Management System - API Documentation

## Base URL
```
http://your-domain.com/api
```

## Authentication
Most endpoints require authentication using Laravel Sanctum. Include the bearer token in the Authorization header:
```
Authorization: Bearer {token}
```

After successful login/registration, you'll receive a token that should be included in all subsequent requests.

---

## Public Endpoints

### 1. User Registration
**POST** `/auth/register`

Register a new user (Training Center or ACC).

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "training_center_admin" // or "acc_admin"
}
```

**Response (201):**
```json
{
  "message": "Registration successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "training_center_admin",
    "status": "pending"
  },
  "token": "1|xxxxxxxxxxxxx"
}
```

---

### 2. User Login
**POST** `/auth/login`

Login with email and password.

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "training_center_admin",
    "status": "active"
  },
  "token": "1|xxxxxxxxxxxxx"
}
```

**Error Response (422):**
```json
{
  "message": "The provided credentials are incorrect."
}
```

---

### 3. Forgot Password
**POST** `/auth/forgot-password`

Request password reset link.

**Request Body:**
```json
{
  "email": "john@example.com"
}
```

**Response (200):**
```json
{
  "message": "Password reset link sent to your email"
}
```

---

### 4. Reset Password
**POST** `/auth/reset-password`

Reset password with token.

**Request Body:**
```json
{
  "token": "reset_token_here",
  "email": "john@example.com",
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

---

### 5. Verify Email
**GET** `/auth/verify-email/{token}`

Verify user email address.

**Response (200):**
```json
{
  "message": "Email verified successfully"
}
```

---

### 6. Verify Certificate (Public)
**GET** `/certificates/verify/{code}`

Verify a certificate using verification code (public endpoint, no auth required).

**URL Parameters:**
- `code` - Certificate verification code

**Response (200):**
```json
{
  "certificate": {
    "certificate_number": "CERT-2024-001",
    "trainee_name": "Jane Smith",
    "course": "Advanced Safety Training",
    "issue_date": "2024-01-15",
    "expiry_date": "2026-01-15",
    "status": "valid",
    "training_center": "ABC Training Center"
  }
}
```

**Error Response (404):**
```json
{
  "message": "Certificate not found"
}
```

---

## Protected Endpoints (Require Authentication)

### Authentication Endpoints

#### 7. Get User Profile
**GET** `/auth/profile`

Get authenticated user's profile.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "training_center_admin",
    "status": "active",
    "last_login": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

#### 8. Update Profile
**PUT** `/auth/profile`

Update authenticated user's profile.

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "name": "John Updated",
  "email": "newemail@example.com"
}
```

**Response (200):**
```json
{
  "message": "Profile updated successfully",
  "user": {
    "id": 1,
    "name": "John Updated",
    "email": "newemail@example.com",
    "role": "training_center_admin",
    "status": "active"
  }
}
```

---

#### 9. Change Password
**PUT** `/auth/change-password`

Change user password.

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "current_password": "oldpassword123",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response (200):**
```json
{
  "message": "Password changed successfully"
}
```

**Error Response (422):**
```json
{
  "message": "Current password is incorrect"
}
```

---

#### 10. Logout
**POST** `/auth/logout`

Logout and invalidate current token.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "message": "Logged out successfully"
}
```

---

## Group Admin Endpoints
*Requires role: `group_admin`*

### ACC Management

#### 11. Get ACC Applications
**GET** `/admin/accs/applications`

Get all pending ACC applications.

**Response (200):**
```json
{
  "applications": [
    {
      "id": 1,
      "name": "ABC Accreditation Body",
      "legal_name": "ABC Accreditation Body LLC",
      "registration_number": "ACC-001",
      "email": "info@abc.com",
      "status": "pending",
      "documents": []
    }
  ]
}
```

---

#### 12. Get ACC Application Details
**GET** `/admin/accs/applications/{id}`

Get details of a specific ACC application.

**Response (200):**
```json
{
  "application": {
    "id": 1,
    "name": "ABC Accreditation Body",
    "legal_name": "ABC Accreditation Body LLC",
    "registration_number": "ACC-001",
    "email": "info@abc.com",
    "status": "pending",
    "documents": [
      {
        "id": 1,
        "document_type": "license",
        "document_url": "/storage/documents/license.pdf",
        "verified": false
      }
    ]
  }
}
```

---

#### 13. Approve ACC Application
**PUT** `/admin/accs/applications/{id}/approve`

Approve an ACC application.

**Response (200):**
```json
{
  "message": "ACC application approved",
  "acc": {
    "id": 1,
    "name": "ABC Accreditation Body",
    "status": "active",
    "approved_at": "2024-01-15T10:30:00.000000Z",
    "approved_by": 1
  }
}
```

---

#### 14. Reject ACC Application
**PUT** `/admin/accs/applications/{id}/reject`

Reject an ACC application.

**Request Body:**
```json
{
  "rejection_reason": "Missing required documents"
}
```

**Response (200):**
```json
{
  "message": "ACC application rejected",
  "acc": {
    "id": 1,
    "status": "rejected"
  }
}
```

---

#### 15. Create ACC Space
**POST** `/admin/accs/{id}/create-space`

Create workspace for approved ACC.

**Response (200):**
```json
{
  "message": "ACC space created successfully"
}
```

---

#### 16. Generate ACC Credentials
**POST** `/admin/accs/{id}/generate-credentials`

Generate login credentials for ACC.

**Response (200):**
```json
{
  "message": "Credentials generated successfully",
  "username": "acc_abc",
  "password": "temporary_password"
}
```

---

#### 17. List All ACCs
**GET** `/admin/accs`

Get all ACCs with their subscriptions.

**Response (200):**
```json
{
  "accs": [
    {
      "id": 1,
      "name": "ABC Accreditation Body",
      "status": "active",
      "subscriptions": []
    }
  ]
}
```

---

#### 18. Get ACC Details
**GET** `/admin/accs/{id}`

Get detailed information about an ACC.

**Response (200):**
```json
{
  "acc": {
    "id": 1,
    "name": "ABC Accreditation Body",
    "email": "info@abc.com",
    "status": "active",
    "subscriptions": [],
    "documents": []
  }
}
```

---

#### 19. Set Commission Percentage
**PUT** `/admin/accs/{id}/commission-percentage`

Set commission percentage for an ACC.

**Request Body:**
```json
{
  "commission_percentage": 15.5
}
```

**Response (200):**
```json
{
  "message": "Commission percentage set successfully"
}
```

---

#### 20. Get ACC Transactions
**GET** `/admin/accs/{id}/transactions`

Get all transactions for an ACC.

**Response (200):**
```json
{
  "transactions": []
}
```

---

### Categories & Courses Management

#### 21. Create Category
**POST** `/admin/categories`

Create a new category.

**Request Body:**
```json
{
  "name": "Safety Training",
  "name_ar": "تدريب السلامة",
  "description": "Safety related courses",
  "icon_url": "/icons/safety.png",
  "status": "active"
}
```

**Response (201):**
```json
{
  "category": {
    "id": 1,
    "name": "Safety Training",
    "name_ar": "تدريب السلامة",
    "status": "active"
  }
}
```

---

#### 22. List Categories
**GET** `/admin/categories`

Get all categories.

**Response (200):**
```json
{
  "categories": []
}
```

---

#### 23. Update Category
**PUT** `/admin/categories/{id}`

Update a category.

**Request Body:**
```json
{
  "name": "Updated Safety Training",
  "status": "active"
}
```

**Response (200):**
```json
{
  "message": "Category updated successfully",
  "category": {}
}
```

---

#### 24. Delete Category
**DELETE** `/admin/categories/{id}`

Delete a category.

**Response (200):**
```json
{
  "message": "Category deleted successfully"
}
```

---

#### 25. Create Sub Category
**POST** `/admin/sub-categories`

Create a new sub-category.

**Request Body:**
```json
{
  "category_id": 1,
  "name": "Fire Safety",
  "name_ar": "سلامة الحريق",
  "description": "Fire safety courses",
  "status": "active"
}
```

**Response (201):**
```json
{
  "sub_category": {
    "id": 1,
    "category_id": 1,
    "name": "Fire Safety",
    "status": "active"
  }
}
```

---

#### 26-29. Sub Category CRUD
Similar endpoints as categories:
- **GET** `/admin/sub-categories` - List all
- **PUT** `/admin/sub-categories/{id}` - Update
- **DELETE** `/admin/sub-categories/{id}` - Delete

---

#### 30. Create Class
**POST** `/admin/classes`

Create a new class (global catalog).

**Request Body:**
```json
{
  "course_id": 1,
  "name": "CLASS-2024-001",
  "status": "active"
}
```

**Response (201):**
```json
{
  "class": {
    "id": 1,
    "course_id": 1,
    "name": "CLASS-2024-001",
    "status": "active"
  }
}
```

---

#### 31-33. Class CRUD
- **GET** `/admin/classes` - List all
- **PUT** `/admin/classes/{id}` - Update
- **DELETE** `/admin/classes/{id}` - Delete

---

### Financial & Reporting

#### 34. Financial Dashboard
**GET** `/admin/financial/dashboard`

Get financial dashboard data.

**Response (200):**
```json
{
  "total_revenue": 100000.00,
  "pending_settlements": 50000.00,
  "this_month_revenue": 25000.00,
  "active_accs": 10
}
```

---

#### 35. Get Transactions
**GET** `/admin/financial/transactions`

Get all financial transactions.

**Query Parameters:**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15)
- `type` - Filter by transaction type
- `status` - Filter by status

**Response (200):**
```json
{
  "transactions": [],
  "total": 0,
  "per_page": 15,
  "current_page": 1
}
```

---

#### 36. Get Settlements
**GET** `/admin/financial/settlements`

Get all monthly settlements.

**Response (200):**
```json
{
  "settlements": []
}
```

---

#### 37. Request Payment from ACC
**POST** `/admin/financial/settlements/{id}/request-payment`

Request payment for a settlement.

**Response (200):**
```json
{
  "message": "Payment request sent successfully"
}
```

---

#### 38-41. Reports
- **GET** `/admin/reports/revenue` - Revenue reports
- **GET** `/admin/reports/accs` - ACC reports
- **GET** `/admin/reports/training-centers` - Training center reports
- **GET** `/admin/reports/certificates` - Certificate reports

---

## ACC Endpoints
*Requires role: `acc_admin`*

### Dashboard

#### 42. ACC Dashboard
**GET** `/acc/dashboard`

Get ACC dashboard data.

**Response (200):**
```json
{
  "subscription_status": "active",
  "subscription_expires": "2024-12-31",
  "pending_requests": 5,
  "active_training_centers": 10,
  "revenue_this_month": 50000.00
}
```

---

### Subscription Management

#### 43. Get Subscription
**GET** `/acc/subscription`

Get current subscription details.

**Response (200):**
```json
{
  "subscription": {
    "id": 1,
    "subscription_start_date": "2024-01-01",
    "subscription_end_date": "2024-12-31",
    "renewal_date": "2024-12-31",
    "amount": 10000.00,
    "payment_status": "paid",
    "auto_renew": true
  }
}
```

---

#### 44. Pay Subscription
**POST** `/acc/subscription/payment`

Make subscription payment.

**Request Body:**
```json
{
  "amount": 10000.00,
  "payment_method": "credit_card",
  "payment_gateway_transaction_id": "txn_123456"
}
```

**Response (200):**
```json
{
  "message": "Payment successful",
  "subscription": {}
}
```

---

#### 45. Renew Subscription
**PUT** `/acc/subscription/renew`

Renew subscription.

**Response (200):**
```json
{
  "message": "Subscription renewed successfully"
}
```

---

### Training Center Management

#### 46. Get Authorization Requests
**GET** `/acc/training-centers/requests`

Get all training center authorization requests.

**Response (200):**
```json
{
  "requests": [
    {
      "id": 1,
      "training_center": {
        "name": "XYZ Training Center",
        "email": "info@xyz.com"
      },
      "status": "pending",
      "request_date": "2024-01-15T10:30:00.000000Z"
    }
  ]
}
```

---

#### 47. Approve Training Center
**PUT** `/acc/training-centers/requests/{id}/approve`

Approve a training center authorization request.

**Response (200):**
```json
{
  "message": "Training center approved successfully"
}
```

---

#### 48. Reject Training Center
**PUT** `/acc/training-centers/requests/{id}/reject`

Reject a training center authorization request.

**Request Body:**
```json
{
  "rejection_reason": "Insufficient documentation"
}
```

**Response (200):**
```json
{
  "message": "Training center rejected"
}
```

---

#### 49. Return Training Center Request
**PUT** `/acc/training-centers/requests/{id}/return`

Return a training center request for additional information.

**Request Body:**
```json
{
  "return_comment": "Please provide additional insurance documents"
}
```

**Response (200):**
```json
{
  "message": "Request returned successfully"
}
```

---

#### 50. List Authorized Training Centers
**GET** `/acc/training-centers`

Get all authorized training centers.

**Response (200):**
```json
{
  "training_centers": []
}
```

---

### Instructor Management

#### 51-54. Instructor Requests
Similar endpoints as training centers:
- **GET** `/acc/instructors/requests` - Get requests
- **PUT** `/acc/instructors/requests/{id}/approve` - Approve
- **PUT** `/acc/instructors/requests/{id}/reject` - Reject
- **GET** `/acc/instructors` - List authorized

---

### Course Management

#### 55. Create Course
**POST** `/acc/courses`

Create a new course.

**Request Body:**
```json
{
  "sub_category_id": 1,
  "name": "Advanced Fire Safety",
  "name_ar": "السلامة من الحرائق المتقدمة",
  "code": "AFS-001",
  "description": "Advanced fire safety training course",
  "duration_hours": 40,
  "level": "advanced",
  "status": "active"
}
```

**Response (201):**
```json
{
  "course": {
    "id": 1,
    "name": "Advanced Fire Safety",
    "code": "AFS-001",
    "status": "active"
  }
}
```

---

#### 56-59. Course CRUD
- **GET** `/acc/courses` - List all
- **GET** `/acc/courses/{id}` - Get details
- **PUT** `/acc/courses/{id}` - Update
- **DELETE** `/acc/courses/{id}` - Delete

---

#### 60. Set Course Pricing
**POST** `/acc/courses/{id}/pricing`

Set pricing for a course.

**Request Body:**
```json
{
  "base_price": 500.00,
  "currency": "USD",
  "group_commission_percentage": 10.0,
  "training_center_commission_percentage": 5.0,
  "instructor_commission_percentage": 3.0,
  "effective_from": "2024-01-01"
}
```

**Response (200):**
```json
{
  "message": "Pricing set successfully"
}
```

---

#### 61. Update Course Pricing
**PUT** `/acc/courses/{id}/pricing`

Update course pricing.

**Request Body:** Same as set pricing

---

### Certificate Templates

#### 62. Create Certificate Template
**POST** `/acc/certificate-templates`

Create a certificate template.

**Request Body:**
```json
{
  "category_id": 1,
  "name": "Standard Certificate Template",
  "template_html": "<html>...</html>",
  "template_variables": ["trainee_name", "course_name", "issue_date"],
  "background_image_url": "/templates/bg.jpg",
  "logo_positions": {"x": 100, "y": 50},
  "signature_positions": {"x": 200, "y": 300},
  "status": "active"
}
```

**Response (201):**
```json
{
  "template": {
    "id": 1,
    "name": "Standard Certificate Template",
    "status": "active"
  }
}
```

---

#### 63-66. Template CRUD
- **GET** `/acc/certificate-templates` - List all
- **GET** `/acc/certificate-templates/{id}` - Get details
- **PUT** `/acc/certificate-templates/{id}` - Update
- **DELETE** `/acc/certificate-templates/{id}` - Delete

---

#### 67. Preview Template
**POST** `/acc/certificate-templates/{id}/preview`

Preview certificate template with sample data.

**Request Body:**
```json
{
  "sample_data": {
    "trainee_name": "John Doe",
    "course_name": "Fire Safety",
    "issue_date": "2024-01-15"
  }
}
```

**Response (200):**
```json
{
  "preview_url": "/preview/template_123.pdf"
}
```

---

### Discount Codes

#### 68. Create Discount Code
**POST** `/acc/discount-codes`

Create a discount code.

**Request Body (Time-limited):**
```json
{
  "code": "SAVE20",
  "discount_type": "time_limited",
  "discount_percentage": 20.0,
  "applicable_course_ids": [1, 2, 3],
  "start_date": "2024-01-01",
  "end_date": "2024-12-31",
  "status": "active"
}
```

**Request Body (Quantity-based):**
```json
{
  "code": "BULK50",
  "discount_type": "quantity_based",
  "discount_percentage": 15.0,
  "applicable_course_ids": [1, 2],
  "total_quantity": 100,
  "status": "active"
}
```

**Response (201):**
```json
{
  "discount_code": {
    "id": 1,
    "code": "SAVE20",
    "discount_type": "time_limited",
    "status": "active"
  }
}
```

---

#### 69-72. Discount Code CRUD
- **GET** `/acc/discount-codes` - List all
- **GET** `/acc/discount-codes/{id}` - Get details
- **PUT** `/acc/discount-codes/{id}` - Update
- **DELETE** `/acc/discount-codes/{id}` - Delete

---

#### 73. Validate Discount Code
**POST** `/acc/discount-codes/validate`

Validate a discount code.

**Request Body:**
```json
{
  "code": "SAVE20",
  "course_id": 1
}
```

**Response (200):**
```json
{
  "valid": true,
  "discount_percentage": 20.0,
  "message": "Discount code is valid"
}
```

---

### Materials Management

#### 74. Create Material
**POST** `/acc/materials`

Create a course material.

**Request Body:**
```json
{
  "course_id": 1,
  "material_type": "pdf",
  "name": "Fire Safety Manual",
  "description": "Complete fire safety manual",
  "price": 50.00,
  "file_url": "/materials/fire_safety.pdf",
  "preview_url": "/materials/fire_safety_preview.pdf",
  "status": "active"
}
```

**Response (201):**
```json
{
  "material": {
    "id": 1,
    "name": "Fire Safety Manual",
    "price": 50.00,
    "status": "active"
  }
}
```

---

#### 75-78. Material CRUD
- **GET** `/acc/materials` - List all
- **GET** `/acc/materials/{id}` - Get details
- **PUT** `/acc/materials/{id}` - Update
- **DELETE** `/acc/materials/{id}` - Delete

---

### Certificates & Classes

#### 79. Get Certificates
**GET** `/acc/certificates`

Get all certificates issued.

**Query Parameters:**
- `page` - Page number
- `per_page` - Items per page
- `status` - Filter by status
- `course_id` - Filter by course

**Response (200):**
```json
{
  "certificates": [],
  "total": 0,
  "per_page": 15,
  "current_page": 1
}
```

---

#### 80. Get Classes
**GET** `/acc/classes`

Get all classes across authorized training centers.

**Response (200):**
```json
{
  "classes": []
}
```

---

### Financial

#### 81. Get Transactions
**GET** `/acc/financial/transactions`

Get ACC financial transactions.

**Response (200):**
```json
{
  "transactions": []
}
```

---

#### 82. Get Settlements
**GET** `/acc/financial/settlements`

Get monthly settlements.

**Response (200):**
```json
{
  "settlements": []
}
```

---

## Training Center Endpoints
*Requires role: `training_center_admin`*

### Dashboard

#### 83. Training Center Dashboard
**GET** `/training-center/dashboard`

Get training center dashboard data.

**Response (200):**
```json
{
  "authorizations": [
    {
      "acc": {
        "name": "ABC Accreditation Body"
      },
      "status": "approved"
    }
  ],
  "code_inventory": {
    "total": 100,
    "used": 25,
    "available": 75
  },
  "active_classes": 5,
  "wallet_balance": 5000.00
}
```

---

### ACC Management

#### 84. List ACCs
**GET** `/training-center/accs`

Browse available ACCs.

**Response (200):**
```json
{
  "accs": [
    {
      "id": 1,
      "name": "ABC Accreditation Body",
      "email": "info@abc.com",
      "status": "active"
    }
  ]
}
```

---

#### 85. Request Authorization
**POST** `/training-center/accs/{id}/request-authorization`

Request authorization from an ACC.

**Request Body:**
```json
{
  "documents": [
    {
      "type": "license",
      "url": "/documents/license.pdf"
    },
    {
      "type": "insurance",
      "url": "/documents/insurance.pdf"
    }
  ]
}
```

**Response (201):**
```json
{
  "message": "Authorization request submitted successfully",
  "authorization": {
    "id": 1,
    "status": "pending"
  }
}
```

---

#### 86. Get Authorizations
**GET** `/training-center/authorizations`

Get all authorization requests.

**Response (200):**
```json
{
  "authorizations": []
}
```

---

### Instructor Management

#### 87. Create Instructor
**POST** `/training-center/instructors`

Add a new instructor.

**Request Body:**
```json
{
  "first_name": "Jane",
  "last_name": "Smith",
  "email": "jane@example.com",
  "phone": "+1234567890",
  "id_number": "ID123456",
  "cv_url": "/documents/cv.pdf",
  "certificates": [
    {
      "name": "Fire Safety Instructor",
      "issuer": "ABC Body",
      "expiry": "2025-12-31"
    }
  ],
  "specializations": ["Fire Safety", "First Aid"]
}
```

**Response (201):**
```json
{
  "instructor": {
    "id": 1,
    "first_name": "Jane",
    "last_name": "Smith",
    "email": "jane@example.com",
    "status": "pending"
  }
}
```

---

#### 88-90. Instructor CRUD
- **GET** `/training-center/instructors` - List all
- **GET** `/training-center/instructors/{id}` - Get details
- **PUT** `/training-center/instructors/{id}` - Update

---

#### 91. Request Instructor Authorization
**POST** `/training-center/instructors/{id}/request-authorization`

Submit instructor for ACC approval.

**Request Body:**
```json
{
  "acc_id": 1,
  "course_ids": [1, 2, 3],
  "documents": []
}
```

**Response (201):**
```json
{
  "message": "Authorization request submitted successfully"
}
```

---

### Certificate Codes

#### 92. Purchase Codes
**POST** `/training-center/codes/purchase`

Purchase certificate codes.

**Request Body:**
```json
{
  "acc_id": 1,
  "course_id": 1,
  "quantity": 10,
  "discount_code": "SAVE20",
  "payment_method": "wallet"
}
```

**Response (201):**
```json
{
  "message": "Codes purchased successfully",
  "batch": {
    "id": 1,
    "quantity": 10,
    "total_amount": 4000.00,
    "codes": [
      {
        "id": 1,
        "code": "CODE-2024-001",
        "status": "available"
      }
    ]
  }
}
```

---

#### 93. Get Code Inventory
**GET** `/training-center/codes/inventory`

Get available certificate codes.

**Query Parameters:**
- `acc_id` - Filter by ACC
- `course_id` - Filter by course
- `status` - Filter by status (available, used, expired)

**Response (200):**
```json
{
  "codes": [
    {
      "id": 1,
      "code": "CODE-2024-001",
      "course": {
        "name": "Fire Safety"
      },
      "status": "available",
      "purchased_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "summary": {
    "total": 100,
    "available": 75,
    "used": 20,
    "expired": 5
  }
}
```

---

#### 94. Get Code Batches
**GET** `/training-center/codes/batches`

Get all code purchase batches.

**Response (200):**
```json
{
  "batches": [
    {
      "id": 1,
      "quantity": 10,
      "total_amount": 4000.00,
      "purchase_date": "2024-01-15T10:30:00.000000Z",
      "payment_method": "wallet"
    }
  ]
}
```

---

### Wallet Management

#### 95. Add Funds to Wallet
**POST** `/training-center/wallet/add-funds`

Add funds to wallet.

**Request Body:**
```json
{
  "amount": 1000.00,
  "payment_method": "credit_card",
  "payment_gateway_transaction_id": "txn_123456"
}
```

**Response (200):**
```json
{
  "message": "Funds added successfully",
  "wallet": {
    "balance": 6000.00,
    "currency": "USD"
  }
}
```

---

#### 96. Get Wallet Balance
**GET** `/training-center/wallet/balance`

Get current wallet balance.

**Response (200):**
```json
{
  "wallet": {
    "balance": 5000.00,
    "currency": "USD",
    "last_updated": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

#### 97. Get Wallet Transactions
**GET** `/training-center/wallet/transactions`

Get wallet transaction history.

**Response (200):**
```json
{
  "transactions": []
}
```

---

### Classes Management

#### 98. Create Class
**POST** `/training-center/classes`

Create a new training class.

**Request Body:**
```json
{
  "course_id": 1,
  "class_id": 1,
  "instructor_id": 1,
  "start_date": "2024-02-01",
  "end_date": "2024-02-05",
  "schedule": {
    "monday": "09:00-17:00",
    "tuesday": "09:00-17:00",
    "wednesday": "09:00-17:00"
  },
  "max_capacity": 20,
  "location": "physical",
  "location_details": "Training Room A, Building 1"
}
```

**Response (201):**
```json
{
  "class": {
    "id": 1,
    "course": {
      "name": "Fire Safety"
    },
    "instructor": {
      "first_name": "Jane",
      "last_name": "Smith"
    },
    "start_date": "2024-02-01",
    "status": "scheduled"
  }
}
```

---

#### 99-102. Class CRUD
- **GET** `/training-center/classes` - List all
- **GET** `/training-center/classes/{id}` - Get details
- **PUT** `/training-center/classes/{id}` - Update
- **DELETE** `/training-center/classes/{id}` - Delete

---

#### 103. Mark Class Complete
**PUT** `/training-center/classes/{id}/complete`

Mark a class as completed.

**Response (200):**
```json
{
  "message": "Class marked as completed"
}
```

---

### Certificate Generation

#### 104. Generate Certificate
**POST** `/training-center/certificates/generate`

Generate a certificate for a trainee.

**Request Body:**
```json
{
  "training_class_id": 1,
  "code_id": 1,
  "trainee_name": "John Doe",
  "trainee_id_number": "ID123456",
  "expiry_date": "2026-01-15"
}
```

**Response (201):**
```json
{
  "message": "Certificate generated successfully",
  "certificate": {
    "id": 1,
    "certificate_number": "CERT-2024-001",
    "verification_code": "VERIFY-ABC123",
    "certificate_pdf_url": "/certificates/cert_001.pdf",
    "status": "valid"
  }
}
```

---

#### 105. List Certificates
**GET** `/training-center/certificates`

Get all generated certificates.

**Query Parameters:**
- `page` - Page number
- `per_page` - Items per page
- `course_id` - Filter by course
- `status` - Filter by status

**Response (200):**
```json
{
  "certificates": [],
  "total": 0,
  "per_page": 15,
  "current_page": 1
}
```

---

#### 106. Get Certificate Details
**GET** `/training-center/certificates/{id}`

Get certificate details.

**Response (200):**
```json
{
  "certificate": {
    "id": 1,
    "certificate_number": "CERT-2024-001",
    "trainee_name": "John Doe",
    "course": {
      "name": "Fire Safety"
    },
    "issue_date": "2024-01-15",
    "status": "valid",
    "certificate_pdf_url": "/certificates/cert_001.pdf"
  }
}
```

---

### Marketplace

#### 107. Browse Materials
**GET** `/training-center/marketplace/materials`

Browse available materials from ACCs.

**Query Parameters:**
- `acc_id` - Filter by ACC
- `course_id` - Filter by course
- `material_type` - Filter by type (pdf, video, presentation, package)
- `search` - Search term

**Response (200):**
```json
{
  "materials": [
    {
      "id": 1,
      "name": "Fire Safety Manual",
      "acc": {
        "name": "ABC Accreditation Body"
      },
      "price": 50.00,
      "material_type": "pdf",
      "preview_url": "/materials/preview.pdf"
    }
  ]
}
```

---

#### 108. Get Material Details
**GET** `/training-center/marketplace/materials/{id}`

Get material details.

**Response (200):**
```json
{
  "material": {
    "id": 1,
    "name": "Fire Safety Manual",
    "description": "Complete fire safety manual",
    "price": 50.00,
    "preview_url": "/materials/preview.pdf"
  }
}
```

---

#### 109. Purchase Material/Course
**POST** `/training-center/marketplace/purchase`

Purchase material, course, or package.

**Request Body:**
```json
{
  "purchase_type": "material",
  "item_id": 1,
  "acc_id": 1,
  "payment_method": "wallet"
}
```

**Response (201):**
```json
{
  "message": "Purchase successful",
  "purchase": {
    "id": 1,
    "purchase_type": "material",
    "amount": 50.00,
    "purchased_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

#### 110. Get Library
**GET** `/training-center/library`

Get purchased materials library.

**Response (200):**
```json
{
  "library": [
    {
      "id": 1,
      "name": "Fire Safety Manual",
      "purchase_type": "material",
      "purchased_at": "2024-01-15T10:30:00.000000Z",
      "file_url": "/materials/fire_safety.pdf"
    }
  ]
}
```

---

## Instructor Endpoints
*Requires role: `instructor`*

### Dashboard

#### 111. Instructor Dashboard
**GET** `/instructor/dashboard`

Get instructor dashboard data.

**Response (200):**
```json
{
  "assigned_classes": 5,
  "upcoming_classes": 2,
  "completed_classes": 10,
  "earnings_this_month": 1500.00
}
```

---

### Classes

#### 112. List Classes
**GET** `/instructor/classes`

Get assigned classes.

**Query Parameters:**
- `status` - Filter by status (scheduled, in_progress, completed)

**Response (200):**
```json
{
  "classes": [
    {
      "id": 1,
      "course": {
        "name": "Fire Safety"
      },
      "training_center": {
        "name": "XYZ Training Center"
      },
      "start_date": "2024-02-01",
      "end_date": "2024-02-05",
      "status": "scheduled",
      "enrolled_count": 15,
      "max_capacity": 20
    }
  ]
}
```

---

#### 113. Get Class Details
**GET** `/instructor/classes/{id}`

Get class details.

**Response (200):**
```json
{
  "class": {
    "id": 1,
    "course": {
      "name": "Fire Safety",
      "description": "Fire safety training course"
    },
    "schedule": {
      "monday": "09:00-17:00",
      "tuesday": "09:00-17:00"
    },
    "location": "physical",
    "location_details": "Training Room A"
  }
}
```

---

#### 114. Mark Class Complete
**PUT** `/instructor/classes/{id}/mark-complete`

Mark a class as completed.

**Request Body:**
```json
{
  "completion_rate_percentage": 95.5,
  "notes": "All students completed successfully"
}
```

**Response (200):**
```json
{
  "message": "Class marked as completed",
  "completion": {
    "id": 1,
    "completed_date": "2024-02-05",
    "completion_rate_percentage": 95.5
  }
}
```

---

### Materials

#### 115. Get Materials
**GET** `/instructor/materials`

Get available course materials.

**Response (200):**
```json
{
  "materials": [
    {
      "id": 1,
      "name": "Fire Safety Manual",
      "material_type": "pdf",
      "file_url": "/materials/fire_safety.pdf"
    }
  ]
}
```

---

### Earnings

#### 116. Get Earnings
**GET** `/instructor/earnings`

Get instructor earnings and payment history.

**Query Parameters:**
- `month` - Filter by month (YYYY-MM)
- `year` - Filter by year

**Response (200):**
```json
{
  "earnings": {
    "total": 5000.00,
    "this_month": 1500.00,
    "pending": 500.00,
    "paid": 4500.00
  },
  "transactions": []
}
```

---

## Error Responses

All endpoints may return the following error responses:

### 401 Unauthorized
```json
{
  "message": "Unauthenticated"
}
```

### 403 Forbidden
```json
{
  "message": "Unauthorized. Required role: group_admin"
}
```

### 404 Not Found
```json
{
  "message": "Resource not found"
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### 500 Server Error
```json
{
  "message": "Server Error"
}
```

---

## Notes for Frontend Developers

1. **Authentication**: Store the token received from login/register and include it in all subsequent requests.

2. **Pagination**: Many list endpoints support pagination. Check for `per_page`, `current_page`, and `total` in responses.

3. **File Uploads**: For file uploads (documents, materials), use `multipart/form-data` format.

4. **Date Formats**: All dates are in ISO 8601 format (YYYY-MM-DD or YYYY-MM-DDTHH:mm:ss.sssZ).

5. **Currency**: All monetary values are in decimal format with 2 decimal places.

6. **Status Enums**: 
   - User status: `pending`, `active`, `suspended`, `inactive`
   - ACC status: `pending`, `active`, `suspended`, `expired`
   - Authorization status: `pending`, `approved`, `rejected`, `returned`
   - Certificate status: `valid`, `revoked`, `expired`
   - Code status: `available`, `used`, `expired`, `revoked`
   - Transaction status: `pending`, `completed`, `failed`, `refunded`

7. **Roles**: 
   - `group_admin` - Platform administrator
   - `acc_admin` - Accreditation body administrator
   - `training_center_admin` - Training center administrator
   - `instructor` - Instructor/Assessor

8. **Testing**: Use tools like Postman or Insomnia to test endpoints. Remember to include the Authorization header with the bearer token.

---

## Support

For API support or questions, please contact the backend development team.

**Last Updated**: January 2024
**API Version**: 1.0

