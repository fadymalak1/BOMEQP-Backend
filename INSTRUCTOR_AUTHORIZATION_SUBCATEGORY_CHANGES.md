# Instructor Authorization - Sub-Category & Course Selection Changes

**Date:** December 29, 2025  
**Status:** ✅ Implemented

---

## Overview

The instructor authorization flow has been enhanced to allow training centers to authorize instructors for courses in two ways:
1. **Select a Sub-Category** - Authorizes the instructor for all courses in that sub-category
2. **Select Specific Courses** - Authorizes the instructor for individually selected courses from any sub-category

---

## What Changed

### Before
- Training centers could only select specific courses (`course_ids` array)
- Required selecting each course individually

### After
- Training centers can select either:
  - A **sub-category** (authorizes all active courses in that sub-category for the ACC)
  - **Specific courses** (authorizes only the selected courses)
- Cannot select both sub-category and specific courses in the same request
- New endpoints to help with course/sub-category selection

---

## New API Endpoints

### 1. Get Sub-Categories for an ACC

**Endpoint:** `GET /api/training-center/accs/{accId}/sub-categories`

**Description:** Get all sub-categories that have active courses in a specific ACC, with course counts.

**Authorization:** Requires `training_center_admin` role

**Response (200):**
```json
{
  "sub_categories": [
    {
      "id": 1,
      "name": "Fire Safety",
      "name_ar": "سلامة الحريق",
      "description": "Fire safety related courses",
      "category_id": 1,
      "courses_count": 5,
      "status": "active"
    },
    {
      "id": 2,
      "name": "First Aid",
      "name_ar": "الإسعافات الأولية",
      "description": "First aid courses",
      "category_id": 1,
      "courses_count": 3,
      "status": "active"
    }
  ],
  "acc": {
    "id": 1,
    "name": "ABC Accreditation Body"
  }
}
```

**Example Usage:**
```javascript
const getSubCategories = async (accId) => {
  const response = await axios.get(`/api/training-center/accs/${accId}/sub-categories`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  return response.data.sub_categories;
};
```

---

### 2. Get Courses for an ACC (with optional sub-category filter)

**Endpoint:** `GET /api/training-center/accs/{accId}/courses`

**Description:** Get all active courses for a specific ACC, optionally filtered by sub-category.

**Authorization:** Requires `training_center_admin` role

**Query Parameters:**
- `sub_category_id` (optional, integer): Filter courses by sub-category ID

**Response (200) - All Courses:**
```json
{
  "courses": [
    {
      "id": 1,
      "name": "Basic Fire Safety",
      "code": "FS-001",
      "description": "Introduction to fire safety",
      "duration_hours": 8,
      "level": "beginner",
      "status": "active",
      "sub_category_id": 1,
      "acc_id": 1,
      "sub_category": {
        "id": 1,
        "name": "Fire Safety"
      }
    },
    {
      "id": 2,
      "name": "Advanced Fire Safety",
      "code": "FS-002",
      "description": "Advanced fire safety techniques",
      "duration_hours": 16,
      "level": "advanced",
      "status": "active",
      "sub_category_id": 1,
      "acc_id": 1,
      "sub_category": {
        "id": 1,
        "name": "Fire Safety"
      }
    }
  ],
  "acc": {
    "id": 1,
    "name": "ABC Accreditation Body"
  }
}
```

**Response (200) - Filtered by Sub-Category:**
```json
{
  "courses": [
    {
      "id": 1,
      "name": "Basic Fire Safety",
      "code": "FS-001",
      "sub_category_id": 1,
      ...
    },
    {
      "id": 2,
      "name": "Advanced Fire Safety",
      "code": "FS-002",
      "sub_category_id": 1,
      ...
    }
  ],
  "acc": {
    "id": 1,
    "name": "ABC Accreditation Body"
  }
}
```

**Example Usage:**
```javascript
// Get all courses for an ACC
const getAllCourses = async (accId) => {
  const response = await axios.get(`/api/training-center/accs/${accId}/courses`, {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  return response.data.courses;
};

// Get courses for a specific sub-category
const getCoursesBySubCategory = async (accId, subCategoryId) => {
  const response = await axios.get(
    `/api/training-center/accs/${accId}/courses?sub_category_id=${subCategoryId}`,
    {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    }
  );
  return response.data.courses;
};
```

---

### 3. Request Instructor Authorization (Updated)

**Endpoint:** `POST /api/training-center/instructors/{id}/request-authorization`

**Description:** Request authorization for an instructor. Now supports either sub-category or specific course selection.

**Authorization:** Requires `training_center_admin` role

