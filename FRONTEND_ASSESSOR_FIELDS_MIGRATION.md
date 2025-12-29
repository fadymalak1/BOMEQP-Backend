# Frontend Migration Guide: Assessor Fields Added

## Overview

Two new fields have been added to support assessor functionality:
1. **`is_assessor`** - Added to **Instructors** to mark if an instructor is an assessor
2. **`assessor_required`** - Added to **Courses** to indicate if a course requires an assessor

## What Changed

### New Fields

#### 1. Instructor - `is_assessor`
- **Type:** Boolean
- **Default:** `false`
- **Required:** No (optional)
- **Purpose:** Marks whether an instructor can act as an assessor

#### 2. Course - `assessor_required`
- **Type:** Boolean
- **Default:** `false`
- **Required:** No (optional)
- **Purpose:** Indicates whether this course requires an assessor

## API Changes

### 1. Instructor Creation/Update (Training Center)

#### Create Instructor - `POST /api/training-center/instructors`

**New Optional Field:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "id_number": "ID123456",
  "is_assessor": true,  // ⚠️ NEW OPTIONAL FIELD
  "specializations": ["Fire Safety"],
  "certificates_json": []
}
```

**Response:**
```json
{
  "message": "Instructor created successfully. Credentials have been sent to the instructor's email.",
  "instructor": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "is_assessor": true,  // ⚠️ Now included in response
    "status": "pending"
  }
}
```

#### Update Instructor - `PUT /api/training-center/instructors/{id}`

**Optional Field:**
```json
{
  "is_assessor": false  // ⚠️ Can be updated
}
```

---

### 2. Instructor Update (Admin)

#### Update Instructor - `PUT /api/admin/instructors/{id}`

**New Optional Field:**
```json
{
  "is_assessor": true,  // ⚠️ NEW OPTIONAL FIELD
  "status": "active"
}
```

---

### 3. Course Creation/Update (ACC)

#### Create Course - `POST /api/acc/courses`

**New Optional Field:**
```json
{
  "sub_category_id": 1,
  "name": "Advanced Fire Safety",
  "code": "AFS-001",
  "duration_hours": 40,
  "max_capacity": 20,
  "assessor_required": true,  // ⚠️ NEW OPTIONAL FIELD
  "level": "advanced",
  "status": "active",
  "pricing": {
    "base_price": 500.00,
    "currency": "USD"
  }
}
```

**Response:**
```json
{
  "message": "Course created successfully with pricing",
  "course": {
    "id": 1,
    "name": "Advanced Fire Safety",
    "code": "AFS-001",
    "duration_hours": 40,
    "max_capacity": 20,
    "assessor_required": true,  // ⚠️ Now included in response
    "level": "advanced",
    "status": "active"
  }
}
```

#### Update Course - `PUT /api/acc/courses/{id}`

**Optional Field:**
```json
{
  "assessor_required": false  // ⚠️ Can be updated
}
```

---

### 4. Instructor Responses

All instructor endpoints now return `is_assessor`:

#### Get Instructors - `GET /api/training-center/instructors`

```json
{
  "instructors": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "is_assessor": true,  // ⚠️ NEW FIELD
      "status": "active"
    }
  ]
}
```

#### Get Instructor Details - `GET /api/training-center/instructors/{id}`

```json
{
  "id": 1,
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "is_assessor": true,  // ⚠️ NEW FIELD
  "status": "active",
  "training_center": {
    "id": 1,
    "name": "ABC Training Center"
  }
}
```

---

### 5. Course Responses

All course endpoints now return `assessor_required`:

#### Get Courses - `GET /api/acc/courses`

```json
{
  "courses": [
    {
      "id": 1,
      "name": "Advanced Fire Safety",
      "code": "AFS-001",
      "duration_hours": 40,
      "max_capacity": 20,
      "assessor_required": true,  // ⚠️ NEW FIELD
      "level": "advanced",
      "status": "active"
    }
  ]
}
```

#### Get Course Details - `GET /api/acc/courses/{id}`

```json
{
  "id": 1,
  "name": "Advanced Fire Safety",
  "code": "AFS-001",
  "description": "Advanced fire safety training course",
  "duration_hours": 40,
  "max_capacity": 20,
  "assessor_required": true,  // ⚠️ NEW FIELD
  "level": "advanced",
  "status": "active"
}
```

---

## Frontend Code Changes Required

### 1. Instructor Creation Form (Training Center)

**Add `is_assessor` checkbox:**
```jsx
// InstructorForm.jsx
<form onSubmit={handleSubmit}>
  {/* ... other fields ... */}
  
  <div className="form-group">
    <label>
      <input
        type="checkbox"
        name="is_assessor"
        checked={formData.is_assessor || false}
        onChange={(e) => setFormData({
          ...formData,
          is_assessor: e.target.checked
        })}
      />
      Is Assessor
    </label>
    <small>Mark this instructor as an assessor</small>
  </div>
  
  {/* ... rest of form ... */}
