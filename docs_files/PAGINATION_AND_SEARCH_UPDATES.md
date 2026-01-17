# Pagination and Search Updates - Training Center Endpoints

## Overview
Pagination and search functionality have been added to four training center endpoints to improve performance and user experience when dealing with large datasets.

---

## 1. List ACCs Endpoint

### Endpoint
`GET /v1/api/training-center/accs`

### Changes

#### **New Query Parameters:**
- `search` (string, optional) - Search by ACC name, legal name, email, or country
- `per_page` (integer, optional) - Number of items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

#### **Response Structure Changed:**
The response now uses Laravel's pagination format instead of a simple array.

**Before:**
```json
{
  "accs": [
    {
      "id": 1,
      "name": "ACC Name",
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
      "name": "ACC Name",
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
GET /v1/api/training-center/accs
```

**Search for ACCs:**
```
GET /v1/api/training-center/accs?search=training
```

**Get specific page with custom page size:**
```
GET /v1/api/training-center/accs?page=2&per_page=20
```

**Search with pagination:**
```
GET /v1/api/training-center/accs?search=education&page=1&per_page=10
```

### Search Fields
- ACC name
- Legal name
- Email
- Country

---

## 2. List Instructors Endpoint

### Endpoint
`GET /v1/api/training-center/instructors`

### Changes

#### **New Query Parameters:**
- `search` (string, optional) - Search by instructor name, email, phone, or ID number
- `per_page` (integer, optional) - Number of items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

#### **Response Structure Changed:**
The response now uses Laravel's pagination format.

**Before:**
```json
{
  "instructors": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
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
      "first_name": "John",
      "last_name": "Doe",
      ...
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 30,
  "last_page": 2
}
```

### Example Requests

**Get all instructors (first page):**
```
GET /v1/api/training-center/instructors
```

**Search for instructors:**
```
GET /v1/api/training-center/instructors?search=john
```

**Get specific page:**
```
GET /v1/api/training-center/instructors?page=2&per_page=25
```

### Search Fields
- First name
- Last name
- Email
- Phone
- ID number

---

## 3. List Instructor Authorizations Endpoint

### Endpoint
`GET /v1/api/training-center/instructors/authorizations`

### Changes

#### **New Query Parameters:**
- `search` (string, optional) - Search by instructor name, ACC name, or authorization ID
- `status` (string, optional) - Filter by authorization status: `pending`, `approved`, `rejected`, `returned`
- `payment_status` (string, optional) - Filter by payment status: `pending`, `paid`, `failed`
- `per_page` (integer, optional) - Number of items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

#### **Response Structure Changed:**
The response now uses Laravel's pagination format.

**Before:**
```json
{
  "authorizations": [
    {
      "id": 1,
      "status": "pending",
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
      "status": "pending",
      ...
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 45,
  "last_page": 3
}
```

### Example Requests

**Get all authorizations:**
```
GET /v1/api/training-center/instructors/authorizations
```

**Filter by status:**
```
GET /v1/api/training-center/instructors/authorizations?status=pending
```

**Filter by payment status:**
```
GET /v1/api/training-center/instructors/authorizations?payment_status=paid
```

**Search for authorizations:**
```
GET /v1/api/training-center/instructors/authorizations?search=john
```

**Combine filters and search:**
```
GET /v1/api/training-center/instructors/authorizations?status=approved&payment_status=paid&search=training&page=1&per_page=20
```

### Search Fields
- Authorization ID
- Instructor first name
- Instructor last name
- ACC name

### Filter Options
- **Status:** `pending`, `approved`, `rejected`, `returned`
- **Payment Status:** `pending`, `paid`, `failed`

---

## 4. List Classes Endpoint

### Endpoint
`GET /v1/api/training-center/classes`

### Changes

#### **New Query Parameters:**
- `search` (string, optional) - Search by class name, course name, instructor name, or status
- `status` (string, optional) - Filter by class status: `scheduled`, `in_progress`, `completed`, `cancelled`
- `per_page` (integer, optional) - Number of items per page (default: 15)
- `page` (integer, optional) - Page number (default: 1)

