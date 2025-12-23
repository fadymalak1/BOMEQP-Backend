# Category Management & Admin Edit APIs

This document describes the new APIs for category management and admin editing capabilities.

---

## Table of Contents

1. [Admin Category Management](#admin-category-management)
2. [Admin ACC Category Assignment](#admin-acc-category-assignment)
3. [ACC Category Management](#acc-category-management)
4. [Admin Edit Capabilities](#admin-edit-capabilities)

---

## Admin Category Management

### 1. Create Category

**Endpoint:** `POST /api/admin/categories`  
**Authentication:** Required (Group Admin)  
**Description:** Admin can create a new category.

**Request Body:**
```json
{
  "name": "Aviation Safety",
  "name_ar": "سلامة الطيران",
  "description": "Category for aviation safety courses",
  "icon_url": "https://example.com/icons/aviation.png",
  "status": "active"
}
```

**Response:** `201 Created`
```json
{
  "category": {
    "id": 1,
    "name": "Aviation Safety",
    "name_ar": "سلامة الطيران",
    "description": "Category for aviation safety courses",
    "icon_url": "https://example.com/icons/aviation.png",
    "status": "active",
    "created_by": 1,
    "created_at": "2025-12-19T10:00:00.000000Z",
    "updated_at": "2025-12-19T10:00:00.000000Z"
  }
}
```

**Validation Rules:**
- `name`: required, string, max:255
- `name_ar`: nullable, string, max:255
- `description`: nullable, string
- `icon_url`: nullable, string
- `status`: required, enum: `active`, `inactive`

---

### 2. Create Sub Category

**Endpoint:** `POST /api/admin/sub-categories`  
**Authentication:** Required (Group Admin)  
**Description:** Admin can create a new sub category.

**Request Body:**
```json
{
  "category_id": 1,
  "name": "Aircraft Maintenance",
  "name_ar": "صيانة الطائرات",
  "description": "Sub category for aircraft maintenance courses",
  "status": "active"
}
```

**Response:** `201 Created`
```json
{
  "sub_category": {
    "id": 1,
    "category_id": 1,
    "name": "Aircraft Maintenance",
    "name_ar": "صيانة الطائرات",
    "description": "Sub category for aircraft maintenance courses",
    "status": "active",
    "created_by": 1,
    "created_at": "2025-12-19T10:00:00.000000Z",
    "updated_at": "2025-12-19T10:00:00.000000Z"
  }
}
```

**Validation Rules:**
- `category_id`: required, exists:categories,id
- `name`: required, string, max:255
- `name_ar`: nullable, string, max:255
- `description`: nullable, string
- `status`: required, enum: `active`, `inactive`

---

## Admin ACC Category Assignment

### 3. Assign Category to ACC

**Endpoint:** `POST /api/admin/accs/{id}/assign-category`  
**Authentication:** Required (Group Admin)  
**Description:** Admin can assign a category to an ACC.

**URL Parameters:**
- `id`: ACC ID (integer)

**Request Body:**
```json
{
  "category_id": 1
}
```

**Response:** `200 OK`
```json
{
  "message": "Category assigned successfully",
  "acc": {
    "id": 1,
    "name": "Aviation Training ACC",
    "categories": [
      {
        "id": 1,
        "name": "Aviation Safety",
        "pivot": {
          "acc_id": 1,
          "category_id": 1
        }
      }
    ]
  }
}
```

**Error Response:** `400 Bad Request` (if category already assigned)
```json
{
  "message": "Category is already assigned to this ACC"
}
```

**Validation Rules:**
- `category_id`: required, exists:categories,id

---

### 4. Remove Category from ACC

**Endpoint:** `DELETE /api/admin/accs/{id}/remove-category`  
**Authentication:** Required (Group Admin)  
**Description:** Admin can remove a category from an ACC.

**URL Parameters:**
- `id`: ACC ID (integer)

**Request Body:**
```json
{
  "category_id": 1
}
```

**Response:** `200 OK`
```json
{
  "message": "Category removed successfully",
  "acc": {
    "id": 1,
    "name": "Aviation Training ACC",
    "categories": []
  }
}
```

**Validation Rules:**
- `category_id`: required, exists:categories,id

---

## ACC Category Management

### 5. ACC List Categories

**Endpoint:** `GET /api/acc/categories`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC can view only categories assigned to them (by admin) or categories they created themselves.

**Query Parameters:**
- `status`: Filter by status (optional, enum: `active`, `inactive`)

**Response:** `200 OK`
```json
{
  "categories": [
    {
      "id": 1,
      "name": "Aviation Safety",
      "name_ar": "سلامة الطيران",
      "description": "Category assigned by admin",
      "status": "active",
      "created_by": 1,
      "sub_categories": [
        {
          "id": 1,
          "name": "Aircraft Maintenance",
          "category_id": 1
        }
      ]
    },
    {
      "id": 5,
      "name": "Custom Category",
      "name_ar": "فئة مخصصة",
      "description": "Category created by ACC",
      "status": "active",
      "created_by": 10,
      "sub_categories": []
    }
  ]
}
```

**Note:** Only shows:
- Categories assigned to the ACC (via admin assignment)
- Categories created by the ACC's user
- Subcategories that belong to accessible categories or were created by the ACC

---

### 6. ACC Get Category

**Endpoint:** `GET /api/acc/categories/{id}`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC can view a specific category only if it's assigned to them or created by them.

**URL Parameters:**
- `id`: Category ID (integer)

**Response:** `200 OK`
```json
{
  "category": {
    "id": 1,
    "name": "Aviation Safety",
    "name_ar": "سلامة الطيران",
    "description": "Category description",
    "status": "active",
    "created_by": 1,
    "sub_categories": [...]
  }
}
```

**Error Response:** `404 Not Found` (if category not assigned or not created by ACC)
```json
{
  "message": "Category not found or not accessible"
}
```

---

### 7. ACC Create Category

**Endpoint:** `POST /api/acc/categories`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC can create their own categories.

**Request Body:**
```json
{
  "name": "Custom Category",
  "name_ar": "فئة مخصصة",
  "description": "ACC's custom category",
  "icon_url": "https://example.com/icons/custom.png",
  "status": "active"
}
```

**Response:** `201 Created`
```json
{
  "category": {
    "id": 5,
    "name": "Custom Category",
    "name_ar": "فئة مخصصة",
    "description": "ACC's custom category",
    "icon_url": "https://example.com/icons/custom.png",
    "status": "active",
    "created_by": 10,
    "created_at": "2025-12-19T10:00:00.000000Z",
    "updated_at": "2025-12-19T10:00:00.000000Z"
  }
}
```

**Validation Rules:**
- `name`: required, string, max:255
- `name_ar`: nullable, string, max:255
- `description`: nullable, string
- `icon_url`: nullable, string
- `status`: required, enum: `active`, `inactive`

---

### 8. ACC Update Category

**Endpoint:** `PUT /api/acc/categories/{id}`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC can update only categories they created.

**URL Parameters:**
- `id`: Category ID (integer)

**Request Body:**
```json
{
  "name": "Updated Category Name",
  "status": "inactive"
}
```

**Response:** `200 OK`
```json
{
  "message": "Category updated successfully",
  "category": {
    "id": 5,
    "name": "Updated Category Name",
    "status": "inactive",
    ...
  }
}
```

**Error Response:** `403 Forbidden` (if category not created by this ACC)
```json
{
  "message": "You can only update categories you created"
}
```

**Validation Rules:**
- `name`: sometimes, string, max:255
- `name_ar`: nullable, string, max:255
- `description`: nullable, string
- `icon_url`: nullable, string
- `status`: sometimes, enum: `active`, `inactive`

---

### 9. ACC Delete Category

**Endpoint:** `DELETE /api/acc/categories/{id}`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC can delete only categories they created.

**URL Parameters:**
- `id`: Category ID (integer)

**Response:** `200 OK`
```json
{
  "message": "Category deleted successfully"
}
```

**Error Response:** `403 Forbidden` (if category not created by this ACC)
```json
{
  "message": "You can only delete categories you created"
}
```

---

### 10. ACC List Sub Categories

**Endpoint:** `GET /api/acc/sub-categories`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC can view subcategories that belong to categories assigned to them or created by them.

**Query Parameters:**
- `category_id`: Filter by category ID (optional)
- `status`: Filter by status (optional, enum: `active`, `inactive`)

**Response:** `200 OK`
```json
{
  "sub_categories": [
    {
      "id": 1,
      "category_id": 1,
      "name": "Aircraft Maintenance",
      "name_ar": "صيانة الطائرات",
      "description": "Sub category description",
      "status": "active",
      "created_by": 1,
      "category": {
        "id": 1,
        "name": "Aviation Safety"
      }
    }
  ]
}
```

**Error Response:** `403 Forbidden` (if filtering by inaccessible category)
```json
{
  "message": "Category not accessible"
}
```

---

### 11. ACC Get Sub Category

**Endpoint:** `GET /api/acc/sub-categories/{id}`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC can view a specific subcategory only if its parent category is assigned to them or created by them.

**URL Parameters:**
- `id`: Sub Category ID (integer)

**Response:** `200 OK`
```json
{
  "sub_category": {
    "id": 1,
    "category_id": 1,
    "name": "Aircraft Maintenance",
    "name_ar": "صيانة الطائرات",
    "description": "Sub category description",
    "status": "active",
    "created_by": 1,
    "category": {
      "id": 1,
      "name": "Aviation Safety"
    }
  }
}
```

**Error Response:** `404 Not Found` (if subcategory's category not accessible)
```json
{
  "message": "Sub category not found or not accessible"
}
```

---

### 12. ACC Create Sub Category

**Endpoint:** `POST /api/acc/sub-categories`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC can create sub categories for categories they created.

**Request Body:**
```json
{
  "category_id": 5,
  "name": "Custom Sub Category",
  "name_ar": "فئة فرعية مخصصة",
  "description": "ACC's custom sub category",
  "status": "active"
}
```

**Response:** `201 Created`
```json
{
  "sub_category": {
    "id": 10,
    "category_id": 5,
    "name": "Custom Sub Category",
    "name_ar": "فئة فرعية مخصصة",
    "description": "ACC's custom sub category",
    "status": "active",
    "created_by": 10,
    "created_at": "2025-12-19T10:00:00.000000Z",
    "updated_at": "2025-12-19T10:00:00.000000Z"
  }
}
```

**Error Response:** `403 Forbidden` (if category not assigned or not created by this ACC)
```json
{
  "message": "You can only create sub categories for categories assigned to you or created by you"
}
```

**Validation Rules:**
- `category_id`: required, exists:categories,id
- `name`: required, string, max:255
- `name_ar`: nullable, string, max:255
- `description`: nullable, string
- `status`: required, enum: `active`, `inactive`

---

### 13. ACC Update Sub Category

**Endpoint:** `PUT /api/acc/sub-categories/{id}`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC can update only sub categories they created.

**URL Parameters:**
- `id`: Sub Category ID (integer)

**Request Body:**
```json
{
  "name": "Updated Sub Category Name",
  "status": "inactive"
}
```

**Response:** `200 OK`
```json
{
  "message": "Sub category updated successfully",
  "sub_category": {
    "id": 10,
    "name": "Updated Sub Category Name",
    "status": "inactive",
    ...
  }
}
```

**Error Response:** `403 Forbidden` (if sub category not accessible)
```json
{
  "message": "You can only update sub categories you created or sub categories in accessible categories"
}
```

**Note:** ACC can update subcategories if:
- The subcategory was created by the ACC, OR
- The subcategory belongs to a category assigned to the ACC or created by the ACC

**Validation Rules:**
- `category_id`: sometimes, exists:categories,id
- `name`: sometimes, string, max:255
- `name_ar`: nullable, string, max:255
- `description`: nullable, string
- `status`: sometimes, enum: `active`, `inactive`

---

### 14. ACC Delete Sub Category

**Endpoint:** `DELETE /api/acc/sub-categories/{id}`  
**Authentication:** Required (ACC Admin)  
**Description:** ACC can delete only sub categories they created.

**URL Parameters:**
- `id`: Sub Category ID (integer)

**Response:** `200 OK`
```json
{
  "message": "Sub category deleted successfully"
}
```

**Error Response:** `403 Forbidden` (if sub category not created by this ACC)
```json
{
  "message": "You can only delete sub categories you created"
}
```

---

## Admin Edit Capabilities

### 11. Update ACC Data

**Endpoint:** `PUT /api/admin/accs/{id}`  
**Authentication:** Required (Group Admin)  
**Description:** Admin can update ACC data.

**URL Parameters:**
- `id`: ACC ID (integer)

**Request Body:**
```json
{
  "name": "Updated ACC Name",
  "legal_name": "Updated Legal Name",
  "phone": "+1234567890",
  "email": "updated@example.com",
  "status": "active",
  "commission_percentage": 15.5
}
```

**Response:** `200 OK`
```json
{
  "message": "ACC updated successfully",
  "acc": {
    "id": 1,
    "name": "Updated ACC Name",
    "legal_name": "Updated Legal Name",
    "phone": "+1234567890",
    "email": "updated@example.com",
    "status": "active",
    "commission_percentage": "15.50",
    ...
  }
}
```

**Validation Rules:**
- `name`: sometimes, string, max:255
- `legal_name`: sometimes, string, max:255
- `registration_number`: sometimes, string, max:255, unique:accs,registration_number,{id}
- `country`: sometimes, string, max:255
- `address`: sometimes, string
- `phone`: sometimes, string, max:255
- `email`: sometimes, email, max:255, unique:accs,email,{id}
- `website`: nullable, string, max:255
- `logo_url`: nullable, string, max:255
- `status`: sometimes, enum: `pending`, `active`, `suspended`, `expired`, `rejected`
- `registration_fee_paid`: sometimes, boolean
- `registration_fee_amount`: nullable, numeric, min:0
- `commission_percentage`: nullable, numeric, min:0, max:100

---

### 12. List Training Centers

**Endpoint:** `GET /api/admin/training-centers`  
**Authentication:** Required (Group Admin)  
**Description:** Admin can list all training centers with optional filters.

**Query Parameters:**
- `status`: Filter by status (optional)
- `country`: Filter by country (optional)
- `search`: Search in name, legal_name, email, registration_number (optional)
- `per_page`: Items per page (optional, default: 15)

**Response:** `200 OK`
```json
{
  "training_centers": [
    {
      "id": 1,
      "name": "Aviation Training Center",
      "legal_name": "Aviation Training Center LLC",
      "registration_number": "TC001",
      "country": "USA",
      "city": "New York",
      "email": "contact@aviationtc.com",
      "status": "active",
      "wallet": {...},
      "instructors": [...]
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

### 13. Get Training Center Details

**Endpoint:** `GET /api/admin/training-centers/{id}`  
**Authentication:** Required (Group Admin)  
**Description:** Admin can get detailed information about a training center.

**URL Parameters:**
- `id`: Training Center ID (integer)

**Response:** `200 OK`
```json
{
  "training_center": {
    "id": 1,
    "name": "Aviation Training Center",
    "legal_name": "Aviation Training Center LLC",
    "registration_number": "TC001",
    "country": "USA",
    "city": "New York",
    "address": "123 Aviation St",
    "phone": "+1234567890",
    "email": "contact@aviationtc.com",
    "website": "https://aviationtc.com",
    "logo_url": "https://example.com/logo.png",
    "referred_by_group": true,
    "status": "active",
    "wallet": {...},
    "instructors": [...],
    "authorizations": [...],
    "certificates": [...],
    "training_classes": [...]
  }
}
```

---

### 14. Update Training Center Data

**Endpoint:** `PUT /api/admin/training-centers/{id}`  
**Authentication:** Required (Group Admin)  
**Description:** Admin can update training center data.

**URL Parameters:**
- `id`: Training Center ID (integer)

**Request Body:**
```json
{
  "name": "Updated Training Center Name",
  "legal_name": "Updated Legal Name",
  "phone": "+1234567890",
  "email": "updated@example.com",
  "status": "active",
  "referred_by_group": true
}
```

**Response:** `200 OK`
```json
{
  "message": "Training center updated successfully",
  "training_center": {
    "id": 1,
    "name": "Updated Training Center Name",
    "legal_name": "Updated Legal Name",
    "phone": "+1234567890",
    "email": "updated@example.com",
    "status": "active",
    "referred_by_group": true,
    ...
  }
}
```

**Validation Rules:**
- `name`: sometimes, string, max:255
- `legal_name`: sometimes, string, max:255
- `registration_number`: sometimes, string, max:255, unique:training_centers,registration_number,{id}
- `country`: sometimes, string, max:255
- `city`: sometimes, string, max:255
- `address`: sometimes, string
- `phone`: sometimes, string, max:255
- `email`: sometimes, email, max:255, unique:training_centers,email,{id}
- `website`: nullable, string, max:255
- `logo_url`: nullable, string, max:255
- `referred_by_group`: sometimes, boolean
- `status`: sometimes, enum: `pending`, `active`, `suspended`, `inactive`

---

### 15. Update Instructor Data

**Endpoint:** `PUT /api/admin/instructors/{id}`  
**Authentication:** Required (Group Admin)  
**Description:** Admin can update instructor data.

**URL Parameters:**
- `id`: Instructor ID (integer)

**Request Body:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john.doe@example.com",
  "phone": "+1234567890",
  "status": "active",
  "specializations": ["Aviation Safety", "Aircraft Maintenance"]
}
```

**Response:** `200 OK`
```json
{
  "message": "Instructor updated successfully",
  "instructor": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone": "+1234567890",
    "status": "active",
    "specializations": ["Aviation Safety", "Aircraft Maintenance"],
    "training_center": {...},
    "authorizations": [...],
    "course_authorizations": [...]
  }
}
```

**Validation Rules:**
- `first_name`: sometimes, string, max:255
- `last_name`: sometimes, string, max:255
- `email`: sometimes, email, max:255, unique:instructors,email,{id}
- `phone`: sometimes, string, max:255
- `id_number`: sometimes, string, max:255, unique:instructors,id_number,{id}
- `cv_url`: nullable, string, max:255
- `certificates_json`: nullable, array
- `specializations`: nullable, array
- `status`: sometimes, enum: `pending`, `active`, `suspended`, `inactive`

---

## Summary

### Admin Endpoints
- ✅ Create Category: `POST /api/admin/categories`
- ✅ Create Sub Category: `POST /api/admin/sub-categories`
- ✅ Assign Category to ACC: `POST /api/admin/accs/{id}/assign-category`
- ✅ Remove Category from ACC: `DELETE /api/admin/accs/{id}/remove-category`
- ✅ Update ACC: `PUT /api/admin/accs/{id}`
- ✅ List Training Centers: `GET /api/admin/training-centers`
- ✅ Get Training Center: `GET /api/admin/training-centers/{id}`
- ✅ Update Training Center: `PUT /api/admin/training-centers/{id}`
- ✅ Update Instructor: `PUT /api/admin/instructors/{id}`

### ACC Endpoints
- ✅ List Categories: `GET /api/acc/categories` (filtered: assigned + created)
- ✅ Get Category: `GET /api/acc/categories/{id}` (only if assigned or created)
- ✅ Create Category: `POST /api/acc/categories`
- ✅ Update Category: `PUT /api/acc/categories/{id}` (only if created by ACC)
- ✅ Delete Category: `DELETE /api/acc/categories/{id}` (only if created by ACC)
- ✅ List Sub Categories: `GET /api/acc/sub-categories` (filtered: accessible categories)
- ✅ Get Sub Category: `GET /api/acc/sub-categories/{id}` (only if accessible)
- ✅ Create Sub Category: `POST /api/acc/sub-categories` (for assigned or created categories)
- ✅ Update Sub Category: `PUT /api/acc/sub-categories/{id}` (if accessible)
- ✅ Delete Sub Category: `DELETE /api/acc/sub-categories/{id}` (only if created by ACC)

---

## Notes

1. **ACC Category Visibility**: ACCs can only see:
   - Categories assigned to them by admin (via `POST /api/admin/accs/{id}/assign-category`)
   - Categories they created themselves
   - Subcategories that belong to accessible categories or were created by the ACC

2. **ACC Category Management**: ACCs can only manage (update/delete) categories and sub categories they created. They can view categories assigned to them but cannot modify them.

2. **Category Assignment**: Only Group Admins can assign categories to ACCs. This creates a many-to-many relationship between ACCs and Categories.

3. **Admin Edit Permissions**: Group Admins have full edit permissions for ACCs, Training Centers, and Instructors.

4. **Validation**: All endpoints include proper validation and return appropriate error messages.

5. **PostgreSQL Compatible**: All database operations are PostgreSQL compatible.

---

**Last Updated:** December 19, 2025