</form>
```

**Form State:**
```javascript
const [formData, setFormData] = useState({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  id_number: '',
  is_assessor: false,  // ⚠️ NEW FIELD
  specializations: [],
  certificates_json: []
});
```

---

### 2. Instructor Update Form (Training Center & Admin)

**Add `is_assessor` checkbox:**
```jsx
// InstructorEditForm.jsx
<div className="form-group">
  <label>
    <input
      type="checkbox"
      name="is_assessor"
      checked={instructor.is_assessor || false}
      onChange={(e) => setInstructor({
        ...instructor,
        is_assessor: e.target.checked
      })}
    />
    Is Assessor
  </label>
</div>
```

---

### 3. Course Creation Form (ACC)

**Add `assessor_required` checkbox:**
```jsx
// CourseForm.jsx
<form onSubmit={handleSubmit}>
  {/* ... other fields ... */}
  
  <div className="form-group">
    <label>
      <input
        type="checkbox"
        name="assessor_required"
        checked={formData.assessor_required || false}
        onChange={(e) => setFormData({
          ...formData,
          assessor_required: e.target.checked
        })}
      />
      Assessor Required
    </label>
    <small>This course requires an assessor</small>
  </div>
  
  {/* ... rest of form ... */}
</form>
```

**Form State:**
```javascript
const [formData, setFormData] = useState({
  sub_category_id: '',
  name: '',
  code: '',
  duration_hours: '',
  max_capacity: '',
  assessor_required: false,  // ⚠️ NEW FIELD
  level: 'beginner',
  status: 'active',
  pricing: null
});
```

---

### 4. Course Update Form (ACC)

**Add `assessor_required` checkbox:**
```jsx
// CourseEditForm.jsx
<div className="form-group">
  <label>
    <input
      type="checkbox"
      name="assessor_required"
      checked={course.assessor_required || false}
      onChange={(e) => setCourse({
        ...course,
        assessor_required: e.target.checked
      })}
    />
    Assessor Required
  </label>
</div>
```

---

### 5. Instructor List/Table Components

**Display assessor badge:**
```jsx
// InstructorList.jsx
{instructors.map(instructor => (
  <tr key={instructor.id}>
    <td>{instructor.first_name} {instructor.last_name}</td>
    <td>{instructor.email}</td>
    <td>
      {instructor.is_assessor && (  // ⚠️ NEW DISPLAY
        <span className="badge badge-info">Assessor</span>
      )}
    </td>
    <td>{instructor.status}</td>
  </tr>
))}
```

**Filter by assessor:**
```jsx
// InstructorList.jsx
const [filters, setFilters] = useState({
  status: '',
  is_assessor: null,  // ⚠️ NEW FILTER
  search: ''
});