#### **Response Structure Changed:**
The response now uses Laravel's pagination format. The data structure remains the same (includes trainees, course, instructor, etc.).

**Before:**
```json
{
  "classes": [
    {
      "id": 1,
      "name": "Class A",
      "trainees": [...],
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
      "name": "Class A",
      "trainees": [...],
      ...
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 25,
  "last_page": 2
}
```

### Example Requests

**Get all classes:**
```
GET /v1/api/training-center/classes
```

**Filter by status:**
```
GET /v1/api/training-center/classes?status=completed
```

**Search for classes:**
```
GET /v1/api/training-center/classes?search=python
```

**Combine filter and search:**
```
GET /v1/api/training-center/classes?status=in_progress&search=advanced&page=1&per_page=10
```

### Search Fields
- Class name
- Course name
- Instructor first name
- Instructor last name
- Status

### Filter Options
- **Status:** `scheduled`, `in_progress`, `completed`, `cancelled`

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

1. **All four endpoints now support pagination:**
   - `/training-center/accs`
   - `/training-center/instructors`
   - `/training-center/instructors/authorizations`
   - `/training-center/classes`

2. **All four endpoints now support search:**
   - Search functionality added with relevant fields for each endpoint

3. **Response structure updated:**
   - Changed from simple array (`{ "items": [...] }`) to paginated format (`{ "data": [...], "current_page": ..., "total": ... }`)

4. **Additional filters added:**
   - Status filter for classes and authorizations
   - Payment status filter for authorizations

### 📋 Query Parameters Summary:

| Endpoint | Search | Status Filter | Payment Status | Pagination |
|----------|--------|---------------|----------------|------------|
| `/accs` | ✅ | ❌ | ❌ | ✅ |
| `/instructors` | ✅ | ❌ | ❌ | ✅ |
| `/instructors/authorizations` | ✅ | ✅ | ✅ | ✅ |
| `/classes` | ✅ | ✅ | ❌ | ✅ |

---

## Frontend Action Items

### 1. Update API Response Parsing

**Before:**
```javascript
// Old way
const response = await api.get('/training-center/accs');
const accs = response.data.accs;
```

**After:**
```javascript
// New way
const response = await api.get('/training-center/accs');
const accs = response.data.data;  // Note: data.data
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
- ACCs list page
- Instructors list page
- Instructor authorizations page
- Classes list page

### 4. Add Filter Dropdowns

Add filter dropdowns where applicable:
- Status filter for classes
- Status filter for authorizations
- Payment status filter for authorizations

### 5. Update State Management

Update your state management to handle:
- Search term
- Current page
- Items per page
- Active filters

### 6. Update API Calls

Update all API calls to include pagination and search parameters:

```javascript
// Example: Fetch classes with search and pagination
const fetchClasses = async (page = 1, search = '', status = '') => {
  const params = {
    page,
    per_page: 15,
    ...(search && { search }),
    ...(status && { status })
  };
  
  const response = await api.get('/training-center/classes', { params });
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
- Existing filters (status, payment_status) continue to work as before

---

## Example Implementation

### React Example

```javascript
import { useState, useEffect } from 'react';

function ClassesList() {
  const [classes, setClasses] = useState([]);
  const [pagination, setPagination] = useState({});
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);

  useEffect(() => {
    fetchClasses();
  }, [page, search, status]);

  const fetchClasses = async () => {
    const params = {
      page,
      per_page: 15,
      ...(search && { search }),
      ...(status && { status })
    };

    const response = await api.get('/training-center/classes', { params });
    setClasses(response.data.data);
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
        placeholder="Search classes..."
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
        <option value="scheduled">Scheduled</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>

      {/* Classes list */}
      {classes.map(class => (
        <div key={class.id}>{class.name}</div>
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