**Request Body - Option 1: Sub-Category Selection**
```json
{
  "acc_id": 1,
  "sub_category_id": 5,
  "documents_json": [
    {
      "type": "certificate",
      "url": "/documents/cert.pdf"
    }
  ]
}
```

**Request Body - Option 2: Specific Course Selection**
```json
{
  "acc_id": 1,
  "course_ids": [1, 2, 3, 5],
  "documents_json": [
    {
      "type": "certificate",
      "url": "/documents/cert.pdf"
    }
  ]
}
```

**Validation Rules:**
- `acc_id` is required
- Either `sub_category_id` OR `course_ids` must be provided (not both, not neither)
- If `sub_category_id` is provided, all active courses in that sub-category for the ACC will be included
- If `course_ids` is provided, only those specific courses will be included
- All courses must belong to the selected ACC and be active

**Response (201):**
```json
{
  "message": "Authorization request submitted successfully",
  "authorization": {
    "id": 1,
    "instructor_id": 1,
    "acc_id": 1,
    "sub_category_id": 5,
    "training_center_id": 1,
    "status": "pending",
    "request_date": "2025-12-29T10:00:00.000000Z",
    "sub_category": {
      "id": 5,
      "name": "Fire Safety"
    }
  },
  "courses_count": 5
}
```

**Error Responses:**

**422 - Missing Selection:**
```json
{
  "message": "Either sub_category_id or course_ids must be provided",
  "errors": {
    "sub_category_id": ["Either sub_category_id or course_ids is required"]
  }
}
```

**422 - Both Provided:**
```json
{
  "message": "Cannot provide both sub_category_id and course_ids. Please provide only one.",
  "errors": {
    "sub_category_id": ["Cannot provide both sub_category_id and course_ids"]
  }
}
```

**422 - No Courses Found:**
```json
{
  "message": "No active courses found for the selected sub-category in this ACC"
}
```

**422 - Invalid Courses:**
```json
{
  "message": "Some selected courses do not belong to the selected ACC or are not active"
}
```

---

## Frontend Implementation Guide

### UI Flow Recommendation

1. **Select ACC** - User selects which ACC to request authorization from
2. **Choose Selection Type** - User chooses between:
   - "Select Sub-Category" (authorizes all courses in sub-category)
   - "Select Specific Courses" (authorizes only selected courses)
3. **Make Selection**:
   - If sub-category: Show list of sub-categories with course counts
   - If specific courses: Show all courses grouped by sub-category with checkboxes
4. **Submit Request** - Send authorization request with selected option

### React Example - Authorization Form

