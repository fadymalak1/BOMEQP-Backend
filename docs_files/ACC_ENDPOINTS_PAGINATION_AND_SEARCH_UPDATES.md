# ACC Endpoints - Pagination and Search Updates

## Overview
Pagination and search functionality have been added to seven ACC endpoints to improve performance and user experience when dealing with large datasets. All endpoints now support pagination and search capabilities.

---

## 1. List Training Centers Endpoint

### Endpoint
`GET /v1/api/acc/training-centers`

### Changes

#### **New Query Parameters:**
- `search` (string, optional) - Search by training center name, email, country, or city
- `per_page` (integer, optional) - Number of items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

#### **Response Structure Changed:**
The response now uses Laravel's pagination format instead of a simple array.

**Before:**
```json
{
  "training_centers": [
    {
      "id": 1,
      "name": "Training Center Name",
      ...
    }
  ]
}
```

**After:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Training Center Name",
      ...
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 50,
  "last_page": 4,
  "from": 1,
  "to": 15
}
```

### Example Requests

**Get first page with default pagination:**
```
GET /v1/api/acc/training-centers
```

**Search for training centers:**
```
GET /v1/api/acc/training-centers?search=education
```

**Get specific page with custom page size:**
```
GET /v1/api/acc/training-centers?page=2&per_page=20
```

### Search Fields
- Training center name
- Email
- Country
- City

---

## 2. List Training Center Requests Endpoint

### Endpoint
`GET /v1/api/acc/training-centers/requests`

### Changes

#### **New Query Parameters:**
- `search` (string, optional) - Search by training center name, email, country, city, or request ID
- `status` (string, optional) - Filter by request status: `pending`, `approved`, `rejected`, `returned`
- `per_page` (integer, optional) - Number of items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

#### **Response Structure Changed:**
The response now uses Laravel's pagination format.

**Before:**
```json
{
  "requests": [
    {
      "id": 1,
      "status": "pending",
      "training_center": {...}
    }
  ]
}
```

**After:**
```json
{
  "data": [
    {
      "id": 1,
      "status": "pending",
      "training_center": {...}
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 30,
  "last_page": 2
}
```

### Example Requests

**Get all requests:**
```
GET /v1/api/acc/training-centers/requests
```

**Filter by status:**
```
GET /v1/api/acc/training-centers/requests?status=pending
```

**Search for requests:**
```
GET /v1/api/acc/training-centers/requests?search=training
```

**Combine filter and search:**
```
GET /v1/api/acc/training-centers/requests?status=approved&search=education&page=1&per_page=20
```

### Search Fields
- Request ID
- Training center name
- Training center email
- Training center country
- Training center city

### Filter Options
- **Status:** `pending`, `approved`, `rejected`, `returned`

---

## 3. List Instructor Requests Endpoint

### Endpoint
`GET /v1/api/acc/instructors/requests`

### Changes

#### **New Query Parameters:**
- `search` (string, optional) - Search by instructor name, email, training center name, or request ID
- `status` (string, optional) - Filter by request status: `pending`, `approved`, `rejected`, `returned`
- `payment_status` (string, optional) - Filter by payment status: `pending`, `paid`, `failed`
- `per_page` (integer, optional) - Number of items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

#### **Response Structure Changed:**
The response now uses Laravel's pagination format. The data structure remains the same (includes instructor, training center, sub_category, etc.).

**Before:**
```json
{
  "requests": [
    {
      "id": 1,
      "status": "pending",
      "instructor": {...},
      "training_center": {...}
    }
  ]
}
```

**After:**
```json
{
  "data": [
    {
      "id": 1,
      "status": "pending",
      "instructor": {...},
      "training_center": {...}
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 45,
  "last_page": 3
}
```

### Example Requests

**Get all requests:**
```
GET /v1/api/acc/instructors/requests
```

**Filter by status:**
```
GET /v1/api/acc/instructors/requests?status=approved
```

**Filter by payment status:**
```
GET /v1/api/acc/instructors/requests?payment_status=paid
```

**Search for requests:**
```
GET /v1/api/acc/instructors/requests?search=john
```

**Combine filters and search:**
```
GET /v1/api/acc/instructors/requests?status=approved&payment_status=paid&search=training&page=1&per_page=20
```

### Search Fields
- Request ID
- Instructor first name
- Instructor last name
- Instructor email
- Training center name

### Filter Options
- **Status:** `pending`, `approved`, `rejected`, `returned`
- **Payment Status:** `pending`, `paid`, `failed`

---

## 4. List Courses Endpoint

### Endpoint
`GET /v1/api/acc/courses`

### Changes

#### **New Query Parameters:**
- `search` (string, optional) - Search by course name, code, or description (already existed, now with pagination)
- `sub_category_id` (integer, optional) - Filter by sub-category (already existed)
- `status` (string, optional) - Filter by status (already existed)
- `level` (string, optional) - Filter by level (already existed)
- `per_page` (integer, optional) - **NEW** - Number of items per page (default: 15)
- `page` (integer, optional) - **NEW** - Page number (default: 1)

#### **Response Structure Changed:**
The response now uses Laravel's pagination format.

**Before:**
```json
{
  "courses": [
    {
      "id": 1,
      "name": "Course Name",
      "pricing": {...}
    }
  ]
}
```

**After:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Course Name",
      "pricing": {...}
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 25,
  "last_page": 2
}
```

### Example Requests

**Get all courses:**
```
GET /v1/api/acc/courses
```

**Search for courses:**
```
GET /v1/api/acc/courses?search=fire
```

**Filter and paginate:**
```
GET /v1/api/acc/courses?status=active&level=advanced&page=1&per_page=10
```

### Search Fields
- Course name
- Course name (Arabic)
- Course code
- Description

---

## 5. List Discount Codes Endpoint

### Endpoint
`GET /v1/api/acc/discount-codes`

### Changes

#### **New Query Parameters:**
- `search` (string, optional) - Search by discount code value or status
- `status` (string, optional) - Filter by status: `active`, `expired`, `depleted`, `inactive`
- `discount_type` (string, optional) - Filter by discount type: `time_limited`, `quantity_based`
- `per_page` (integer, optional) - Number of items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

#### **Response Structure Changed:**
The response now uses Laravel's pagination format.

**Before:**
```json
{
  "discount_codes": [
    {
      "id": 1,
      "code": "DISCOUNT10",
      ...
    }
  ]
}
```

**After:**
```json
{
  "data": [
    {
      "id": 1,
      "code": "DISCOUNT10",
      ...
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 20,
  "last_page": 2
}
```

### Example Requests

**Get all discount codes:**
```
GET /v1/api/acc/discount-codes
```

**Filter by status:**
```
GET /v1/api/acc/discount-codes?status=active
```

**Filter by discount type:**
```
GET /v1/api/acc/discount-codes?discount_type=time_limited
```

**Search for discount codes:**
```
GET /v1/api/acc/discount-codes?search=DISCOUNT
```

**Combine filters and search:**
```
GET /v1/api/acc/discount-codes?status=active&discount_type=time_limited&search=10&page=1&per_page=20
```

### Search Fields
- Discount code value
- Status

### Filter Options
- **Status:** `active`, `expired`, `depleted`, `inactive`
- **Discount Type:** `time_limited`, `quantity_based`

---

## 6. List Categories Endpoint

### Endpoint
`GET /v1/api/acc/categories`

### Changes

#### **New Query Parameters:**
- `search` (string, optional) - Search by category name (English or Arabic)
- `status` (string, optional) - Filter by status: `active`, `inactive` (already existed)
- `per_page` (integer, optional) - **NEW** - Number of items per page (default: 15)
- `page` (integer, optional) - **NEW** - Page number (default: 1)

#### **Response Structure Changed:**
The response now uses Laravel's pagination format.

**Before:**
```json
{
  "categories": [
    {
      "id": 1,
      "name": "Category Name",
      "sub_categories": [...]
    }
  ]
}
```

**After:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Category Name",
      "sub_categories": [...]
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 10,
  "last_page": 1
}
```

### Example Requests

**Get all categories:**
```
GET /v1/api/acc/categories
```

**Filter by status:**
```
GET /v1/api/acc/categories?status=active
```

**Search for categories:**
```
GET /v1/api/acc/categories?search=fire
```

**Combine filter and search:**
```
GET /v1/api/acc/categories?status=active&search=safety&page=1&per_page=10
```

### Search Fields
- Category name (English)
- Category name (Arabic)

### Filter Options
- **Status:** `active`, `inactive`

---

## 7. List Sub-Categories Endpoint

### Endpoint
`GET /v1/api/acc/sub-categories`

### Changes

#### **New Query Parameters:**
- `search` (string, optional) - Search by sub-category name (English or Arabic)
- `category_id` (integer, optional) - Filter by category ID (already existed)
- `status` (string, optional) - Filter by status: `active`, `inactive` (already existed)
- `per_page` (integer, optional) - **NEW** - Number of items per page (default: 15)
- `page` (integer, optional) - **NEW** - Page number (default: 1)

#### **Response Structure Changed:**
The response now uses Laravel's pagination format.

**Before:**
```json
{
  "sub_categories": [
    {
      "id": 1,
      "name": "Sub-Category Name",
      "category": {...}
    }
  ]
}
```

**After:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Sub-Category Name",
      "category": {...}
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 25,
  "last_page": 2
}
```

### Example Requests

**Get all sub-categories:**
```
GET /v1/api/acc/sub-categories
```

**Filter by category:**
```
GET /v1/api/acc/sub-categories?category_id=1
```

**Filter by status:**
```
GET /v1/api/acc/sub-categories?status=active
```

**Search for sub-categories:**
```
GET /v1/api/acc/sub-categories?search=basic
```

**Combine filters and search:**
```
GET /v1/api/acc/sub-categories?category_id=1&status=active&search=fire&page=1&per_page=10
```

### Search Fields
- Sub-category name (English)
- Sub-category name (Arabic)

### Filter Options
- **Category ID:** Filter by parent category
- **Status:** `active`, `inactive`

---

## Pagination Response Structure

All paginated endpoints return the following structure:

```json
{
  "data": [...],           // Array of items for current page
  "current_page": 1,        // Current page number
  "per_page": 15,           // Items per page
  "total": 100,             // Total number of items
  "last_page": 7,           // Last page number
  "from": 1,                // First item number on current page
  "to": 15,                 // Last item number on current page
  "path": "/v1/api/...",    // Base path for pagination links
  "first_page_url": "...",  // URL for first page
  "last_page_url": "...",   // URL for last page
  "next_page_url": "...",   // URL for next page (null if on last page)
  "prev_page_url": "..."    // URL for previous page (null if on first page)
}
```

---

## Summary of Changes

### ✅ What Changed:

1. **All seven endpoints now support pagination:**
   - `/acc/training-centers`
   - `/acc/training-centers/requests`
   - `/acc/instructors/requests`
   - `/acc/courses`
   - `/acc/discount-codes`
   - `/acc/categories`
   - `/acc/sub-categories`

2. **All seven endpoints now support search:**
   - Search functionality added with relevant fields for each endpoint

3. **Response structure updated:**
   - Changed from simple array (`{ "items": [...] }`) to paginated format (`{ "data": [...], "current_page": ..., "total": ... }`)

4. **Additional filters added:**
   - Status filter for requests, discount codes, categories, and sub-categories
   - Payment status filter for instructor requests
   - Discount type filter for discount codes

### 📋 Query Parameters Summary:

| Endpoint | Search | Status Filter | Payment Status | Other Filters | Pagination |
|----------|--------|---------------|----------------|---------------|------------|
| `/training-centers` | ✅ | ❌ | ❌ | ❌ | ✅ |
| `/training-centers/requests` | ✅ | ✅ | ❌ | ❌ | ✅ |
| `/instructors/requests` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `/courses` | ✅ | ✅ | ❌ | sub_category_id, level | ✅ |
| `/discount-codes` | ✅ | ✅ | ❌ | discount_type | ✅ |
| `/categories` | ✅ | ✅ | ❌ | ❌ | ✅ |
| `/sub-categories` | ✅ | ✅ | ❌ | category_id | ✅ |

---

## Frontend Action Items

### 1. Update API Response Parsing

**Before:**
```javascript
// Old way
const response = await api.get('/acc/training-centers');
const trainingCenters = response.data.training_centers;
```

**After:**
```javascript
// New way
const response = await api.get('/acc/training-centers');
const trainingCenters = response.data.data;  // Note: data.data
const pagination = {
  currentPage: response.data.current_page,
  perPage: response.data.per_page,
  total: response.data.total,
  lastPage: response.data.last_page
};
```

### 2. Implement Pagination UI

Add pagination controls to display:
- Current page number
- Total pages
- Previous/Next buttons
- Page size selector (optional)

### 3. Add Search Input Fields

Add search input fields to:
- Training centers list page
- Training center requests page
- Instructor requests page
- Courses list page
- Discount codes list page
- Categories list page
- Sub-categories list page

### 4. Add Filter Dropdowns

Add filter dropdowns where applicable:
- Status filter for requests, discount codes, categories, and sub-categories
- Payment status filter for instructor requests
- Discount type filter for discount codes
- Category filter for sub-categories
- Level filter for courses

### 5. Update State Management

Update your state management to handle:
- Search term
- Current page
- Items per page
- Active filters

### 6. Update API Calls

Update all API calls to include pagination and search parameters:

```javascript
// Example: Fetch training center requests with search and pagination
const fetchRequests = async (page = 1, search = '', status = '') => {
  const params = {
    page,
    per_page: 15,
    ...(search && { search }),
    ...(status && { status })
  };
  
  const response = await api.get('/acc/training-centers/requests', { params });
  return response.data;
};
```

### 7. Handle Empty Results

Update UI to show appropriate messages when:
- No results found for search
- No items on current page
- All pages have been viewed

### 8. Testing Checklist

- [ ] Test pagination navigation (next, previous, specific page)
- [ ] Test search functionality on all endpoints
- [ ] Test filter combinations
- [ ] Test pagination with search
- [ ] Test pagination with filters
- [ ] Test pagination with search and filters combined
- [ ] Verify response structure parsing
- [ ] Test edge cases (empty results, single page, etc.)
- [ ] Test with different `per_page` values
- [ ] Verify backward compatibility (default behavior)

---

## Migration Notes

### Breaking Changes

⚠️ **Important:** The response structure has changed from:
```json
{ "items": [...] }
```
to:
```json
{ "data": [...], "current_page": ..., "total": ... }
```

**Action Required:** Update all frontend code that accesses these endpoints to use the new response structure.

### Backward Compatibility

- Default pagination (15 items per page) is applied automatically
- If no `page` parameter is provided, page 1 is returned
- If no `search` parameter is provided, all items are returned (filtered by pagination)
- Existing filters (status, category_id, etc.) continue to work as before

---

## Example Implementation

### React Example

```javascript
import { useState, useEffect } from 'react';

function TrainingCenterRequests() {
  const [requests, setRequests] = useState([]);
  const [pagination, setPagination] = useState({});
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);

  useEffect(() => {
    fetchRequests();
  }, [page, search, status]);

  const fetchRequests = async () => {
    const params = {
      page,
      per_page: 15,
      ...(search && { search }),
      ...(status && { status })
    };

    const response = await api.get('/acc/training-centers/requests', { params });
    setRequests(response.data.data);
    setPagination({
      currentPage: response.data.current_page,
      totalPages: response.data.last_page,
      total: response.data.total
    });
  };

  return (
    <div>
      <input
        type="text"
        placeholder="Search requests..."
        value={search}
        onChange={(e) => {
          setSearch(e.target.value);
          setPage(1); // Reset to first page on search
        }}
      />
      
      <select value={status} onChange={(e) => {
        setStatus(e.target.value);
        setPage(1);
      }}>
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="returned">Returned</option>
      </select>

      {/* Requests list */}
      {requests.map(request => (
        <div key={request.id}>{request.training_center.name}</div>
      ))}

      {/* Pagination */}
      <div>
        <button
          disabled={page === 1}
          onClick={() => setPage(page - 1)}
        >
          Previous
        </button>
        <span>Page {pagination.currentPage} of {pagination.totalPages}</span>
        <button
          disabled={page === pagination.totalPages}
          onClick={() => setPage(page + 1)}
        >
          Next
        </button>
      </div>
    </div>
  );
}
```

---

## Questions or Issues?

If you encounter any issues or have questions about these changes, please contact the backend development team.

**Last Updated:** January 21, 2026