// Filter logic
const filteredInstructors = instructors.filter(instructor => {
  if (filters.is_assessor !== null && instructor.is_assessor !== filters.is_assessor) {
    return false;
  }
  // ... other filters
  return true;
});
```

---

### 6. Course List/Table Components

**Display assessor required indicator:**
```jsx
// CourseList.jsx
{courses.map(course => (
  <tr key={course.id}>
    <td>{course.name}</td>
    <td>{course.code}</td>
    <td>
      {course.assessor_required && (  // ⚠️ NEW DISPLAY
        <span className="badge badge-warning">Assessor Required</span>
      )}
    </td>
    <td>{course.status}</td>
  </tr>
))}
```

**Filter by assessor requirement:**
```jsx
// CourseList.jsx
const [filters, setFilters] = useState({
  status: '',
  assessor_required: null,  // ⚠️ NEW FILTER
  search: ''
});
```

---

### 7. TypeScript/Interface Updates

**Update Instructor interface:**
```typescript
interface Instructor {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  is_assessor: boolean;  // ⚠️ NEW FIELD
  status: string;
  // ... other fields
}
```

**Update Course interface:**
```typescript
interface Course {
  id: number;
  name: string;
  code: string;
  duration_hours: number;
  max_capacity: number;
  assessor_required: boolean;  // ⚠️ NEW FIELD
  level: string;
  status: string;
  // ... other fields
}
```

---

### 8. API Service Updates

**Instructor Service:**
```typescript
// instructorService.ts
export const createInstructor = async (data: {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  id_number: string;
  is_assessor?: boolean;  // ⚠️ NEW OPTIONAL FIELD
  specializations?: string[];
  certificates_json?: any[];
}) => {
  return api.post('/training-center/instructors', data);
};

export const updateInstructor = async (id: number, data: {
  first_name?: string;
  last_name?: string;
  email?: string;
  phone?: string;
  is_assessor?: boolean;  // ⚠️ NEW OPTIONAL FIELD
  // ... other fields
}) => {
  return api.put(`/training-center/instructors/${id}`, data);
};
```

**Course Service:**
```typescript
// courseService.ts
export const createCourse = async (data: {
  sub_category_id: number;
  name: string;
  code: string;
  duration_hours: number;
  max_capacity: number;
  assessor_required?: boolean;  // ⚠️ NEW OPTIONAL FIELD
  level: string;
  status: string;
  pricing?: {
    base_price: number;
    currency: string;
  };
}) => {
  return api.post('/acc/courses', data);
};

export const updateCourse = async (id: number, data: {
  name?: string;
  code?: string;
  assessor_required?: boolean;  // ⚠️ NEW OPTIONAL FIELD
  // ... other fields
}) => {
  return api.put(`/acc/courses/${id}`, data);
};
```

---

## Migration Checklist

### For Training Center (Instructor Management)
- [ ] Add `is_assessor` checkbox to instructor creation form
- [ ] Add `is_assessor` checkbox to instructor update form
- [ ] Update instructor TypeScript interface to include `is_assessor`
- [ ] Update instructor API service to include `is_assessor` in requests
- [ ] Display assessor badge/indicator in instructor list
- [ ] Add filter option for assessors in instructor list
- [ ] Update instructor details view to show assessor status

### For ACC (Course Management)
- [ ] Add `assessor_required` checkbox to course creation form
- [ ] Add `assessor_required` checkbox to course update form
- [ ] Update course TypeScript interface to include `assessor_required`
- [ ] Update course API service to include `assessor_required` in requests
- [ ] Display assessor required indicator in course list
- [ ] Add filter option for assessor requirement in course list
- [ ] Update course details view to show assessor requirement

### For Admin (Instructor Management)
- [ ] Add `is_assessor` checkbox to instructor update form
- [ ] Update instructor API service to include `is_assessor` in update requests
- [ ] Display assessor badge in admin instructor list
- [ ] Add filter option for assessors

### For Class Management (Training Center)
- [ ] When creating a class, check if course requires assessor
- [ ] If assessor required, show warning/validation when selecting instructor
- [ ] Filter instructor selection to show only assessors if course requires it
- [ ] Display assessor requirement in class details

---

## Common Issues & Solutions

### Issue 1: `is_assessor` or `assessor_required` is undefined
**Problem:** Accessing `instructor.is_assessor` or `course.assessor_required` returns `undefined`

**Solution:** Use default value or optional chaining
```javascript
// ❌ Wrong
const isAssessor = instructor.is_assessor;