```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function InstructorAuthorizationForm({ instructorId }) {
  const [accs, setAccs] = useState([]);
  const [selectedAccId, setSelectedAccId] = useState('');
  const [selectionType, setSelectionType] = useState('sub_category'); // 'sub_category' or 'courses'
  const [subCategories, setSubCategories] = useState([]);
  const [courses, setCourses] = useState([]);
  const [selectedSubCategoryId, setSelectedSubCategoryId] = useState('');
  const [selectedCourseIds, setSelectedCourseIds] = useState([]);
  const [loading, setLoading] = useState(false);

  // Load ACCs
  useEffect(() => {
    loadAccs();
  }, []);

  // Load sub-categories when ACC is selected
  useEffect(() => {
    if (selectedAccId && selectionType === 'sub_category') {
      loadSubCategories(selectedAccId);
    }
  }, [selectedAccId, selectionType]);

  // Load courses when ACC is selected
  useEffect(() => {
    if (selectedAccId && selectionType === 'courses') {
      loadCourses(selectedAccId);
    }
  }, [selectedAccId, selectionType]);

  const loadAccs = async () => {
    try {
      const response = await axios.get('/api/training-center/accs', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      setAccs(response.data.accs);
    } catch (error) {
      console.error('Error loading ACCs:', error);
    }
  };

  const loadSubCategories = async (accId) => {
    try {
      const response = await axios.get(`/api/training-center/accs/${accId}/sub-categories`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      setSubCategories(response.data.sub_categories);
    } catch (error) {
      console.error('Error loading sub-categories:', error);
    }
  };

  const loadCourses = async (accId) => {
    try {
      const response = await axios.get(`/api/training-center/accs/${accId}/courses`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      setCourses(response.data.courses);
    } catch (error) {
      console.error('Error loading courses:', error);
    }
  };

  const handleCourseToggle = (courseId) => {
    setSelectedCourseIds(prev => 
      prev.includes(courseId)
        ? prev.filter(id => id !== courseId)
        : [...prev, courseId]
    );
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const requestData = {
        acc_id: selectedAccId,
        documents_json: [] // Add documents if needed
      };

      if (selectionType === 'sub_category') {
        requestData.sub_category_id = selectedSubCategoryId;
      } else {
        requestData.course_ids = selectedCourseIds;
      }

      const response = await axios.post(
        `/api/training-center/instructors/${instructorId}/request-authorization`,
        requestData,
        {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        }
      );

      alert(`Authorization request submitted! ${response.data.courses_count} courses included.`);
      // Reset form or redirect
    } catch (error) {
      if (error.response?.status === 422) {
        alert(error.response.data.message);
      } else {
        alert('Error submitting authorization request');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <div>
        <label>Select ACC:</label>
        <select 
          value={selectedAccId} 
          onChange={(e) => setSelectedAccId(e.target.value)}
          required
        >
          <option value="">Select ACC</option>
          {accs.map(acc => (
            <option key={acc.id} value={acc.id}>{acc.name}</option>
          ))}
        </select>
      </div>

      {selectedAccId && (
        <div>
          <label>Selection Type:</label>
          <div>
            <label>
              <input
                type="radio"
                value="sub_category"
                checked={selectionType === 'sub_category'}
                onChange={(e) => setSelectionType(e.target.value)}
              />
              Select Sub-Category (All Courses)
            </label>
            <label>
              <input
                type="radio"
                value="courses"
                checked={selectionType === 'courses'}
                onChange={(e) => setSelectionType(e.target.value)}
              />
              Select Specific Courses
            </label>
          </div>
        </div>
      )}

      {selectedAccId && selectionType === 'sub_category' && (
        <div>
          <label>Select Sub-Category:</label>
          <select
            value={selectedSubCategoryId}
            onChange={(e) => setSelectedSubCategoryId(e.target.value)}
            required
          >
            <option value="">Select Sub-Category</option>
            {subCategories.map(subCat => (
              <option key={subCat.id} value={subCat.id}>
                {subCat.name} ({subCat.courses_count} courses)
              </option>
            ))}
          </select>
        </div>
      )}

      {selectedAccId && selectionType === 'courses' && (
        <div>
          <label>Select Courses:</label>
          <div style={{ maxHeight: '300px', overflowY: 'auto' }}>
            {courses.map(course => (
              <label key={course.id} style={{ display: 'block' }}>
                <input
                  type="checkbox"
                  checked={selectedCourseIds.includes(course.id)}
                  onChange={() => handleCourseToggle(course.id)}
                />
                {course.name} ({course.code}) - {course.sub_category?.name}
              </label>
            ))}
          </div>
          <p>Selected: {selectedCourseIds.length} courses</p>
        </div>
      )}

      <button type="submit" disabled={loading || !selectedAccId}>
        {loading ? 'Submitting...' : 'Submit Authorization Request'}
      </button>
    </form>
  );
}

export default InstructorAuthorizationForm;
```

---

## Backend Behavior

### When Sub-Category is Selected

1. Backend retrieves all active courses in the selected sub-category that belong to the selected ACC
2. If no courses are found, returns a 422 error
3. Stores `sub_category_id` in the authorization record
4. Stores all course IDs in `documents_json.requested_course_ids` for reference
5. When ACC approves, creates `InstructorCourseAuthorization` records for all courses

### When Specific Courses are Selected

1. Backend validates that all selected courses belong to the selected ACC and are active
2. If any course is invalid, returns a 422 error
3. Stores course IDs in `documents_json.requested_course_ids`
4. When ACC approves, creates `InstructorCourseAuthorization` records for selected courses only

### Course Authorization Creation

When ACC admin approves the authorization:
- `InstructorCourseAuthorization` records are automatically created for all courses (from sub-category or specific selection)
- These records link the instructor to specific courses and the ACC
- Status is set to `active`

---

## Migration Notes

**Database Migration Required:**
- Run migration: `php artisan migrate`
- This adds `sub_category_id` column to `instructor_acc_authorization` table

**Backward Compatibility:**
- Existing authorizations without `sub_category_id` will continue to work
- The `sub_category_id` field is nullable

---

## Testing Checklist

- [ ] Load sub-categories for an ACC
- [ ] Load courses for an ACC (all courses)
- [ ] Load courses filtered by sub-category
- [ ] Submit authorization with sub-category selection
- [ ] Submit authorization with specific course selection
- [ ] Verify error when neither sub-category nor courses are provided
- [ ] Verify error when both sub-category and courses are provided
- [ ] Verify error when sub-category has no courses
- [ ] Verify error when selected courses don't belong to ACC
- [ ] Verify course authorizations are created when ACC approves

---

## Support

If you have any questions or need clarification on these changes, please contact the backend development team.

**Last Updated:** December 29, 2025

