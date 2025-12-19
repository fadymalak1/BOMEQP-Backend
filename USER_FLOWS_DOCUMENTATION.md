# User Flows Documentation

This document describes the complete workflow for each user role in the BOMEQP Accreditation Management System.

---

## Table of Contents

1. [Group Admin Flow](#1-group-admin-flow)
2. [ACC Admin Flow](#2-acc-admin-flow)
3. [Training Center Admin Flow](#3-training-center-admin-flow)
4. [Instructor Flow](#4-instructor-flow)

---

## 1. Group Admin Flow

**Role**: `group_admin`  
**Purpose**: System-wide administration and oversight

### 1.1 Initial Setup
- Group Admin account is created manually or via seeder (not through public registration)
- Account has `status = 'active'` from the start
- Has full system access and administrative privileges

### 1.2 Registration/Login
- **Login**: `POST /auth/login`
  - Email and password
  - Returns Bearer token
  - Status check is not enforced (can login regardless of status)

### 1.3 ACC Management Workflow

#### Step 1: View Pending ACC Applications
- **Endpoint**: `GET /admin/accs/applications`
- View all ACC registration applications with `status = 'pending'`
- See ACC details including documents

#### Step 2: Review ACC Application
- **Endpoint**: `GET /admin/accs/applications/{id}`
- Review specific ACC application details
- Check uploaded documents
- Verify ACC information

#### Step 3: Approve or Reject ACC
**Option A: Approve ACC**
- **Endpoint**: `PUT /admin/accs/applications/{id}/approve`
- Sets ACC `status = 'active'`
- Sets associated User account `status = 'active'`
- Records `approved_at` timestamp and `approved_by` (Group Admin ID)
- ACC can now log in and use the system

**Option B: Reject ACC**
- **Endpoint**: `PUT /admin/accs/applications/{id}/reject`
- Body: `{ "rejection_reason": "Reason text" }`
- Sets ACC `status = 'rejected'`
- Stores `rejection_reason` for reference
- ACC cannot proceed (would need to re-apply)

#### Step 4: Set ACC Commission Percentage (Optional)
- **Endpoint**: `PUT /admin/accs/{id}/commission-percentage`
- Body: `{ "commission_percentage": 15.5 }`
- Sets the commission percentage the Group receives from this ACC

#### Step 5: Create ACC Space (Optional)
- **Endpoint**: `POST /admin/accs/{id}/create-space`
- Creates workspace/resources for approved ACC

#### Step 6: Generate ACC Credentials (Optional)
- **Endpoint**: `POST /admin/accs/{id}/generate-credentials`
- Generates login credentials for ACC

#### Step 7: View All ACCs
- **Endpoint**: `GET /admin/accs`
- View all ACCs with their subscriptions and details
- **Endpoint**: `GET /admin/accs/{id}`
- View specific ACC details
- **Endpoint**: `GET /admin/accs/{id}/transactions`
- View ACC transaction history

### 1.4 Category & Sub-Category Management

#### Create/Manage Categories
- **Create**: `POST /admin/categories`
- **List**: `GET /admin/categories`
- **View**: `GET /admin/categories/{id}`
- **Update**: `PUT /admin/categories/{id}`
- **Delete**: `DELETE /admin/categories/{id}`

#### Create/Manage Sub-Categories
- **Create**: `POST /admin/sub-categories`
- **List**: `GET /admin/sub-categories`
- **View**: `GET /admin/sub-categories/{id}`
- **Update**: `PUT /admin/sub-categories/{id}`
- **Delete**: `DELETE /admin/sub-categories/{id}`

### 1.5 View System-Wide Data

#### View All Instructors
- **Endpoint**: `GET /admin/instructors`
- Query parameters: `status`, `training_center_id`, `search`, `per_page`, `page`
- View all instructors across all training centers
- See instructor details, authorizations, and course authorizations

#### View All Courses
- **Endpoint**: `GET /admin/courses`
- Query parameters: `acc_id`, `status`, `sub_category_id`, `level`, `search`, `per_page`, `page`
- View all courses created by all ACCs in the system

### 1.6 Financial Management

#### View Financial Dashboard
- **Endpoint**: `GET /admin/financial/dashboard`
- Overview of revenue, commissions, transactions

#### View Transactions
- **Endpoint**: `GET /admin/financial/transactions`
- All financial transactions in the system

#### View Settlements
- **Endpoint**: `GET /admin/financial/settlements`
- Monthly settlements and payments
- **Request Payment**: `POST /admin/financial/settlements/{id}/request-payment`

#### Reports
- **Revenue Report**: `GET /admin/reports/revenue`
- **ACCs Report**: `GET /admin/reports/accs`
- **Training Centers Report**: `GET /admin/reports/training-centers`
- **Certificates Report**: `GET /admin/reports/certificates`

### 1.7 Stripe Settings Management
- **List**: `GET /admin/stripe-settings`
- **Get Active**: `GET /admin/stripe-settings/active`
- **Create**: `POST /admin/stripe-settings`
- **Update**: `PUT /admin/stripe-settings/{id}`
- **Delete**: `DELETE /admin/stripe-settings/{id}`

### 1.8 Class Management (Full CRUD)
- **Create**: `POST /admin/classes`
- **List**: `GET /admin/classes`
- **View**: `GET /admin/classes/{id}`
- **Update**: `PUT /admin/classes/{id}`
- **Delete**: `DELETE /admin/classes/{id}`

---

## 2. ACC Admin Flow

**Role**: `acc_admin`  
**Purpose**: Manage accreditation body operations, courses, and authorize training centers/instructors

### 2.1 Registration
- **Endpoint**: `POST /auth/register`
- Body:
  ```json
  {
    "name": "ACC Name",
    "email": "acc@example.com",
    "password": "password",
    "password_confirmation": "password",
    "role": "acc_admin",
    "country": "Country",
    "address": "Address",
    "phone": "Phone"
  }
  ```
- Creates User account with `role = 'acc_admin'` and `status = 'pending'`
- Creates ACC record with `status = 'pending'`
- **Cannot log in until approved by Group Admin**

### 2.2 Approval Process
- **Waits for Group Admin approval**
- Group Admin reviews application via `GET /admin/accs/applications/{id}`
- Group Admin approves via `PUT /admin/accs/applications/{id}/approve`
- Upon approval:
  - ACC `status` changes to `'active'`
  - User `status` changes to `'active'`
  - ACC can now log in

### 2.3 Login
- **Endpoint**: `POST /auth/login`
- Can login after approval (or even before, but with limited access)
- Returns Bearer token for authenticated requests

### 2.4 Subscription Management

#### View Current Subscription
- **Endpoint**: `GET /acc/subscription`
- View subscription details, expiry, plan information

#### Pay for Subscription
- **Endpoint**: `POST /acc/subscription/payment`
- Process subscription payment
- Updates `registration_fee_paid = true` and payment timestamp

#### Renew Subscription
- **Endpoint**: `PUT /acc/subscription/renew`
- Renew expiring or expired subscription

### 2.5 Dashboard
- **Endpoint**: `GET /acc/dashboard`
- Overview of ACC operations, statistics, pending requests

### 2.6 Course Management

#### Create Course
- **Endpoint**: `POST /acc/courses`
- Body:
  ```json
  {
    "sub_category_id": 1,
    "name": "Course Name",
    "name_ar": "اسم الدورة",
    "code": "COURSE-001",
    "description": "Course description",
    "duration_hours": 40,
    "level": "beginner",
    "status": "active"
  }
  ```
- Creates course associated with this ACC

#### List Courses
- **Endpoint**: `GET /acc/courses`
- View all courses created by this ACC

#### View Course Details
- **Endpoint**: `GET /acc/courses/{id}`
- View course with sub-category and pricing information

#### Update Course
- **Endpoint**: `PUT /acc/courses/{id}`
- Update course details

#### Delete Course
- **Endpoint**: `DELETE /acc/courses/{id}`
- Remove course (if no dependencies)

#### Set Course Pricing
- **Endpoint**: `POST /acc/courses/{id}/pricing`
- Set initial certificate pricing for a course
- Body:
  ```json
  {
    "base_price": "500.00",
    "currency": "USD",
    "group_commission_percentage": "10.00",
    "training_center_commission_percentage": "15.00",
    "instructor_commission_percentage": "5.00",
    "effective_from": "2024-01-01"
  }
  ```

#### Update Course Pricing
- **Endpoint**: `PUT /acc/courses/{id}/pricing`
- Update existing pricing (creates new pricing record with effective date)

### 2.7 Certificate Template Management

#### Create Template
- **Endpoint**: `POST /acc/certificate-templates`
- Create certificate template for courses

#### List Templates
- **Endpoint**: `GET /acc/certificate-templates`
- View all certificate templates

#### View Template
- **Endpoint**: `GET /acc/certificate-templates/{id}`
- View template details

#### Update Template
- **Endpoint**: `PUT /acc/certificate-templates/{id}`
- Update template design/content

#### Delete Template
- **Endpoint**: `DELETE /acc/certificate-templates/{id}`
- Remove template

#### Preview Template
- **Endpoint**: `POST /acc/certificate-templates/{id}/preview`
- Generate preview of certificate template

### 2.8 Training Center Authorization Management

#### View Authorization Requests
- **Endpoint**: `GET /acc/training-centers/requests`
- View all pending authorization requests from training centers

#### Approve Training Center
- **Endpoint**: `PUT /acc/training-centers/requests/{id}/approve`
- Approve training center authorization request
- Training center can now:
  - View courses from this ACC
  - Create classes using this ACC's courses
  - Generate certificates for this ACC's courses

#### Reject Training Center
- **Endpoint**: `PUT /acc/training-centers/requests/{id}/reject`
- Body: `{ "rejection_reason": "Reason text" }`
- Reject authorization request
- Training center cannot use this ACC's courses

#### Return Request for Revision
- **Endpoint**: `PUT /acc/training-centers/requests/{id}/return`
- Body: `{ "return_comment": "Comments" }`
- Request more information or revisions
- Status changes to `'returned'`
- Training center can resubmit

#### View Authorized Training Centers
- **Endpoint**: `GET /acc/training-centers`
- View all approved training centers

### 2.9 Instructor Authorization Management

#### View Authorization Requests
- **Endpoint**: `GET /acc/instructors/requests`
- View all pending instructor authorization requests

#### Approve Instructor
- **Endpoint**: `PUT /acc/instructors/requests/{id}/approve`
- Approve instructor for this ACC
- Instructor can now teach courses from this ACC (if also authorized for specific courses)

#### Reject Instructor
- **Endpoint**: `PUT /acc/instructors/requests/{id}/reject`
- Body: `{ "rejection_reason": "Reason text" }`
- Reject instructor authorization

#### View Authorized Instructors
- **Endpoint**: `GET /acc/instructors`
- View all approved instructors

### 2.10 Discount Code Management

#### Create Discount Code
- **Endpoint**: `POST /acc/discount-codes`
- Create discount codes for courses/certificates

#### List Discount Codes
- **Endpoint**: `GET /acc/discount-codes`
- View all discount codes

#### View Discount Code
- **Endpoint**: `GET /acc/discount-codes/{id}`
- View discount code details

#### Update Discount Code
- **Endpoint**: `PUT /acc/discount-codes/{id}`
- Update discount code

#### Delete Discount Code
- **Endpoint**: `DELETE /acc/discount-codes/{id}`
- Remove discount code

#### Validate Discount Code
- **Endpoint**: `POST /acc/discount-codes/validate`
- Validate if a discount code is valid and applicable

### 2.11 Material Management

#### Create Material
- **Endpoint**: `POST /acc/materials`
- Create training materials for courses

#### List Materials
- **Endpoint**: `GET /acc/materials`
- View all materials

#### View Material
- **Endpoint**: `GET /acc/materials/{id}`
- View material details

#### Update Material
- **Endpoint**: `PUT /acc/materials/{id}`
- Update material

#### Delete Material
- **Endpoint**: `DELETE /acc/materials/{id}`
- Remove material

### 2.12 View Certificates & Classes
- **View Certificates**: `GET /acc/certificates`
- **View Classes**: `GET /acc/classes`
- View all certificates generated and classes created using this ACC's courses

### 2.13 Financial Management
- **View Transactions**: `GET /acc/financial/transactions`
- **View Settlements**: `GET /acc/financial/settlements`
- View ACC financial transactions and settlements

### 2.14 View Sub-Categories (Read-Only)
- **List**: `GET /admin/sub-categories`
- **View**: `GET /admin/sub-categories/{id}`
- Read-only access to sub-categories (shared route with Group Admin)

---

## 3. Training Center Admin Flow

**Role**: `training_center_admin`  
**Purpose**: Manage training center operations, create classes, and generate certificates

### 3.1 Registration
- **Endpoint**: `POST /auth/register`
- Body:
  ```json
  {
    "name": "Training Center Name",
    "email": "tc@example.com",
    "password": "password",
    "password_confirmation": "password",
    "role": "training_center_admin",
    "country": "Country",
    "city": "City",
    "address": "Address",
    "phone": "Phone"
  }
  ```
- Creates User account with `role = 'training_center_admin'` and `status = 'active'`
- Creates TrainingCenter record with `status = 'active'`
- **Can log in immediately** (no approval needed)

### 3.2 Login
- **Endpoint**: `POST /auth/login`
- Can login immediately after registration
- Returns Bearer token

### 3.3 Dashboard
- **Endpoint**: `GET /training-center/dashboard`
- Overview of:
  - Authorization status with ACCs
  - Certificate code inventory
  - Active classes
  - Wallet balance

### 3.4 ACC Authorization Workflow

#### Step 1: Browse Available ACCs
- **Endpoint**: `GET /training-center/accs`
- View all active ACCs in the system
- See ACC details (name, email, status)

#### Step 2: Request Authorization from ACC
- **Endpoint**: `POST /training-center/accs/{id}/request-authorization`
- Content-Type: `multipart/form-data`
- Upload required documents:
  - Form field: `documents[]` (file uploads)
  - Each document is uploaded as a file
- Creates authorization request with `status = 'pending'`
- ACC Admin will review the request

#### Step 3: View Authorization Status
- **Endpoint**: `GET /training-center/authorizations`
- View all authorization requests and their status:
  - `pending`: Awaiting ACC review
  - `approved`: Authorized to use ACC's courses
  - `rejected`: Request denied
  - `returned`: Request returned for revisions

#### Step 4: Wait for ACC Approval
- ACC Admin reviews request via `GET /acc/training-centers/requests`
- ACC Admin approves/rejects/returns the request
- Training Center receives updated status

**Once Approved:**
- Can view courses from this ACC
- Can create classes using this ACC's courses
- Can generate certificates for this ACC's courses

### 3.5 Course Viewing

#### View Courses from Approved ACCs
- **Endpoint**: `GET /training-center/courses`
- Query parameters:
  - `acc_id`: Filter by specific ACC
  - `sub_category_id`: Filter by sub-category
  - `level`: Filter by level (beginner, intermediate, advanced)
  - `search`: Search in course name, description
  - `per_page`, `page`: Pagination
- **Only shows courses from ACCs that have approved this training center**
- **Only shows active courses**

#### View Course Details
- **Endpoint**: `GET /training-center/courses/{id}`
- View course details including pricing, ACC information, sub-category

### 3.6 Instructor Management

#### Create Instructor
- **Endpoint**: `POST /training-center/instructors`
- Body:
  ```json
  {
    "first_name": "John",
    "last_name": "Doe",
    "email": "instructor@example.com",
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
- Creates instructor record
- Instructor `status = 'pending'` (needs authorization from ACC)

#### List Instructors
- **Endpoint**: `GET /training-center/instructors`
- View all instructors for this training center

#### View Instructor Details
- **Endpoint**: `GET /training-center/instructors/{id}`
- View instructor details, authorizations, course authorizations

#### Update Instructor
- **Endpoint**: `PUT /training-center/instructors/{id}`
- Update instructor information

#### Delete Instructor
- **Endpoint**: `DELETE /training-center/instructors/{id}`
- Remove instructor (if not assigned to classes)

#### Request ACC Authorization for Instructor
- **Endpoint**: `POST /training-center/instructors/{id}/request-authorization`
- Body:
  ```json
  {
    "acc_id": 1,
    "documents": [
      {
        "type": "license",
        "url": "/documents/license.pdf"
      }
    ]
  }
  ```
- Request ACC to authorize this instructor
- ACC Admin reviews and approves/rejects
- Once approved, instructor can teach courses from that ACC (if also authorized for specific courses)

### 3.7 Certificate Code Management

#### Purchase Certificate Codes
- **Endpoint**: `POST /training-center/codes/purchase`
- Body:
  ```json
  {
    "acc_id": 1,
    "course_id": 1,
    "quantity": 50,
    "payment_method": "wallet" // or "stripe"
  }
  ```
- Purchase certificate codes for a specific course from an ACC
- Codes are added to inventory
- Payment processed via wallet or Stripe

#### View Code Inventory
- **Endpoint**: `GET /training-center/codes/inventory`
- View available, used, and total certificate codes

#### View Code Batches
- **Endpoint**: `GET /training-center/codes/batches`
- View all code purchase batches/history

### 3.8 Wallet Management

#### Add Funds to Wallet
- **Endpoint**: `POST /training-center/wallet/add-funds`
- Body:
  ```json
  {
    "amount": 1000.00,
    "payment_method": "stripe"
  }
  ```
- Add funds to training center wallet
- Payment processed via Stripe

#### View Wallet Balance
- **Endpoint**: `GET /training-center/wallet/balance`
- View current wallet balance

#### View Wallet Transactions
- **Endpoint**: `GET /training-center/wallet/transactions`
- View all wallet transactions (deposits, purchases, etc.)

### 3.9 Class Management

#### Create Class
- **Endpoint**: `POST /training-center/classes`
- Body:
  ```json
  {
    "course_id": 1,
    "class_id": 1,
    "instructor_id": 1,
    "start_date": "2024-02-01",
    "end_date": "2024-02-05",
    "schedule_json": {
      "monday": "09:00-17:00",
      "tuesday": "09:00-17:00"
    },
    "max_capacity": 25,
    "location": "physical",
    "location_details": "Training Room A"
  }
  ```
- **Important**: The `course_id` must belong to an ACC that has approved this training center
- System validates authorization automatically
- Creates training class with `status = 'scheduled'`

#### List Classes
- **Endpoint**: `GET /training-center/classes`
- View all classes for this training center
- Includes course, instructor, and class model information

#### View Class Details
- **Endpoint**: `GET /training-center/classes/{id}`
- View class details including completion information

#### Update Class
- **Endpoint**: `PUT /training-center/classes/{id}`
- Update class details (dates, schedule, capacity, etc.)

#### Delete Class
- **Endpoint**: `DELETE /training-center/classes/{id}`
- Remove class (if no certificates generated)

#### Mark Class Complete
- **Endpoint**: `PUT /training-center/classes/{id}/complete`
- Mark class as completed
- Creates completion record
- Can now generate certificates for trainees

### 3.10 Certificate Generation

#### Generate Certificate
- **Endpoint**: `POST /training-center/certificates/generate`
- Body:
  ```json
  {
    "training_class_id": 1,
    "code_id": 1,
    "trainee_name": "John Doe",
    "trainee_id_number": "ID123456",
    "expiry_date": "2026-01-15"
  }
  ```
- Generates certificate for a trainee
- Uses certificate code from inventory
- Creates certificate PDF
- Marks code as used

#### List Certificates
- **Endpoint**: `GET /training-center/certificates`
- Query parameters: `page`, `per_page`, `course_id`, `status`
- View all generated certificates

#### View Certificate Details
- **Endpoint**: `GET /training-center/certificates/{id}`
- View certificate details including PDF URL, verification code

### 3.11 Marketplace (Materials)

#### Browse Materials
- **Endpoint**: `GET /training-center/marketplace/materials`
- View all materials available for purchase from ACCs

#### View Material Details
- **Endpoint**: `GET /training-center/marketplace/materials/{id}`
- View material details, pricing, preview

#### Purchase Material
- **Endpoint**: `POST /training-center/marketplace/purchase`
- Body:
  ```json
  {
    "material_id": 1,
    "payment_method": "wallet"
  }
  ```
- Purchase training material
- Material added to library

#### View Library
- **Endpoint**: `GET /training-center/library`
- View all purchased materials

---

## 4. Instructor Flow

**Role**: `instructor`  
**Purpose**: Teach classes and manage training sessions

### 4.1 Account Creation
- **Instructor is created by Training Center Admin**
- **Endpoint**: `POST /training-center/instructors` (called by Training Center Admin)
- Instructor record created with `status = 'pending'`
- No direct registration endpoint for instructors
- Training Center provides login credentials (if user account created separately)

**Note**: If User account is created for instructor:
- User account with `role = 'instructor'`
- User `status = 'pending'` or `'active'` (depending on implementation)

### 4.2 ACC Authorization

#### Request Authorization (via Training Center)
- Training Center Admin requests ACC authorization for instructor
- **Endpoint**: `POST /training-center/instructors/{id}/request-authorization` (Training Center Admin calls this)
- ACC Admin reviews request
- ACC Admin approves/rejects via:
  - `PUT /acc/instructors/requests/{id}/approve`
  - `PUT /acc/instructors/requests/{id}/reject`

**Once ACC Authorization Approved:**
- Instructor is authorized for that ACC
- May still need course-specific authorization to teach specific courses

### 4.3 Course Authorization (if required)
- ACC Admin authorizes instructor for specific courses
- This is typically done at the ACC level through their course management

### 4.4 Login
- **Endpoint**: `POST /auth/login`
- Email and password
- Returns Bearer token
- Can login after account is active

### 4.5 Dashboard
- **Endpoint**: `GET /instructor/dashboard`
- Overview of:
  - Assigned classes count
  - Upcoming classes
  - Completed classes
  - Earnings this month

### 4.6 Class Management

#### View Assigned Classes
- **Endpoint**: `GET /instructor/classes`
- Query parameters: `status` (scheduled, in_progress, completed)
- View all classes assigned to this instructor
- Includes:
  - Course information
  - Training center information
  - Schedule (start_date, end_date, schedule_json)
  - Location details
  - Enrollment count
  - Status

#### View Class Details
- **Endpoint**: `GET /instructor/classes/{id}`
- View detailed class information:
  - Course details and description
  - Schedule breakdown
  - Location (physical/online) and details
  - Enrolled students count
  - Max capacity
  - Status

#### Mark Class Complete
- **Endpoint**: `PUT /instructor/classes/{id}/mark-complete`
- Body:
  ```json
  {
    "completion_rate_percentage": 95.5,
    "notes": "All students completed successfully"
  }
  ```
- Marks class as completed
- Creates completion record with:
  - Completion date
  - Completion rate percentage
  - Notes
- Training center can now generate certificates for trainees

### 4.7 Access Materials

#### View Course Materials
- **Endpoint**: `GET /instructor/materials`
- View materials available for courses the instructor is teaching
- Materials from:
  - ACC course materials
  - Training center purchased materials
- Can download/view material files

### 4.8 View Earnings

#### View Earnings
- **Endpoint**: `GET /instructor/earnings`
- Query parameters: `month`, `year`, `page`, `per_page`
- View earnings from teaching classes
- Breakdown by:
  - Period (monthly)
  - Course
  - Class
  - Commission amount
- Total earnings, pending payments, paid amounts

---

## Summary of Key Relationships

### Authorization Chain
```
Group Admin
    ↓ (approves)
ACC Admin
    ↓ (approves)
Training Center Admin + Instructor
    ↓ (authorization)
Can use ACC courses to create classes
    ↓ (complete class)
Generate certificates
```

### Registration Status Flow
- **Group Admin**: Always `active` (manually created)
- **ACC Admin**: `pending` → (Group Admin approval) → `active`
- **Training Center Admin**: `active` immediately upon registration
- **Instructor**: `pending` → (ACC authorization) → `active`

### Course Access Flow
1. ACC creates course
2. Training Center requests authorization from ACC
3. ACC approves training center
4. Training Center can view ACC's courses
5. Training Center creates class using course
6. Training Center assigns instructor (who must be authorized by ACC)
7. Instructor teaches class
8. Class completed → Certificates generated

---

## Important Notes

1. **Status vs Authorization**: User `status` determines login ability, but authorization determines resource access (e.g., training center must be authorized by ACC to use their courses)

2. **Multi-Level Authorization**: 
   - ACC authorization (can use ACC's courses)
   - Course authorization (instructor can teach specific course)
   - Both may be required depending on the action

3. **Real-Time Validation**: System validates authorizations in real-time when:
   - Creating classes (checks ACC authorization)
   - Assigning instructors (checks instructor authorization)
   - Generating certificates (checks class completion, code availability)

4. **Certificate Codes**: Training centers must purchase codes before generating certificates

5. **Financial Flow**: Payments flow through the system:
   - Training Center → ACC (for codes, materials)
   - ACC → Group (commission on courses)
   - Group → ACC (settlements)

6. **Instructor Earnings**: Instructors earn commission based on certificate pricing set by ACC

---

## API Base URL
All endpoints are prefixed with: `https://aeroenix.com/v1/api`

For example:
- Login: `POST https://aeroenix.com/v1/api/auth/login`
- Dashboard: `GET https://aeroenix.com/v1/api/training-center/dashboard`

---

## Authentication
All protected endpoints require:
```
Authorization: Bearer {token}
```
Token obtained from login endpoint and valid until expiration or logout.