// ✅ Correct
const isAssessor = instructor.is_assessor ?? false;
// or
const isAssessor = instructor.is_assessor || false;
```

### Issue 2: Checkbox not updating
**Problem:** Checkbox state not syncing with form data

**Solution:** Ensure boolean conversion
```javascript
// ❌ Wrong
onChange={(e) => setFormData({
  ...formData,
  is_assessor: e.target.value
})}

// ✅ Correct
onChange={(e) => setFormData({
  ...formData,
  is_assessor: e.target.checked
})}
```

### Issue 3: API not accepting boolean
**Problem:** API returns validation error for boolean field

**Solution:** Ensure proper boolean value is sent
```javascript
// ❌ Wrong
const data = {
  is_assessor: "true"  // String
};

// ✅ Correct
const data = {
  is_assessor: true  // Boolean
};
// or
const data = {
  is_assessor: formData.is_assessor === true || formData.is_assessor === "true"
};
```

---

## Testing Checklist

### Instructor Management
- [ ] Create instructor with `is_assessor: true` - should succeed
- [ ] Create instructor with `is_assessor: false` - should succeed
- [ ] Create instructor without `is_assessor` - should default to false
- [ ] Update instructor `is_assessor` - should succeed
- [ ] View instructor list - should display assessor badge
- [ ] Filter instructors by assessor status - should work

### Course Management
- [ ] Create course with `assessor_required: true` - should succeed
- [ ] Create course with `assessor_required: false` - should succeed
- [ ] Create course without `assessor_required` - should default to false
- [ ] Update course `assessor_required` - should succeed
- [ ] View course list - should display assessor required indicator
- [ ] Filter courses by assessor requirement - should work

### Class Management
- [ ] Create class for course that requires assessor - should validate instructor
- [ ] Display assessor requirement when selecting course
- [ ] Filter instructors to show only assessors when course requires it

---

## Example Code Snippets

### React Component - Instructor Form
```jsx
import React, { useState } from 'react';

