# Frontend Developer Guide - BOMEQP Accreditation Management System

## Table of Contents
1. [Project Overview](#project-overview)
2. [User Roles & Layers](#user-roles--layers)
3. [Authentication & Authorization](#authentication--authorization)
4. [API Base Configuration](#api-base-configuration)
5. [Complete API Reference](#complete-api-reference)
6. [Integration Examples](#integration-examples)
7. [Error Handling](#error-handling)
8. [Best Practices](#best-practices)

---

## Project Overview

### What is BOMEQP?
BOMEQP is a **multi-tenant accreditation management platform** that connects:
- **Accreditation Bodies (ACCs)** - Organizations that provide certifications
- **Training Centers** - Institutions that deliver training courses
- **Instructors** - Teachers who conduct classes
- **Group Admin** - Platform administrators who manage the system

### Key Features
- Certificate code purchase and generation system
- Multi-level commission structure
- Subscription-based ACC management
- Authorization workflows
- Financial transaction tracking
- Marketplace for course materials
- Real-time dashboard analytics

### Technology Stack
- **Backend**: Laravel (PHP) with Sanctum authentication
- **API**: RESTful JSON API
- **Payment**: Stripe integration
- **Database**: MySQL/PostgreSQL

---

## User Roles & Layers

The system has **4 distinct user layers**, each with different permissions and access levels:

### 1. Group Admin (Platform Owner)
**Role Code**: `group_admin`

**Responsibilities:**
- Review and approve/reject ACC registration applications
- Create ACC workspaces after approval
- Generate login credentials for ACCs
- Set commission percentages (0-100%) for ACCs, Training Centers, and Instructors
- Manage global categories, sub-categories, and course classes
- Track all financial transactions
- Request monthly settlements from ACCs
- View system-wide analytics and reports
- Manage Stripe payment settings

**Dashboard Features:**
- Pending ACC applications count
- Revenue metrics (daily, monthly, yearly)
- Active ACCs count
- Pending commission settings
- Recent transactions
- System alerts and notifications

**Key Workflows:**
1. ACC Registration → Review → Approve → Create Space → Generate Credentials
2. Set commission percentages after ACC approvals
3. Monitor financial transactions and settlements

---

### 2. ACC Admin (Accreditation Body)
**Role Code**: `acc_admin`

**Responsibilities:**
- Manage subscription (view expiry, renewal dates, make payments)
- Define accreditation structure (categories, sub-categories, courses)
- Manage certificate templates (one per category)
- Set certificate prices per course
- Create discount codes (time-limited or quantity-based)
- Review and approve/reject training center authorization requests
- Review and approve/reject instructor authorization requests
- View all classes across authorized training centers
- Sell materials and courses to training centers
- Receive monthly settlement requests from Group
- Make monthly payments to Group

**Dashboard Features:**
- Subscription status and expiry
- Pending authorization requests count
- Revenue overview
- Active training centers count
- Active instructors count
- Recent certificate generations
- Settlement status

**Key Workflows:**
1. Registration → Wait for Group Approval → Pay Subscription → Account Activated
2. Create Courses → Set Pricing → Create Certificate Templates
3. Review Training Center/Instructor Requests → Approve/Reject/Return
4. Create Discount Codes → Manage Materials → View Financial Reports

---

### 3. Training Center Admin
**Role Code**: `training_center_admin`

**Responsibilities:**
- Free sign-up on portal
- Browse available ACCs
- Submit authorization requests to ACCs
- Purchase certificate codes via wallet or credit card
- Add and manage instructors (submit for ACC approval)
- Create and manage classes
- Assign approved instructors to classes
- Generate certificates using purchased codes (only after class completion)
- Purchase materials/courses from ACCs
- Track code inventory and usage
- View financial statements

**Dashboard Features:**
- Authorization status per ACC
- Code inventory summary
- Active classes count
- Upcoming classes
- Wallet balance
- Recent purchases
- Pending instructor approvals
- Financial summary

**Key Workflows:**
1. Registration → Browse ACCs → Request Authorization → Wait for Approval
2. Purchase Codes → Create Classes → Assign Instructors → Complete Classes → Generate Certificates
3. Browse Marketplace → Purchase Materials → Access Library

---

### 4. Instructor
**Role Code**: `instructor`

**Responsibilities:**
- View assigned classes
- Access course materials
- Mark class completion status
- View student attendance (future)
- Receive notifications for new assignments
- View earnings and payment history

**Dashboard Features:**
- Assigned classes overview
- Upcoming classes
- Completed classes
- Earnings summary
- Available course materials
- Notifications

**Key Workflows:**
1. Added by Training Center → Submit Authorization Request → Wait for ACC Approval → Receive Credentials
2. View Classes → Access Materials → Mark Completion → View Earnings

---

## Authentication & Authorization

### Authentication Flow

1. **Registration/Login** → Receive Bearer Token
2. **Store Token** → Include in all subsequent requests
3. **Token Expiration** → Re-authenticate or refresh

### Token Storage
```javascript
// Store token after login
localStorage.setItem('auth_token', response.data.token);

// Include in requests
headers: {
  'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}
```

### Role-Based Access
- Each endpoint checks user role automatically
- Unauthorized access returns `403 Forbidden`
- Token required for all protected endpoints

---

## API Base Configuration

### Base URL
```
Production: https://aeroenix.com/v1/api
Development: http://localhost/api
```

### Headers
All requests should include:
```javascript
{
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'Authorization': 'Bearer {token}' // Required for protected routes
}
```

### Response Format
**Success Response:**
```json
{
  "message": "Operation successful",
  "data": { ... }
}
```

**Error Response:**
```json
{
  "message": "Error message",
  "errors": {
    "field": ["Error details"]
  }
}
```

### HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Complete API Reference

### Public Endpoints (No Authentication Required)

#### 1. User Registration
**POST** `/auth/register`

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "training_center_admin", // or "acc_admin"
  "country": "United States", // optional
  "city": "New York", // optional
  "address": "123 Main St", // optional
  "phone": "+1234567890" // optional
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

#### 2. User Login
**POST** `/auth/login`

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

#### 3. Forgot Password
**POST** `/auth/forgot-password`

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

#### 4. Reset Password
**POST** `/auth/reset-password`

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

#### 5. Verify Email
**GET** `/auth/verify-email/{token}`

**Response (200):**
```json
{
  "message": "Email verified successfully"
}
```

---

#### 6. Verify Certificate (Public)
**GET** `/certificates/verify/{code}`

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

---

### Protected Endpoints (Authentication Required)

#### Authentication Endpoints

#### 7. Get User Profile
**GET** `/auth/profile`

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

---

#### 10. Logout
**POST** `/auth/logout`

**Response (200):**
```json
{
  "message": "Logged out successfully"
}
```

---

### Stripe Payment Endpoints (All Authenticated Users)

#### 11. Get Stripe Config
**GET** `/stripe/config`

**Response (200):**
```json
{
  "publishable_key": "pk_test_...",
  "currency": "usd"
}
```

---

#### 12. Create Payment Intent
**POST** `/stripe/payment-intent`

**Request Body:**
```json
{
  "amount": 10000, // in cents
  "currency": "usd",
  "description": "Code purchase",
  "metadata": {
    "type": "code_purchase",
    "batch_id": 1
  }
}
```

**Response (200):**
```json
{
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx"
}
```

---

#### 13. Confirm Payment
**POST** `/stripe/confirm`

**Request Body:**
```json
{
  "payment_intent_id": "pi_xxx",
  "transaction_id": 1
}
```

**Response (200):**
```json
{
  "message": "Payment confirmed",
  "transaction": { ... }
}
```

---

---

## Group Admin Endpoints
*Requires role: `group_admin`*

### ACC Management

#### 14. Get ACC Applications
**GET** `/admin/accs/applications`

**Query Parameters:**
- `status` - Filter by status (pending, approved, rejected)
- `page` - Page number
- `per_page` - Items per page

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
      "documents": [],
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 10,
    "per_page": 15
  }
}
```

---

#### 15. Get ACC Application Details
**GET** `/admin/accs/applications/{id}`

**Response (200):**
```json
{
  "application": {
    "id": 1,
    "name": "ABC Accreditation Body",
    "legal_name": "ABC Accreditation Body LLC",
    "registration_number": "ACC-001",
    "email": "info@abc.com",
    "phone": "+1234567890",
    "country": "United States",
    "address": "123 Main St",
    "status": "pending",
    "documents": [
      {
        "id": 1,
        "document_type": "license",
        "document_url": "/storage/documents/license.pdf",
        "verified": false
      }
    ],
    "created_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

#### 16. Approve ACC Application
**PUT** `/admin/accs/applications/{id}/approve`

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

#### 17. Reject ACC Application
**PUT** `/admin/accs/applications/{id}/reject`

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

#### 18. Create ACC Space
**POST** `/admin/accs/{id}/create-space`

**Response (200):**
```json
{
  "message": "ACC space created successfully"
}
```

---

#### 19. Generate ACC Credentials
**POST** `/admin/accs/{id}/generate-credentials`

**Response (200):**
```json
{
  "message": "Credentials generated successfully",
  "username": "acc_abc",
  "password": "temporary_password"
}
```

---

#### 20. List All ACCs
**GET** `/admin/accs`

**Query Parameters:**
- `status` - Filter by status
- `page` - Page number
- `per_page` - Items per page

**Response (200):**
```json
{
  "accs": [
    {
      "id": 1,
      "name": "ABC Accreditation Body",
      "email": "info@abc.com",
      "status": "active",
      "subscriptions": [],
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 5,
    "per_page": 15
  }
}
```

---

#### 21. Get ACC Details
**GET** `/admin/accs/{id}`

**Response (200):**
```json
{
  "acc": {
    "id": 1,
    "name": "ABC Accreditation Body",
    "legal_name": "ABC Accreditation Body LLC",
    "email": "info@abc.com",
    "status": "active",
    "subscriptions": [],
    "documents": [],
    "created_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

#### 22. Set Commission Percentage
**PUT** `/admin/accs/{id}/commission-percentage`

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

#### 23. Get ACC Transactions
**GET** `/admin/accs/{id}/transactions`

**Query Parameters:**
- `type` - Filter by transaction type
- `start_date` - Start date filter
- `end_date` - End date filter
- `page` - Page number

**Response (200):**
```json
{
  "transactions": [
    {
      "id": 1,
      "transaction_type": "subscription",
      "amount": 5000.00,
      "currency": "USD",
      "status": "completed",
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ]
}
```

---

### Categories & Courses Management

#### 24. Create Category
**POST** `/admin/categories`

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
    "description": "Safety related courses",
    "icon_url": "/icons/safety.png",
    "status": "active",
    "created_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

#### 25. List Categories
**GET** `/admin/categories`

**Response (200):**
```json
{
  "categories": [
    {
      "id": 1,
      "name": "Safety Training",
      "name_ar": "تدريب السلامة",
      "status": "active"
    }
  ]
}
```

---

#### 26. Update Category
**PUT** `/admin/categories/{id}`

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
  "category": {
    "id": 1,
    "name": "Updated Safety Training",
    "status": "active"
  }
}
```

---

#### 27. Delete Category
**DELETE** `/admin/categories/{id}`

**Response (200):**
```json
{
  "message": "Category deleted successfully"
}
```

---

#### 28. Create Sub Category
**POST** `/admin/sub-categories`

**Request Body:**
```json
{
  "category_id": 1,
  "name": "Occupational Safety",
  "name_ar": "السلامة المهنية",
  "description": "Occupational safety courses",
  "status": "active"
}
```

**Response (201):**
```json
{
  "sub_category": {
    "id": 1,
    "category_id": 1,
    "name": "Occupational Safety",
    "name_ar": "السلامة المهنية",
    "status": "active"
  }
}
```

---

#### 29. List Sub Categories
**GET** `/admin/sub-categories`

**Query Parameters:**
- `category_id` - Filter by category

**Response (200):**
```json
{
  "sub_categories": [
    {
      "id": 1,
      "category_id": 1,
      "name": "Occupational Safety",
      "status": "active"
    }
  ]
}
```

---

#### 30. Update Sub Category
**PUT** `/admin/sub-categories/{id}`

**Request Body:**
```json
{
  "name": "Updated Sub Category",
  "status": "active"
}
```

**Response (200):**
```json
{
  "message": "Sub category updated successfully",
  "sub_category": { ... }
}
```

---

#### 31. Delete Sub Category
**DELETE** `/admin/sub-categories/{id}`

**Response (200):**
```json
{
  "message": "Sub category deleted successfully"
}
```

---

#### 32. Create Class
**POST** `/admin/classes`

**Request Body:**
```json
{
  "course_id": 1,
  "name": "Safety Fundamentals - Class 1"
}
```

**Response (201):**
```json
{
  "class": {
    "id": 1,
    "course_id": 1,
    "name": "Safety Fundamentals - Class 1",
    "status": "active",
    "created_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

#### 33. List Classes
**GET** `/admin/classes`

**Query Parameters:**
- `course_id` - Filter by course

**Response (200):**
```json
{
  "classes": [
    {
      "id": 1,
      "course_id": 1,
      "name": "Safety Fundamentals - Class 1",
      "status": "active"
    }
  ]
}
```

---

#### 34. Update Class
**PUT** `/admin/classes/{id}`

**Request Body:**
```json
{
  "name": "Updated Class Name",
  "status": "active"
}
```

**Response (200):**
```json
{
  "message": "Class updated successfully",
  "class": { ... }
}
```

---

#### 35. Delete Class
**DELETE** `/admin/classes/{id}`

**Response (200):**
```json
{
  "message": "Class deleted successfully"
}
```

---

### Financial & Reporting

#### 36. Get Financial Dashboard
**GET** `/admin/financial/dashboard`

**Response (200):**
```json
{
  "total_revenue": 100000.00,
  "monthly_revenue": 50000.00,
  "pending_settlements": 25000.00,
  "total_accs": 10,
  "active_accs": 8,
  "recent_transactions": []
}
```

---

#### 37. Get Financial Transactions
**GET** `/admin/financial/transactions`

**Query Parameters:**
- `type` - Filter by transaction type
- `start_date` - Start date
- `end_date` - End date
- `page` - Page number

**Response (200):**
```json
{
  "transactions": [],
  "meta": { ... }
}
```

---

#### 38. Get Settlements
**GET** `/admin/financial/settlements`

**Response (200):**
```json
{
  "settlements": [
    {
      "id": 1,
      "settlement_month": "2024-01",
      "acc_id": 1,
      "total_revenue": 10000.00,
      "group_commission_amount": 1500.00,
      "status": "pending",
      "request_date": "2024-02-01"
    }
  ]
}
```

---

#### 39. Request Payment from ACC
**POST** `/admin/financial/settlements/{id}/request-payment`

**Response (200):**
```json
{
  "message": "Payment request sent successfully"
}
```

---

#### 40. Get Revenue Report
**GET** `/admin/reports/revenue`

**Query Parameters:**
- `start_date` - Start date
- `end_date` - End date
- `group_by` - day, month, year

**Response (200):**
```json
{
  "revenue": [],
  "total": 100000.00,
  "period": "2024-01"
}
```

---

#### 41. Get ACCs Report
**GET** `/admin/reports/accs`

**Response (200):**
```json
{
  "accs": [],
  "total_active": 8,
  "total_pending": 2
}
```

---

#### 42. Get Training Centers Report
**GET** `/admin/reports/training-centers`

**Response (200):**
```json
{
  "training_centers": [],
  "total": 50,
  "active": 45
}
```

---

#### 43. Get Certificates Report
**GET** `/admin/reports/certificates`

**Response (200):**
```json
{
  "certificates": [],
  "total_generated": 1000,
  "valid": 950,
  "revoked": 50
}
```

---

### Stripe Settings Management

#### 44. List Stripe Settings
**GET** `/admin/stripe-settings`

**Response (200):**
```json
{
  "settings": [
    {
      "id": 1,
      "name": "Production",
      "is_active": true,
      "publishable_key": "pk_live_...",
      "currency": "usd"
    }
  ]
}
```

---

#### 45. Get Active Stripe Setting
**GET** `/admin/stripe-settings/active`

**Response (200):**
```json
{
  "setting": {
    "id": 1,
    "name": "Production",
    "is_active": true,
    "publishable_key": "pk_live_...",
    "currency": "usd"
  }
}
```

---

#### 46. Create Stripe Setting
**POST** `/admin/stripe-settings`

**Request Body:**
```json
{
  "name": "Production",
  "secret_key": "sk_live_...",
  "publishable_key": "pk_live_...",
  "webhook_secret": "whsec_...",
  "currency": "usd",
  "is_active": true
}
```

**Response (201):**
```json
{
  "message": "Stripe setting created successfully",
  "setting": { ... }
}
```

---

#### 47. Update Stripe Setting
**PUT** `/admin/stripe-settings/{id}`

**Request Body:**
```json
{
  "name": "Updated Name",
  "is_active": false
}
```

**Response (200):**
```json
{
  "message": "Stripe setting updated successfully",
  "setting": { ... }
}
```

---

#### 48. Delete Stripe Setting
**DELETE** `/admin/stripe-settings/{id}`

**Response (200):**
```json
{
  "message": "Stripe setting deleted successfully"
}
```

---

### Instructor Management

#### 41. Get All Instructors
**GET** `/admin/instructors`

Get all instructors in the system with optional filters.

**Query Parameters:**
- `status` (optional) - Filter by status (`pending`, `active`, `suspended`, `inactive`)
- `training_center_id` (optional) - Filter by training center ID
- `search` (optional) - Search by name, email, or phone
- `per_page` (optional) - Number of results per page (default: 15)
- `page` (optional) - Page number

**Response (200):**
```json
{
  "instructors": [
    {
      "id": 1,
      "first_name": "Jane",
      "last_name": "Smith",
      "email": "jane.smith@example.com",
      "phone": "+1234567890",
      "id_number": "ID123456",
      "status": "active",
      "training_center": {
        "id": 1,
        "name": "ABC Training Center",
        "email": "info@abc.com"
      },
      "authorizations": [
        {
          "id": 1,
          "acc_id": 1,
          "status": "approved",
          "request_date": "2024-01-15T10:30:00.000000Z"
        }
      ],
      "created_at": "2024-01-10T10:30:00.000000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

---

#### 42. Get Instructor Details
**GET** `/admin/instructors/{id}`

Get detailed information about a specific instructor including all relationships.

**Response (200):**
```json
{
  "instructor": {
    "id": 1,
    "first_name": "Jane",
    "last_name": "Smith",
    "email": "jane.smith@example.com",
    "phone": "+1234567890",
    "id_number": "ID123456",
    "cv_url": "/documents/cv.pdf",
    "certificates_json": [
      {
        "name": "Fire Safety Instructor",
        "issuer": "ABC Body",
        "expiry": "2025-12-31"
      }
    ],
    "specializations": ["Fire Safety", "First Aid"],
    "status": "active",
    "training_center": {
      "id": 1,
      "name": "ABC Training Center",
      "email": "info@abc.com"
    },
    "authorizations": [
      {
        "id": 1,
        "acc": {
          "id": 1,
          "name": "ABC Accreditation Body"
        },
        "status": "approved",
        "request_date": "2024-01-15T10:30:00.000000Z"
      }
    ],
    "course_authorizations": [
      {
        "id": 1,
        "course": {
          "id": 1,
          "name": "Fire Safety Fundamentals"
        },
        "status": "approved"
      }
    ],
    "training_classes": [
      {
        "id": 1,
        "course": {
          "id": 1,
          "name": "Fire Safety Fundamentals"
        },
        "status": "in_progress",
        "start_date": "2024-02-01T09:00:00.000000Z"
      }
    ],
    "certificates": [
      {
        "id": 1,
        "certificate_number": "CERT-2024-001",
        "trainee_name": "John Doe",
        "issue_date": "2024-01-20T10:30:00.000000Z"
      }
    ],
    "created_at": "2024-01-10T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

**Error Response (404):**
```json
{
  "message": "No query results for model [App\\Models\\Instructor] 1"
}
```

---

## ACC Admin Endpoints
*Requires role: `acc_admin`*

### Dashboard & Subscription

#### 49. Get ACC Dashboard
**GET** `/acc/dashboard`

**Response (200):**
```json
{
  "subscription_status": "active",
  "subscription_expires_at": "2024-02-15",
  "pending_requests": {
    "training_centers": 5,
    "instructors": 3
  },
  "revenue": {
    "monthly": 10000.00,
    "total": 50000.00
  },
  "active_training_centers": 10,
  "active_instructors": 25,
  "certificates_generated": 150,
  "recent_activity": []
}
```

---

#### 50. Get Subscription
**GET** `/acc/subscription`

**Response (200):**
```json
{
  "subscription": {
    "id": 1,
    "subscription_start_date": "2024-01-15",
    "subscription_end_date": "2024-02-15",
    "renewal_date": "2024-02-15",
    "amount": 5000.00,
    "payment_status": "paid",
    "auto_renew": true
  }
}
```

---

#### 51. Pay Subscription
**POST** `/acc/subscription/payment`

**Request Body:**
```json
{
  "amount": 5000.00,
  "payment_method": "credit_card", // or "wallet"
  "payment_intent_id": "pi_xxx" // if using Stripe
}
```

**Response (200):**
```json
{
  "message": "Subscription payment successful",
  "subscription": { ... }
}
```

---

#### 52. Renew Subscription
**PUT** `/acc/subscription/renew`

**Request Body:**
```json
{
  "auto_renew": true
}
```

**Response (200):**
```json
{
  "message": "Subscription renewed successfully",
  "subscription": { ... }
}
```

---

### Training Centers Management

#### 53. Get Training Center Requests
**GET** `/acc/training-centers/requests`

**Query Parameters:**
- `status` - Filter by status (pending, approved, rejected, returned)

**Response (200):**
```json
{
  "requests": [
    {
      "id": 1,
      "training_center": {
        "id": 1,
        "name": "ABC Training Center",
        "email": "info@abc.com"
      },
      "status": "pending",
      "request_date": "2024-01-15",
      "documents_json": []
    }
  ]
}
```

---

#### 54. Approve Training Center Request
**PUT** `/acc/training-centers/requests/{id}/approve`

**Response (200):**
```json
{
  "message": "Training center request approved",
  "authorization": {
    "id": 1,
    "status": "approved",
    "reviewed_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

#### 55. Reject Training Center Request
**PUT** `/acc/training-centers/requests/{id}/reject`

**Request Body:**
```json
{
  "rejection_reason": "Insufficient documentation"
}
```

**Response (200):**
```json
{
  "message": "Training center request rejected",
  "authorization": {
    "id": 1,
    "status": "rejected",
    "rejection_reason": "Insufficient documentation"
  }
}
```

---

#### 56. Return Training Center Request
**PUT** `/acc/training-centers/requests/{id}/return`

**Request Body:**
```json
{
  "return_comment": "Please provide additional documents"
}
```

**Response (200):**
```json
{
  "message": "Training center request returned",
  "authorization": {
    "id": 1,
    "status": "returned",
    "return_comment": "Please provide additional documents"
  }
}
```

---

#### 57. List Authorized Training Centers
**GET** `/acc/training-centers`

**Response (200):**
```json
{
  "training_centers": [
    {
      "id": 1,
      "name": "ABC Training Center",
      "email": "info@abc.com",
      "status": "active",
      "authorized_at": "2024-01-15T10:30:00.000000Z"
    }
  ]
}
```

---

### Instructors Management

#### 58. Get Instructor Requests
**GET** `/acc/instructors/requests`

**Query Parameters:**
- `status` - Filter by status

**Response (200):**
```json
{
  "requests": [
    {
      "id": 1,
      "instructor": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com"
      },
      "status": "pending",
      "request_date": "2024-01-15"
    }
  ]
}
```

---

#### 59. Approve Instructor Request
**PUT** `/acc/instructors/requests/{id}/approve`

**Response (200):**
```json
{
  "message": "Instructor request approved",
  "authorization": {
    "id": 1,
    "status": "approved"
  }
}
```

---

#### 60. Reject Instructor Request
**PUT** `/acc/instructors/requests/{id}/reject`

**Request Body:**
```json
{
  "rejection_reason": "Insufficient qualifications"
}
```

**Response (200):**
```json
{
  "message": "Instructor request rejected",
  "authorization": { ... }
}
```

---

#### 61. List Authorized Instructors
**GET** `/acc/instructors`

**Response (200):**
```json
{
  "instructors": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "status": "active"
    }
  ]
}
```

---

### Courses Management

#### 62. Create Course
**POST** `/acc/courses`

Create a new course. You can optionally include pricing information to set the course price at creation time.

**Request Body (with pricing - optional):**
```json
{
  "sub_category_id": 1,
  "name": "Advanced Safety Training",
  "name_ar": "تدريب السلامة المتقدم",
  "code": "AST-101",
  "description": "Comprehensive safety training course covering all aspects of workplace safety",
  "duration_hours": 40,
  "level": "intermediate",
  "status": "active",
  "pricing": {
    "base_price": 500.00,
    "currency": "USD",
    "effective_from": "2024-01-01",
    "effective_to": "2024-12-31"
  }
}
```

**Request Body (without pricing):**
```json
{
  "sub_category_id": 1,
  "name": "Advanced Safety Training",
  "name_ar": "تدريب السلامة المتقدم",
  "code": "AST-101",
  "description": "Comprehensive safety training course",
  "duration_hours": 40,
  "level": "intermediate",
  "status": "active"
}
```

**Response (201):**
```json
{
  "message": "Course created successfully with pricing",
  "course": {
    "id": 1,
    "sub_category_id": 1,
    "acc_id": 1,
    "name": "Advanced Safety Training",
    "name_ar": "تدريب السلامة المتقدم",
    "code": "AST-101",
    "description": "Comprehensive safety training course covering all aspects of workplace safety",
    "duration_hours": 40,
    "level": "intermediate",
    "status": "active",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z",
    "current_price": {
      "base_price": "500.00",
      "currency": "USD",
      "group_commission_percentage": "15.00",
      "training_center_commission_percentage": "10.00",
      "instructor_commission_percentage": "5.00",
      "effective_from": "2024-01-01",
      "effective_to": "2024-12-31"
    },
    "sub_category": {
      "id": 1,
      "name": "Fire Safety",
      "name_ar": "سلامة الحريق",
      "category": {
        "id": 1,
        "name": "Safety Training",
        "name_ar": "تدريب السلامة"
      }
    }
  }
}
```

**Field Requirements:**

**Required Course Fields:**
- `sub_category_id` (integer) - Sub category ID
- `name` (string) - Course name
- `code` (string) - Unique course code
- `duration_hours` (integer) - Course duration in hours
- `level` (string) - Course level: `beginner`, `intermediate`, or `advanced`
- `status` (string) - Course status: `active`, `inactive`, or `archived`

**Optional Course Fields:**
- `name_ar` (string) - Course name in Arabic
- `description` (string) - Course description

**Optional Pricing Object:**
If you include `pricing`, all pricing fields are required:
- `pricing.base_price` (numeric) - Base price per certificate code
- `pricing.currency` (string) - Currency code (3 characters, e.g., "USD")
- `pricing.effective_from` (date) - When pricing becomes effective
- `pricing.effective_to` (date, nullable) - When pricing expires (null for no expiration)

**Important:** Commission percentage is NOT set by ACC. It is automatically taken from the ACC's `commission_percentage` field, which is set by Group Admin when approving the ACC. When Training Centers purchase codes, the commission is automatically calculated using the ACC's commission percentage.

**Notes:**
- You can create a course without pricing and add it later using the update endpoint
- If pricing is provided, it will be created immediately and set as the active pricing
- The response includes all course details with current pricing (if set) and relationships
- `current_price` will be `null` if no pricing was provided or created
- The `group_commission_percentage` in the response comes from ACC's `commission_percentage` field (set by Group Admin)

---

#### 63. List Courses
**GET** `/acc/courses`

Get all courses for the authenticated ACC with full details and current pricing.

**Query Parameters (All Optional):**
- `sub_category_id` (integer) - Filter by sub category ID
- `status` (string) - Filter by status: `active`, `inactive`, or `archived`
- `level` (string) - Filter by level: `beginner`, `intermediate`, or `advanced`
- `search` (string) - Search in course name, Arabic name, code, or description

**Response (200):**
```json
{
  "courses": [
    {
      "id": 1,
      "sub_category_id": 1,
      "acc_id": 1,
      "name": "Advanced Safety Training",
      "name_ar": "تدريب السلامة المتقدم",
      "code": "AST-101",
      "description": "Comprehensive advanced safety training course covering all aspects of workplace safety",
      "duration_hours": 40,
      "level": "intermediate",
      "status": "active",
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z",
      "current_price": {
        "base_price": "500.00",
        "currency": "USD",
        "group_commission_percentage": "15.00",
        "effective_from": "2024-01-01",
        "effective_to": "2024-12-31"
      },
      "sub_category": {
        "id": 1,
        "name": "Fire Safety",
        "name_ar": "سلامة الحريق",
        "category": {
          "id": 1,
          "name": "Safety Training",
          "name_ar": "تدريب السلامة"
        }
      }
    }
  ]
}
```

**Note:** The `current_price` field will be `null` if no active pricing is set for the course. It contains:
- `base_price`: The current price per certificate code
- `currency`: Currency code (default: USD)
- `group_commission_percentage`: Commission percentage for Group Admin (automatically taken from ACC's commission_percentage, set by Group Admin when approving ACC)
- `effective_from`: When the pricing becomes effective
- `effective_to`: When the pricing expires (null if no expiration)

**Important:** Commission percentage is NOT set by ACC. It is automatically taken from the ACC's `commission_percentage` field, which is set by Group Admin when approving the ACC.

**Example Requests:**
```javascript
// Get all courses
GET /acc/courses

// Filter by status
GET /acc/courses?status=active

// Filter by sub category
GET /acc/courses?sub_category_id=1

// Search courses
GET /acc/courses?search=safety

// Combined filters
GET /acc/courses?status=active&level=intermediate&sub_category_id=1
```

---

#### 64. Get Course Details
**GET** `/acc/courses/{id}`

**Response (200):**
```json
{
  "course": {
    "id": 1,
    "name": "Advanced Safety Training",
    "code": "AST-101",
    "description": "...",
    "duration_hours": 40,
    "level": "intermediate",
    "status": "active",
    "pricing": {
      "base_price": 500.00,
      "currency": "USD"
    }
  }
}
```

---

#### 65. Update Course
**PUT** `/acc/courses/{id}`

Update course details and/or pricing in a single request. All fields are optional - only include the fields you want to update.

**Request Body (All fields optional):**
```json
{
  "sub_category_id": 1,
  "name": "Updated Course Name",
  "name_ar": "اسم الدورة المحدث",
  "code": "UPD-101",
  "description": "Updated description",
  "duration_hours": 50,
  "level": "advanced",
  "status": "active",
  "pricing": {
    "base_price": 550.00,
    "currency": "USD",
    "group_commission_percentage": 15.0,
    "training_center_commission_percentage": 10.0,
    "instructor_commission_percentage": 5.0,
    "effective_from": "2024-01-01",
    "effective_to": "2024-12-31"
  }
}
```

**Note:** 
- You can update course details only, pricing only, or both together
- If `pricing` is provided, it will update the existing active pricing or create a new one if none exists
- All pricing fields are required when `pricing` object is included
- **Commission percentage is NOT included in pricing** - It is automatically taken from ACC's `commission_percentage` field (set by Group Admin)

**Response (200):**
```json
{
  "message": "Course updated successfully",
  "course": {
    "id": 1,
    "sub_category_id": 1,
    "acc_id": 1,
    "name": "Updated Course Name",
    "name_ar": "اسم الدورة المحدث",
    "code": "UPD-101",
    "description": "Updated description",
    "duration_hours": 50,
    "level": "advanced",
    "status": "active",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-12-19T15:45:00.000000Z",
    "current_price": {
      "base_price": "500.00",
      "currency": "USD",
      "group_commission_percentage": "15.00",
      "training_center_commission_percentage": "10.00",
      "instructor_commission_percentage": "5.00",
      "effective_from": "2024-01-01",
      "effective_to": "2024-12-31"
    },
    "sub_category": {
      "id": 1,
      "name": "Fire Safety",
      "name_ar": "سلامة الحريق",
      "category": {
        "id": 1,
        "name": "Safety Training",
        "name_ar": "تدريب السلامة"
      }
    }
  }
}
```

**Note:** The response includes all course details with current pricing and relationships, similar to the list endpoint.

---

#### 66. Delete Course
**DELETE** `/acc/courses/{id}`

**Response (200):**
```json
{
  "message": "Course deleted successfully"
}
```

---

#### 67. Set Course Pricing
**POST** `/acc/courses/{id}/pricing`

**Request Body:**
```json
{
  "base_price": 500.00,
  "currency": "USD",
  "group_commission_percentage": 10.0,
  "training_center_commission_percentage": 15.0,
  "instructor_commission_percentage": 5.0,
  "effective_from": "2024-01-15",
  "effective_to": null
}
```

**Response (201):**
```json
{
  "message": "Pricing set successfully",
  "pricing": {
    "id": 1,
    "course_id": 1,
    "base_price": 500.00,
    "currency": "USD"
  }
}
```

---

#### 68. Update Course Pricing
**PUT** `/acc/courses/{id}/pricing`

**Request Body:**
```json
{
  "base_price": 550.00,
  "effective_to": "2024-12-31"
}
```

**Response (200):**
```json
{
  "message": "Pricing updated successfully",
  "pricing": { ... }
}
```

---

### Certificate Templates

#### 69. Create Certificate Template
**POST** `/acc/certificate-templates`

**Request Body:**
```json
{
  "category_id": 1,
  "name": "Safety Certificate Template",
  "template_html": "<html>...</html>",
  "template_variables": {
    "trainee_name": "{{trainee_name}}",
    "course_name": "{{course_name}}",
    "issue_date": "{{issue_date}}"
  },
  "background_image_url": "/templates/background.jpg",
  "logo_positions": {
    "acc_logo": {"x": 100, "y": 50},
    "group_logo": {"x": 500, "y": 50}
  },
  "signature_positions": {
    "acc_signature": {"x": 200, "y": 600},
    "instructor_signature": {"x": 400, "y": 600}
  },
  "status": "active"
}
```

**Response (201):**
```json
{
  "template": {
    "id": 1,
    "name": "Safety Certificate Template",
    "status": "active"
  }
}
```

---

#### 70. List Certificate Templates
**GET** `/acc/certificate-templates`

**Response (200):**
```json
{
  "templates": [
    {
      "id": 1,
      "name": "Safety Certificate Template",
      "category_id": 1,
      "status": "active"
    }
  ]
}
```

---

#### 71. Get Template Details
**GET** `/acc/certificate-templates/{id}`

**Response (200):**
```json
{
  "template": {
    "id": 1,
    "name": "Safety Certificate Template",
    "template_html": "<html>...</html>",
    "template_variables": {},
    "status": "active"
  }
}
```

---

#### 72. Update Template
**PUT** `/acc/certificate-templates/{id}`

**Request Body:**
```json
{
  "name": "Updated Template Name",
  "template_html": "<html>...</html>",
  "status": "active"
}
```

**Response (200):**
```json
{
  "message": "Template updated successfully",
  "template": { ... }
}
```

---

#### 73. Delete Template
**DELETE** `/acc/certificate-templates/{id}`

**Response (200):**
```json
{
  "message": "Template deleted successfully"
}
```

---

#### 74. Preview Template
**POST** `/acc/certificate-templates/{id}/preview`

**Request Body:**
```json
{
  "sample_data": {
    "trainee_name": "John Doe",
    "course_name": "Advanced Safety Training",
    "issue_date": "2024-01-15"
  }
}
```

**Response (200):**
```json
{
  "preview_url": "/templates/preview/xxx.pdf"
}
```

---

### Discount Codes

#### 75. Create Discount Code
**POST** `/acc/discount-codes`

**Request Body (Time-Limited):**
```json
{
  "code": "SAVE20",
  "discount_type": "time_limited",
  "discount_percentage": 20.00,
  "applicable_course_ids": [1, 2, 3],
  "start_date": "2024-01-15",
  "end_date": "2024-02-15",
  "total_quantity": null,
  "status": "active"
}
```

**Request Body (Quantity-Based):**
```json
{
  "code": "WELCOME10",
  "discount_type": "quantity_based",
  "discount_percentage": 10.00,
  "applicable_course_ids": [1, 2, 3],
  "start_date": null,
  "end_date": null,
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
    "discount_percentage": 20.00,
    "status": "active"
  }
}
```

---

#### 76. List Discount Codes
**GET** `/acc/discount-codes`

**Response (200):**
```json
{
  "discount_codes": [
    {
      "id": 1,
      "code": "SAVE20",
      "discount_type": "time_limited",
      "discount_percentage": 20.00,
      "status": "active"
    }
  ]
}
```

---

#### 77. Get Discount Code Details
**GET** `/acc/discount-codes/{id}`

**Response (200):**
```json
{
  "discount_code": {
    "id": 1,
    "code": "SAVE20",
    "discount_type": "time_limited",
    "discount_percentage": 20.00,
    "applicable_course_ids": [1, 2, 3],
    "start_date": "2024-01-15",
    "end_date": "2024-02-15",
    "status": "active"
  }
}
```

---

#### 78. Update Discount Code
**PUT** `/acc/discount-codes/{id}`

**Request Body:**
```json
{
  "discount_percentage": 25.00,
  "status": "inactive"
}
```

**Response (200):**
```json
{
  "message": "Discount code updated successfully",
  "discount_code": { ... }
}
```

---

#### 79. Delete Discount Code
**DELETE** `/acc/discount-codes/{id}`

**Response (200):**
```json
{
  "message": "Discount code deleted successfully"
}
```

---

#### 80. Validate Discount Code
**POST** `/acc/discount-codes/validate`

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
  "discount_percentage": 20.00,
  "message": "Discount code is valid"
}
```

**Response (422):**
```json
{
  "valid": false,
  "message": "Discount code expired"
}
```

---

### Materials Management

#### 81. Create Material
**POST** `/acc/materials`

**Request Body:**
```json
{
  "course_id": 1,
  "material_type": "pdf", // pdf, video, presentation, package
  "name": "Safety Manual",
  "description": "Comprehensive safety manual",
  "price": 50.00,
  "file_url": "/materials/safety-manual.pdf",
  "preview_url": "/materials/safety-manual-preview.pdf",
  "status": "active"
}
```

**Response (201):**
```json
{
  "material": {
    "id": 1,
    "name": "Safety Manual",
    "material_type": "pdf",
    "price": 50.00,
    "status": "active"
  }
}
```

---

#### 82. List Materials
**GET** `/acc/materials`

**Query Parameters:**
- `course_id` - Filter by course
- `material_type` - Filter by type
- `status` - Filter by status

**Response (200):**
```json
{
  "materials": [
    {
      "id": 1,
      "name": "Safety Manual",
      "material_type": "pdf",
      "price": 50.00,
      "status": "active"
    }
  ]
}
```

---

#### 83. Get Material Details
**GET** `/acc/materials/{id}`

**Response (200):**
```json
{
  "material": {
    "id": 1,
    "name": "Safety Manual",
    "material_type": "pdf",
    "price": 50.00,
    "file_url": "/materials/safety-manual.pdf",
    "status": "active"
  }
}
```

---

#### 84. Update Material
**PUT** `/acc/materials/{id}`

**Request Body:**
```json
{
  "name": "Updated Material Name",
  "price": 55.00,
  "status": "active"
}
```

**Response (200):**
```json
{
  "message": "Material updated successfully",
  "material": { ... }
}
```

---

#### 85. Delete Material
**DELETE** `/acc/materials/{id}`

**Response (200):**
```json
{
  "message": "Material deleted successfully"
}
```

---

### Certificates & Classes

#### 86. List Certificates
**GET** `/acc/certificates`

**Query Parameters:**
- `status` - Filter by status
- `start_date` - Start date filter
- `end_date` - End date filter

**Response (200):**
```json
{
  "certificates": [
    {
      "id": 1,
      "certificate_number": "CERT-2024-001",
      "trainee_name": "John Doe",
      "course": "Advanced Safety Training",
      "issue_date": "2024-01-15",
      "status": "valid"
    }
  ]
}
```

---

#### 87. List Classes
**GET** `/acc/classes`

**Response (200):**
```json
{
  "classes": [
    {
      "id": 1,
      "training_center": "ABC Training Center",
      "course": "Advanced Safety Training",
      "instructor": "John Doe",
      "start_date": "2024-01-15",
      "end_date": "2024-01-20",
      "status": "completed"
    }
  ]
}
```

---

### Financial

#### 88. Get Financial Transactions
**GET** `/acc/financial/transactions`

**Response (200):**
```json
{
  "transactions": [
    {
      "id": 1,
      "transaction_type": "code_purchase",
      "amount": 1000.00,
      "status": "completed",
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ]
}
```

---

#### 89. Get Settlements
**GET** `/acc/financial/settlements`

**Response (200):**
```json
{
  "settlements": [
    {
      "id": 1,
      "settlement_month": "2024-01",
      "total_revenue": 10000.00,
      "group_commission_amount": 1500.00,
      "status": "pending",
      "request_date": "2024-02-01"
    }
  ]
}
```

---

## Training Center Endpoints
*Requires role: `training_center_admin`*

### Dashboard

#### 90. Get Training Center Dashboard
**GET** `/training-center/dashboard`

**Response (200):**
```json
{
  "authorizations": [
    {
      "acc_id": 1,
      "acc_name": "ABC Accreditation Body",
      "status": "approved"
    }
  ],
  "code_inventory": {
    "total": 100,
    "available": 75,
    "used": 25
  },
  "active_classes": 5,
  "upcoming_classes": 3,
  "wallet_balance": 5000.00,
  "recent_purchases": [],
  "pending_instructor_approvals": 2,
  "financial_summary": {
    "total_spent": 10000.00,
    "total_earned": 5000.00
  }
}
```

---

### ACC Management

#### 91. List Available ACCs
**GET** `/training-center/accs`

**Response (200):**
```json
{
  "accs": [
    {
      "id": 1,
      "name": "ABC Accreditation Body",
      "email": "info@abc.com",
      "country": "United States",
      "status": "active"
    }
  ]
}
```

---

#### 92. Request Authorization from ACC
**POST** `/training-center/accs/{id}/request-authorization`

**Content-Type:** `multipart/form-data`

**Request Body (Form Data):**
- `documents[0][type]` (string, required): Document type - one of: `license`, `certificate`, `registration`, `other`
- `documents[0][file]` (file, required): Document file (PDF, DOC, DOCX, JPG, JPEG, PNG) - Max 10MB
- `documents[1][type]` (string, optional): Second document type
- `documents[1][file]` (file, optional): Second document file
- `additional_info` (string, optional): Additional information about the training center

**JavaScript/FormData Example:**
```javascript
const formData = new FormData();

// Method 1: Using array notation (Recommended)
formData.append('documents[0][type]', 'license');
formData.append('documents[0][file]', fileInput.files[0]); // File object
formData.append('documents[1][type]', 'certificate');
formData.append('documents[1][file]', fileInput2.files[0]);
formData.append('additional_info', 'Additional information');

// Method 2: Alternative using loop (if you have multiple files)
const files = [
  { type: 'license', file: fileInput.files[0] },
  { type: 'certificate', file: fileInput2.files[0] }
];
files.forEach((item, index) => {
  formData.append(`documents[${index}][type]`, item.type);
  formData.append(`documents[${index}][file]`, item.file);
});

fetch('/api/training-center/accs/1/request-authorization', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
    // IMPORTANT: Don't set Content-Type header - browser will automatically set it with boundary for multipart/form-data
  },
  body: formData
})
.then(response => response.json())
.then(data => {
  if (response.ok) {
    console.log('Success:', data);
  } else {
    console.error('Error:', data);
  }
})
.catch(error => {
  console.error('Network error:', error);
});
```

**cURL Example:**
```bash
curl -X POST "https://your-domain.com/api/training-center/accs/1/request-authorization" \
  -H "Authorization: Bearer {token}" \
  -F "documents[0][type]=license" \
  -F "documents[0][file]=@/path/to/license.pdf" \
  -F "documents[1][type]=certificate" \
  -F "documents[1][file]=@/path/to/certificate.pdf" \
  -F "additional_info=Additional information"
```

**Response (201):**
```json
{
  "message": "Authorization request submitted successfully",
  "authorization": {
    "id": 1,
    "acc_id": 1,
    "status": "pending",
    "request_date": "2024-01-15T10:30:00.000000Z",
    "documents_json": [
      {
        "type": "license",
        "url": "/storage/authorization/1/1/abc123.pdf",
        "original_name": "license.pdf",
        "mime_type": "application/pdf",
        "size": 123456
      }
    ]
  }
}
```

**Error Response (422):**
```json
{
  "message": "No valid documents uploaded. Please ensure files are uploaded correctly.",
  "hint": "Use FormData with structure: documents[0][type]=license&documents[0][file]=<file>"
}
```

**Common Errors:**

1. **400 Bad Request - "Authorization request already exists"**
   - This ACC authorization request has already been submitted
   - Check existing authorizations first

2. **422 Validation Error - "No valid documents uploaded"**
   - Files were not properly included in the request
   - Ensure files are attached using FormData, not JSON
   - Verify file format is one of: PDF, DOC, DOCX, JPG, JPEG, PNG
   - File size must be less than 10MB

3. **500 Server Error**
   - Check server logs for details
   - Ensure storage directory has write permissions
   - Verify storage link is created: `php artisan storage:link`

**Troubleshooting Tips:**
- Always use `FormData` for file uploads, never JSON
- Don't manually set `Content-Type` header - let the browser set it
- Verify files exist and are valid before uploading
- Check that file extensions match allowed types
- Ensure authentication token is valid and included
```

---

#### 93. Get Authorization Status
**GET** `/training-center/authorizations`

**Response (200):**
```json
{
  "authorizations": [
    {
      "id": 1,
      "acc": {
        "id": 1,
        "name": "ABC Accreditation Body"
      },
      "status": "pending",
      "request_date": "2024-01-15",
      "reviewed_at": null,
      "rejection_reason": null,
      "return_comment": null
    }
  ]
}
```

---

### Instructors Management

#### 94. Create Instructor
**POST** `/training-center/instructors`

Add a new instructor. CV must be uploaded as a PDF file (not a URL string).

**Important:** This endpoint requires `multipart/form-data` content type for file upload.

**Request Body (Form Data):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `first_name` | string | Yes | Instructor's first name |
| `last_name` | string | Yes | Instructor's last name |
| `email` | string | Yes | Instructor's email (must be unique) |
| `phone` | string | Yes | Instructor's phone number |
| `id_number` | string | Yes | Instructor's ID number (must be unique) |
| `cv` | file | No | CV file (PDF only, max 10MB) |
| `certificates_json` | array | No | Array of certificates |
| `specializations` | array | No | Array of specializations |

**Example Request (JavaScript - FormData):**
```javascript
const formData = new FormData();
formData.append('first_name', 'John');
formData.append('last_name', 'Doe');
formData.append('email', 'john@example.com');
formData.append('phone', '+1234567890');
formData.append('id_number', 'ID123456');
formData.append('cv', cvFile); // File object from input[type="file"]
formData.append('specializations[]', 'Safety');
formData.append('specializations[]', 'First Aid');

const response = await fetch('/api/training-center/instructors', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
    // Don't set Content-Type - browser will set it with boundary
  },
  body: formData
});
```

**Response (201):**
```json
{
  "instructor": {
    "id": 1,
    "training_center_id": 2,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "id_number": "ID123456",
    "cv_url": "/storage/instructors/cv/1234567890_2_john_doe_cv.pdf",
    "certificates_json": null,
    "specializations": ["Safety", "First Aid"],
    "status": "pending",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

**CV File Requirements:**
- File type: PDF only (`.pdf`)
- Maximum size: 10MB
- Storage: Files are stored in `storage/app/public/instructors/cv/`
- Access: CV URL is returned in `cv_url` field

---

#### 95. List Instructors
**GET** `/training-center/instructors`

**Response (200):**
```json
{
  "instructors": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "status": "active"
    }
  ]
}
```

---

#### 96. Get Instructor Details
**GET** `/training-center/instructors/{id}`

**Response (200):**
```json
{
  "instructor": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "certificates_json": [],
    "specializations": [],
    "status": "active"
  }
}
```

---

#### 97. Update Instructor
**PUT** `/training-center/instructors/{id}`

Update instructor details. All fields are optional. CV must be uploaded as a PDF file if updating.

**Important:** This endpoint requires `multipart/form-data` content type when uploading CV file.

**Request Body (Form Data):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `first_name` | string | No | Instructor's first name |
| `last_name` | string | No | Instructor's last name |
| `email` | string | No | Instructor's email (must be unique if changed) |
| `phone` | string | No | Instructor's phone number |
| `id_number` | string | No | Instructor's ID number (must be unique if changed) |
| `cv` | file | No | New CV file (PDF only, max 10MB). Old CV will be deleted. |
| `certificates_json` | array | No | Array of certificates |
| `specializations` | array | No | Array of specializations |

**Example Request (JavaScript - FormData):**
```javascript
const formData = new FormData();
formData.append('first_name', 'John Updated');
formData.append('phone', '+1234567891');
if (newCvFile) {
  formData.append('cv', newCvFile); // File object
}

const response = await fetch(`/api/training-center/instructors/${instructorId}`, {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
});
```

**Response (200):**
```json
{
  "message": "Instructor updated successfully",
  "instructor": {
    "id": 1,
    "first_name": "John Updated",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+1234567891",
    "cv_url": "/storage/instructors/cv/1234567891_2_john_updated_cv.pdf",
    "status": "pending"
  }
}
```

**Note:** When a new CV file is uploaded, the old CV file is automatically deleted from storage.

---

#### 98. Delete Instructor
**DELETE** `/training-center/instructors/{id}`

**Response (200):**
```json
{
  "message": "Instructor deleted successfully"
}
```

---

#### 99. Request Instructor Authorization
**POST** `/training-center/instructors/{id}/request-authorization`

**Request Body:**
```json
{
  "acc_id": 1,
  "course_ids": [1, 2, 3],
  "documents_json": [
    {
      "type": "certificate",
      "url": "/documents/cert.pdf"
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
    "instructor_id": 1,
    "acc_id": 1,
    "status": "pending"
  }
}
```

---

### Certificate Codes

#### 99. Create Payment Intent for Code Purchase (Stripe)
**POST** `/training-center/codes/payment-intent`

Create a Stripe payment intent for purchasing certificate codes. This endpoint calculates the total amount (including discounts) and returns a Stripe client secret for frontend payment processing.

**Request Body:**
```json
{
  "acc_id": 3,
  "course_id": 5,
  "quantity": 10,
  "discount_code": "SAVE20"
}
```

**Response (200):**
```json
{
  "success": true,
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx",
  "amount": 3600.00,
  "currency": "USD",
  "total_amount": "4000.00",
  "discount_amount": "400.00",
  "final_amount": "3600.00",
  "unit_price": "400.00",
  "quantity": 10
}
```

**Frontend Implementation Example:**
```javascript
// Step 1: Create payment intent
const response = await fetch('/api/training-center/codes/payment-intent', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    acc_id: 3,
    course_id: 5,
    quantity: 10,
    discount_code: 'SAVE20'
  })
});

const { client_secret, payment_intent_id } = await response.json();

// Step 2: Initialize Stripe
const stripe = Stripe('YOUR_PUBLISHABLE_KEY'); // Get from /api/stripe/config

// Step 3: Confirm payment with Stripe
const { error, paymentIntent } = await stripe.confirmCardPayment(client_secret, {
  payment_method: {
    card: cardElement, // Stripe card element
  }
});

if (error) {
  // Handle error
  console.error(error);
} else if (paymentIntent.status === 'succeeded') {
  // Step 4: Complete purchase
  await purchaseCodes(acc_id, course_id, quantity, payment_intent_id);
}
```

**Error Responses:**
- `400` - Stripe not configured
- `403` - Training Center not authorized for ACC
- `404` - ACC, course, or pricing not found
- `422` - Invalid discount code or validation errors

---

#### 100. Purchase Certificate Codes
**POST** `/training-center/codes/purchase`

**Request Body (Wallet Payment):**
```json
{
  "acc_id": 1,
  "course_id": 1,
  "quantity": 10,
  "discount_code": "SAVE20", // optional
  "payment_method": "wallet"
}
```

**Request Body (Stripe Credit Card Payment):**
```json
{
  "acc_id": 1,
  "course_id": 1,
  "quantity": 10,
  "discount_code": "SAVE20", // optional
  "payment_method": "credit_card",
  "payment_intent_id": "pi_xxx" // Required for credit card - obtained from /codes/payment-intent
}
```

**Important:** For credit card payments:
1. First call `/codes/payment-intent` to create payment intent and get `client_secret`
2. Use Stripe.js to complete payment on frontend
3. Then call this endpoint with the `payment_intent_id` from step 1

**Response (201):**
```json
{
  "message": "Codes purchased successfully",
  "batch": {
    "id": 1,
    "quantity": 10,
    "total_amount": 4000.00, // after discount
    "codes": [
      {
        "id": 1,
        "code": "CERT-ABC123",
        "status": "available"
      }
    ]
  }
}
```

---

#### 101. Get Code Inventory
**GET** `/training-center/codes/inventory`

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
      "code": "CERT-ABC123",
      "acc": "ABC Accreditation Body",
      "course": "Advanced Safety Training",
      "status": "available",
      "purchased_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "summary": {
    "total": 100,
    "available": 75,
    "used": 25
  }
}
```

---

#### 102. Get Code Batches
**GET** `/training-center/codes/batches`

**Response (200):**
```json
{
  "batches": [
    {
      "id": 1,
      "acc": "ABC Accreditation Body",
      "course": "Advanced Safety Training",
      "quantity": 10,
      "total_amount": 4000.00,
      "purchase_date": "2024-01-15",
      "payment_method": "wallet"
    }
  ]
}
```

---

### Wallet Management

#### 103. Add Funds to Wallet
**POST** `/training-center/wallet/add-funds`

**Request Body:**
```json
{
  "amount": 1000.00,
  "payment_method": "credit_card",
  "payment_intent_id": "pi_xxx" // if using Stripe
}
```

**Response (200):**
```json
{
  "message": "Funds added successfully",
  "wallet": {
    "balance": 6000.00,
    "currency": "USD"
  },
  "transaction": {
    "id": 1,
    "amount": 1000.00,
    "status": "completed"
  }
}
```

---

#### 104. Get Wallet Balance
**GET** `/training-center/wallet/balance`

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

#### 105. Get Wallet Transactions
**GET** `/training-center/wallet/transactions`

**Query Parameters:**
- `type` - Filter by type (deposit, withdrawal)
- `start_date` - Start date
- `end_date` - End date

**Response (200):**
```json
{
  "transactions": [
    {
      "id": 1,
      "type": "deposit",
      "amount": 1000.00,
      "status": "completed",
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ]
}
```

---

### Classes Management

#### 106. Create Class
**POST** `/training-center/classes`

**Request Body:**
```json
{
  "course_id": 1,
  "class_id": 1, // from admin classes
  "instructor_id": 1,
  "start_date": "2024-02-01",
  "end_date": "2024-02-05",
  "schedule_json": {
    "days": ["Monday", "Wednesday", "Friday"],
    "time": "09:00 - 17:00",
    "duration": "8 hours"
  },
  "max_capacity": 30,
  "location": "physical", // or "online"
  "location_details": "123 Main St, New York"
}
```

**Response (201):**
```json
{
  "class": {
    "id": 1,
    "course": "Advanced Safety Training",
    "instructor": "John Doe",
    "start_date": "2024-02-01",
    "end_date": "2024-02-05",
    "status": "scheduled",
    "max_capacity": 30,
    "enrolled_count": 0
  }
}
```

---

#### 107. List Classes
**GET** `/training-center/classes`

**Query Parameters:**
- `status` - Filter by status
- `course_id` - Filter by course
- `instructor_id` - Filter by instructor

**Response (200):**
```json
{
  "classes": [
    {
      "id": 1,
      "course": "Advanced Safety Training",
      "instructor": "John Doe",
      "start_date": "2024-02-01",
      "end_date": "2024-02-05",
      "status": "scheduled",
      "max_capacity": 30,
      "enrolled_count": 15
    }
  ]
}
```

---

#### 108. Get Class Details
**GET** `/training-center/classes/{id}`

**Response (200):**
```json
{
  "class": {
    "id": 1,
    "course": "Advanced Safety Training",
    "instructor": "John Doe",
    "start_date": "2024-02-01",
    "end_date": "2024-02-05",
    "schedule_json": {},
    "status": "scheduled",
    "max_capacity": 30,
    "enrolled_count": 15,
    "location": "physical",
    "location_details": "123 Main St"
  }
}
```

---

#### 109. Update Class
**PUT** `/training-center/classes/{id}`

**Request Body:**
```json
{
  "start_date": "2024-02-05",
  "end_date": "2024-02-09",
  "max_capacity": 35
}
```

**Response (200):**
```json
{
  "message": "Class updated successfully",
  "class": { ... }
}
```

---

#### 110. Delete Class
**DELETE** `/training-center/classes/{id}`

**Response (200):**
```json
{
  "message": "Class deleted successfully"
}
```

---

#### 111. Mark Class as Complete
**PUT** `/training-center/classes/{id}/complete`

**Response (200):**
```json
{
  "message": "Class marked as complete",
  "class": {
    "id": 1,
    "status": "completed"
  }
}
```

---

### Certificates

#### 112. Generate Certificate
**POST** `/training-center/certificates/generate`

**Request Body:**
```json
{
  "training_class_id": 1,
  "code_id": 1, // from available codes inventory
  "trainee_name": "Jane Smith",
  "trainee_id_number": "ID123456",
  "issue_date": "2024-02-05",
  "expiry_date": "2026-02-05"
}
```

**Response (201):**
```json
{
  "message": "Certificate generated successfully",
  "certificate": {
    "id": 1,
    "certificate_number": "CERT-2024-001",
    "trainee_name": "Jane Smith",
    "verification_code": "VERIFY-ABC123",
    "certificate_pdf_url": "/certificates/cert-2024-001.pdf",
    "status": "valid"
  }
}
```

---

#### 113. List Certificates
**GET** `/training-center/certificates`

**Query Parameters:**
- `training_class_id` - Filter by class
- `status` - Filter by status
- `start_date` - Start date filter
- `end_date` - End date filter

**Response (200):**
```json
{
  "certificates": [
    {
      "id": 1,
      "certificate_number": "CERT-2024-001",
      "trainee_name": "Jane Smith",
      "course": "Advanced Safety Training",
      "issue_date": "2024-02-05",
      "status": "valid"
    }
  ]
}
```

---

#### 114. Get Certificate Details
**GET** `/training-center/certificates/{id}`

**Response (200):**
```json
{
  "certificate": {
    "id": 1,
    "certificate_number": "CERT-2024-001",
    "trainee_name": "Jane Smith",
    "course": "Advanced Safety Training",
    "class": "Class 1",
    "instructor": "John Doe",
    "issue_date": "2024-02-05",
    "expiry_date": "2026-02-05",
    "verification_code": "VERIFY-ABC123",
    "certificate_pdf_url": "/certificates/cert-2024-001.pdf",
    "status": "valid"
  }
}
```

---

### Marketplace

#### 115. Browse Materials
**GET** `/training-center/marketplace/materials`

**Query Parameters:**
- `acc_id` - Filter by ACC
- `course_id` - Filter by course
- `material_type` - Filter by type
- `search` - Search term

**Response (200):**
```json
{
  "materials": [
    {
      "id": 1,
      "acc": "ABC Accreditation Body",
      "course": "Advanced Safety Training",
      "name": "Safety Manual",
      "material_type": "pdf",
      "price": 50.00,
      "preview_url": "/materials/preview.pdf",
      "status": "active"
    }
  ]
}
```

---

#### 116. Get Material Details
**GET** `/training-center/marketplace/materials/{id}`

**Response (200):**
```json
{
  "material": {
    "id": 1,
    "acc": "ABC Accreditation Body",
    "name": "Safety Manual",
    "description": "Comprehensive safety manual",
    "material_type": "pdf",
    "price": 50.00,
    "preview_url": "/materials/preview.pdf",
    "file_url": "/materials/file.pdf" // only after purchase
  }
}
```

---

#### 117. Purchase from Marketplace
**POST** `/training-center/marketplace/purchase`

**Request Body:**
```json
{
  "purchase_type": "material", // material, course, package
  "item_id": 1,
  "acc_id": 1,
  "payment_method": "wallet" // or "credit_card"
}
```

**Response (200):**
```json
{
  "message": "Purchase successful",
  "purchase": {
    "id": 1,
    "purchase_type": "material",
    "item_id": 1,
    "amount": 50.00,
    "purchased_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

#### 118. Get Library (Purchased Items)
**GET** `/training-center/library`

**Query Parameters:**
- `type` - Filter by type (material, course, package)

**Response (200):**
```json
{
  "library": [
    {
      "id": 1,
      "purchase_type": "material",
      "item": {
        "id": 1,
        "name": "Safety Manual",
        "material_type": "pdf",
        "file_url": "/materials/file.pdf"
      },
      "purchased_at": "2024-01-15T10:30:00.000000Z"
    }
  ]
}
```

---

## Instructor Endpoints
*Requires role: `instructor`*

### Dashboard

#### 119. Get Instructor Dashboard
**GET** `/instructor/dashboard`

**Response (200):**
```json
{
  "assigned_classes": [
    {
      "id": 1,
      "course": "Advanced Safety Training",
      "start_date": "2024-02-01",
      "end_date": "2024-02-05",
      "status": "scheduled",
      "enrolled_count": 15,
      "max_capacity": 30
    }
  ],
  "upcoming_classes": 3,
  "completed_classes": 10,
  "earnings": {
    "total": 5000.00,
    "pending": 1000.00,
    "paid": 4000.00
  },
  "available_materials": [],
  "notifications": []
}
```

---

### Classes Management

#### 120. List Assigned Classes
**GET** `/instructor/classes`

**Query Parameters:**
- `status` - Filter by status

**Response (200):**
```json
{
  "classes": [
    {
      "id": 1,
      "course": "Advanced Safety Training",
      "training_center": "ABC Training Center",
      "start_date": "2024-02-01",
      "end_date": "2024-02-05",
      "status": "scheduled",
      "enrolled_count": 15,
      "max_capacity": 30,
      "location": "physical",
      "location_details": "123 Main St"
    }
  ]
}
```

---

#### 121. Get Class Details
**GET** `/instructor/classes/{id}`

**Response (200):**
```json
{
  "class": {
    "id": 1,
    "course": "Advanced Safety Training",
    "training_center": "ABC Training Center",
    "start_date": "2024-02-01",
    "end_date": "2024-02-05",
    "schedule_json": {},
    "status": "scheduled",
    "enrolled_count": 15,
    "max_capacity": 30,
    "materials": []
  }
}
```

---

#### 122. Mark Class as Complete
**PUT** `/instructor/classes/{id}/mark-complete`

**Request Body:**
```json
{
  "completion_rate_percentage": 95,
  "notes": "Class completed successfully"
}
```

**Response (200):**
```json
{
  "message": "Class marked as complete",
  "class": {
    "id": 1,
    "status": "completed"
  }
}
```

---

### Materials

#### 123. Get Available Materials
**GET** `/instructor/materials`

**Response (200):**
```json
{
  "materials": [
    {
      "id": 1,
      "course": "Advanced Safety Training",
      "name": "Safety Manual",
      "material_type": "pdf",
      "file_url": "/materials/file.pdf"
    }
  ]
}
```

---

### Earnings

#### 124. Get Earnings
**GET** `/instructor/earnings`

**Query Parameters:**
- `start_date` - Start date
- `end_date` - End date

**Response (200):**
```json
{
  "earnings": [
    {
      "id": 1,
      "certificate": "CERT-2024-001",
      "amount": 25.00,
      "commission_percentage": 5.0,
      "status": "paid",
      "paid_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "summary": {
    "total": 5000.00,
    "pending": 1000.00,
    "paid": 4000.00
  }
}
```

---

## Integration Examples

### JavaScript/TypeScript Example

```typescript
// API Client Configuration
const API_BASE_URL = 'https://your-domain.com/api';

class APIClient {
  private token: string | null = null;

  constructor() {
    this.token = localStorage.getItem('auth_token');
  }

  private async request(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<any> {
    const url = `${API_BASE_URL}${endpoint}`;
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...options.headers,
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    const response = await fetch(url, {
      ...options,
      headers,
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Request failed');
    }

    return response.json();
  }

  // Authentication
  async login(email: string, password: string) {
    const response = await this.request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
    
    if (response.token) {
      this.token = response.token;
      localStorage.setItem('auth_token', response.token);
    }
    
    return response;
  }

  async logout() {
    await this.request('/auth/logout', { method: 'POST' });
    this.token = null;
    localStorage.removeItem('auth_token');
  }

  // Group Admin
  async getACCApplications() {
    return this.request('/admin/accs/applications');
  }

  async approveACCApplication(id: number) {
    return this.request(`/admin/accs/applications/${id}/approve`, {
      method: 'PUT',
    });
  }

  // Training Center
  async purchaseCodes(accId: number, courseId: number, quantity: number) {
    return this.request('/training-center/codes/purchase', {
      method: 'POST',
      body: JSON.stringify({
        acc_id: accId,
        course_id: courseId,
        quantity,
        payment_method: 'wallet',
      }),
    });
  }

  async generateCertificate(data: {
    training_class_id: number;
    code_id: number;
    trainee_name: string;
    trainee_id_number: string;
  }) {
    return this.request('/training-center/certificates/generate', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }
}

// Usage
const api = new APIClient();

// Login
await api.login('user@example.com', 'password123');

// Get dashboard
const dashboard = await api.getACCApplications();
```

---

### React Hook Example

```typescript
import { useState, useEffect } from 'react';

function useAPI() {
  const [token, setToken] = useState<string | null>(
    localStorage.getItem('auth_token')
  );

  const apiRequest = async (
    endpoint: string,
    options: RequestInit = {}
  ) => {
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...options.headers,
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
      ...options,
      headers,
    });

    if (!response.ok) {
      throw new Error('Request failed');
    }

    return response.json();
  };

  const login = async (email: string, password: string) => {
    const response = await apiRequest('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
    
    if (response.token) {
      setToken(response.token);
      localStorage.setItem('auth_token', response.token);
    }
    
    return response;
  };

  return { apiRequest, login, token };
}

// Usage in component
function Dashboard() {
  const { apiRequest } = useAPI();
  const [data, setData] = useState(null);

  useEffect(() => {
    apiRequest('/training-center/dashboard')
      .then(setData)
      .catch(console.error);
  }, []);

  return <div>{/* Render dashboard */}</div>;
}
```

---

## Error Handling

### Standard Error Response Format

```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### Error Handling Example

```typescript
try {
  const response = await api.login(email, password);
  // Handle success
} catch (error: any) {
  if (error.response?.status === 422) {
    // Validation errors
    const errors = error.response.data.errors;
    // Display errors to user
  } else if (error.response?.status === 401) {
    // Unauthorized - redirect to login
    window.location.href = '/login';
  } else if (error.response?.status === 403) {
    // Forbidden - insufficient permissions
    alert('You do not have permission to perform this action');
  } else {
    // Other errors
    alert(error.message || 'An error occurred');
  }
}
```

---

## Best Practices

### 1. Token Management
- Store token securely (localStorage for web, secure storage for mobile)
- Include token in all authenticated requests
- Handle token expiration gracefully
- Implement token refresh if available

### 2. Error Handling
- Always handle API errors
- Display user-friendly error messages
- Log errors for debugging
- Implement retry logic for network failures

### 3. Loading States
- Show loading indicators during API calls
- Disable buttons during requests
- Provide feedback for long-running operations

### 4. Data Validation
- Validate data on frontend before sending
- Handle validation errors from backend
- Provide real-time validation feedback

### 5. Security
- Never expose sensitive data in frontend
- Use HTTPS for all API calls
- Implement CSRF protection if required
- Sanitize user inputs

### 6. Performance
- Implement pagination for large lists
- Cache frequently accessed data
- Use debouncing for search inputs
- Optimize API calls (avoid unnecessary requests)

### 7. User Experience
- Provide clear error messages
- Show success confirmations
- Implement optimistic updates where appropriate
- Handle offline scenarios

---

## Common Workflows

### Workflow 1: Training Center Registration & Authorization

1. **Register** → `POST /auth/register` (role: `training_center_admin`)
2. **Browse ACCs** → `GET /training-center/accs`
3. **Request Authorization** → `POST /training-center/accs/{id}/request-authorization`
4. **Check Status** → `GET /training-center/authorizations`
5. **Wait for ACC Approval** (ACC uses their endpoints)
6. **Access Granted** → Can now purchase codes and create classes

### Workflow 2: Certificate Code Purchase & Generation

1. **Check Wallet** → `GET /training-center/wallet/balance`
2. **Add Funds** (if needed) → `POST /training-center/wallet/add-funds`
3. **Purchase Codes** → `POST /training-center/codes/purchase`
4. **Create Class** → `POST /training-center/classes`
5. **Wait for Class Completion** → Instructor marks complete
6. **Generate Certificate** → `POST /training-center/certificates/generate`
7. **View Certificate** → `GET /training-center/certificates/{id}`

### Workflow 3: ACC Subscription Management

1. **Register** → `POST /auth/register` (role: `acc_admin`)
2. **Wait for Group Approval** → Group admin approves
3. **Pay Subscription** → `POST /acc/subscription/payment`
4. **View Subscription** → `GET /acc/subscription`
5. **Renew Subscription** → `PUT /acc/subscription/renew`

---

## Support & Resources

### API Documentation
- Base URL: `https://your-domain.com/api`
- All endpoints return JSON
- Use Bearer token authentication

### Testing
- Use Postman collection for testing
- Test all endpoints before integration
- Verify error handling

### Questions?
Contact the backend development team for:
- API changes
- New endpoints
- Bug reports
- Integration support

---

**Last Updated**: December 2024
**API Version**: 1.0