const InstructorForm = ({ onSubmit, initialData }) => {
  const [formData, setFormData] = useState({
    first_name: initialData?.first_name || '',
    last_name: initialData?.last_name || '',
    email: initialData?.email || '',
    phone: initialData?.phone || '',
    id_number: initialData?.id_number || '',
    is_assessor: initialData?.is_assessor || false,  // ⚠️ NEW FIELD
    specializations: initialData?.specializations || [],
    certificates_json: initialData?.certificates_json || []
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    onSubmit(formData);
  };

  return (
    <form onSubmit={handleSubmit}>
      <div className="form-group">
        <label htmlFor="first_name">First Name *</label>
        <input
          type="text"
          id="first_name"
          value={formData.first_name}
          onChange={(e) => setFormData({ ...formData, first_name: e.target.value })}
          required
        />
      </div>

      <div className="form-group">
        <label htmlFor="last_name">Last Name *</label>
        <input
          type="text"
          id="last_name"
          value={formData.last_name}
          onChange={(e) => setFormData({ ...formData, last_name: e.target.value })}
          required
        />
      </div>

      {/* ... other fields ... */}

      <div className="form-group">
        <label>
          <input
            type="checkbox"
            checked={formData.is_assessor}
            onChange={(e) => setFormData({
              ...formData,
              is_assessor: e.target.checked
            })}
          />
          Is Assessor
        </label>
        <small>Mark this instructor as an assessor</small>
      </div>

      <button type="submit">Save Instructor</button>
    </form>
  );
};

export default InstructorForm;
```

### React Component - Course Form
```jsx
import React, { useState } from 'react';

const CourseForm = ({ onSubmit, initialData }) => {
  const [formData, setFormData] = useState({
    sub_category_id: initialData?.sub_category_id || '',
    name: initialData?.name || '',
    code: initialData?.code || '',
    duration_hours: initialData?.duration_hours || '',
    max_capacity: initialData?.max_capacity || '',
    assessor_required: initialData?.assessor_required || false,  // ⚠️ NEW FIELD
    level: initialData?.level || 'beginner',
    status: initialData?.status || 'active',
    pricing: initialData?.pricing || null
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    onSubmit({
      ...formData,
      duration_hours: parseInt(formData.duration_hours),
      max_capacity: parseInt(formData.max_capacity)
    });
  };

  return (
    <form onSubmit={handleSubmit}>
      {/* ... other fields ... */}

      <div className="form-group">
        <label>
          <input
            type="checkbox"
            checked={formData.assessor_required}
            onChange={(e) => setFormData({
              ...formData,
              assessor_required: e.target.checked
            })}
          />
          Assessor Required
        </label>
        <small>This course requires an assessor</small>
      </div>

      <button type="submit">Save Course</button>
    </form>
  );
};

export default CourseForm;
```

### React Component - Instructor List with Filter
```jsx
import React, { useState, useEffect } from 'react';

const InstructorList = () => {
  const [instructors, setInstructors] = useState([]);
  const [filters, setFilters] = useState({
    status: '',
    is_assessor: null,  // ⚠️ NEW FILTER
    search: ''
  });

  useEffect(() => {
    fetchInstructors();
  }, [filters]);

  const fetchInstructors = async () => {
    const params = new URLSearchParams();
    if (filters.status) params.append('status', filters.status);
    if (filters.is_assessor !== null) params.append('is_assessor', filters.is_assessor);
    if (filters.search) params.append('search', filters.search);

    const response = await api.get(`/training-center/instructors?${params}`);
    setInstructors(response.data.instructors);
  };

  return (
    <div>
      <div className="filters">
        <select
          value={filters.is_assessor === null ? '' : filters.is_assessor}
          onChange={(e) => setFilters({
            ...filters,
            is_assessor: e.target.value === '' ? null : e.target.value === 'true'
          })}
        >
          <option value="">All Instructors</option>
          <option value="true">Assessors Only</option>
          <option value="false">Non-Assessors</option>
        </select>
      </div>

      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Type</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          {instructors.map(instructor => (
            <tr key={instructor.id}>
              <td>{instructor.first_name} {instructor.last_name}</td>
              <td>{instructor.email}</td>
              <td>
                {instructor.is_assessor && (
                  <span className="badge badge-info">Assessor</span>
                )}
                {!instructor.is_assessor && (
                  <span className="badge badge-secondary">Instructor</span>
                )}
              </td>
              <td>{instructor.status}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default InstructorList;
```

### React Component - Class Creation with Assessor Validation
```jsx
import React, { useState, useEffect } from 'react';

const ClassForm = ({ onSubmit }) => {
  const [formData, setFormData] = useState({
    course_id: '',
    instructor_id: '',
    start_date: '',
    end_date: '',
    // ... other fields
  });
  const [selectedCourse, setSelectedCourse] = useState(null);
  const [availableInstructors, setAvailableInstructors] = useState([]);

  useEffect(() => {
    if (formData.course_id) {
      fetchCourse(formData.course_id);
      fetchInstructors();
    }
  }, [formData.course_id]);

  const fetchCourse = async (courseId) => {
    const response = await api.get(`/training-center/courses/${courseId}`);
    setSelectedCourse(response.data.course);
  };

  const fetchInstructors = async () => {
    const params = new URLSearchParams();
    // If course requires assessor, filter to show only assessors
    if (selectedCourse?.assessor_required) {
      params.append('is_assessor', 'true');
    }
    const response = await api.get(`/training-center/instructors?${params}`);
    setAvailableInstructors(response.data.instructors);
  };

  return (
    <form onSubmit={handleSubmit}>
      <div className="form-group">
        <label>Course *</label>
        <select
          value={formData.course_id}
          onChange={(e) => {
            setFormData({ ...formData, course_id: e.target.value });
            setSelectedCourse(null);
          }}
          required
        >
          <option value="">Select Course</option>
          {/* ... course options ... */}
        </select>
        {selectedCourse?.assessor_required && (
          <div className="alert alert-warning">
            ⚠️ This course requires an assessor. Please select an assessor as the instructor.
          </div>
        )}
      </div>

      <div className="form-group">
        <label>Instructor *</label>
        <select
          value={formData.instructor_id}
          onChange={(e) => setFormData({ ...formData, instructor_id: e.target.value })}
          required
        >
          <option value="">Select Instructor</option>
          {availableInstructors.map(instructor => (
            <option key={instructor.id} value={instructor.id}>
              {instructor.first_name} {instructor.last_name}
              {instructor.is_assessor && ' (Assessor)'}
            </option>
          ))}
        </select>
      </div>

      {/* ... other fields ... */}

      <button type="submit">Create Class</button>
    </form>
  );
};

export default ClassForm;
```

---

## API Endpoint Summary

| Endpoint | Method | Change | Action Required |
|----------|--------|--------|-----------------|
| `/api/training-center/instructors` | POST | Added `is_assessor` (optional) | Add checkbox to form |
| `/api/training-center/instructors/{id}` | PUT | Added `is_assessor` (optional) | Add checkbox to form |
| `/api/training-center/instructors` | GET | Response: `is_assessor` | Update display logic |
| `/api/training-center/instructors/{id}` | GET | Response: `is_assessor` | Update display logic |
| `/api/admin/instructors/{id}` | PUT | Added `is_assessor` (optional) | Add checkbox to form |
| `/api/admin/instructors` | GET | Response: `is_assessor` | Update display logic |
| `/api/acc/courses` | POST | Added `assessor_required` (optional) | Add checkbox to form |
| `/api/acc/courses/{id}` | PUT | Added `assessor_required` (optional) | Add checkbox to form |
| `/api/acc/courses` | GET | Response: `assessor_required` | Update display logic |
| `/api/acc/courses/{id}` | GET | Response: `assessor_required` | Update display logic |

---

## Business Logic Considerations

### Class Creation Logic
When creating a class:
1. Check if the selected course has `assessor_required: true`
2. If yes, validate that the selected instructor has `is_assessor: true`
3. Show warning/error if assessor is required but instructor is not an assessor
4. Optionally filter instructor dropdown to show only assessors when course requires it

**Example Validation:**
```javascript
const validateClassCreation = (course, instructor) => {
  if (course.assessor_required && !instructor.is_assessor) {
    return {
      valid: false,
      error: 'This course requires an assessor. Please select an instructor who is marked as an assessor.'
    };
  }
  return { valid: true };
};
```

### Filtering Logic
- When viewing instructors, allow filtering by assessor status
- When viewing courses, allow filtering by assessor requirement
- When creating classes, filter instructors based on course assessor requirement

---

## Questions or Issues?

If you encounter any issues during the migration:

1. Check that boolean values are properly converted (not strings)
2. Verify that checkboxes use `checked` property, not `value`
3. Ensure default values are set to `false` if field is not provided
4. Check browser console for API errors
5. Verify TypeScript types are updated correctly

---

## Migration Date

**Effective Date:** December 29, 2024

**Backend Version:** After migration `2024_12_29_000002_add_assessor_fields`

**Breaking Changes:** No - These are additive fields with default values.

---

## Summary

- ✅ `is_assessor` field added to instructors (boolean, optional, default: false)
- ✅ `assessor_required` field added to courses (boolean, optional, default: false)
- ✅ Both fields are optional and default to `false` if not provided
- ✅ Add checkboxes to instructor and course forms
- ✅ Update TypeScript interfaces to include new fields
- ✅ Update API services to include new fields in requests
- ✅ Display assessor status/requirement in lists and details
- ✅ Consider business logic for class creation validation

