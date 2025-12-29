# Frontend Migration Guide: Max Capacity Moved from Classes to Courses

## Overview

The `max_capacity` field has been moved from **Training Classes** to **Courses**. This change ensures that capacity is set at the course level by ACCs when creating courses, rather than being set per class by Training Centers.

## What Changed

### Before
- `max_capacity` was a field on **Training Classes**
- Training Centers set `max_capacity` when creating each class
- Each class could have a different capacity

### After
- `max_capacity` is now a field on **Courses**
- ACCs set `max_capacity` when creating/updating courses (required field)
- All classes for a course inherit the same `max_capacity` from the course

## API Changes

### 1. Course Creation/Update (ACC)

#### Create Course - `POST /api/acc/courses`

**New Required Field:**
```json
{
  "sub_category_id": 1,
  "name": "Advanced Fire Safety",
  "code": "AFS-001",
  "duration_hours": 40,
  "max_capacity": 20,  // ⚠️ NEW REQUIRED FIELD
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
    "max_capacity": 20,  // ⚠️ Now included in course
    "level": "advanced",
    "status": "active",
    "current_price": {
      "base_price": 500.00,
      "currency": "USD"
    }
  }
}
```

#### Update Course - `PUT /api/acc/courses/{id}`

**Optional Field (can be updated):**
```json
{
  "max_capacity": 25  // ⚠️ Can be updated
}
```

---

### 2. Class Creation (Training Center)

#### Create Class - `POST /api/training-center/classes`

**Removed Field:**
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
  // ❌ REMOVED: "max_capacity": 20
  "location": "physical",
  "location_details": "Training Room A"
}
```

**Response:**
```json
{
  "class": {
    "id": 1,
    "course_id": 1,
    "instructor_id": 1,
    "start_date": "2024-02-01",
    "end_date": "2024-02-05",
    "enrolled_count": 0,
    "status": "scheduled",
    "location": "physical",
    "course": {
      "id": 1,
      "name": "Advanced Fire Safety",
      "code": "AFS-001",
      "max_capacity": 20  // ⚠️ Get from course relationship
    }
  }
}
```

#### Update Class - `PUT /api/training-center/classes/{id}`

**Removed Field:**
- `max_capacity` is no longer accepted in the update request
- Classes automatically use the course's `max_capacity`

---

### 3. Class Responses

All class endpoints now return `max_capacity` from the course relationship:

#### Get Classes - `GET /api/training-center/classes`

```json
{
  "classes": [
    {
      "id": 1,
      "course_id": 1,
      "start_date": "2024-02-01",
      "end_date": "2024-02-05",
      "enrolled_count": 15,
      "status": "scheduled",
      "course": {
        "id": 1,
        "name": "Advanced Fire Safety",
        "code": "AFS-001",
        "max_capacity": 20  // ⚠️ Get from course
      }
    }
  ]
}
```

#### Get Class Details - `GET /api/training-center/classes/{id}`

```json
{
  "id": 1,
  "course_id": 1,
  "instructor_id": 1,
  "start_date": "2024-02-01",
  "end_date": "2024-02-05",
  "enrolled_count": 15,
  "status": "scheduled",
  "course": {
    "id": 1,
    "name": "Advanced Fire Safety",
    "code": "AFS-001",
    "max_capacity": 20  // ⚠️ Get from course
  }
}
```

---

## Frontend Code Changes Required

### 1. Course Creation Form (ACC)

**Add `max_capacity` field:**
```jsx
// CourseForm.jsx
<form onSubmit={handleSubmit}>
  {/* ... other fields ... */}
  
  <div>
    <label>Max Capacity *</label>
    <input
      type="number"
      name="max_capacity"
      value={formData.max_capacity}
      onChange={handleChange}
      min="1"
      required
    />
    <small>Maximum number of trainees per class</small>
  </div>
  
  {/* ... rest of form ... */}
</form>
```

**Validation:**
```javascript
const validationSchema = {
  // ... other fields ...
  max_capacity: {
    required: true,
    min: 1,
    type: 'number'
  }
};
```

---

### 2. Class Creation Form (Training Center)

**Remove `max_capacity` field:**
```jsx
// ClassForm.jsx
<form onSubmit={handleSubmit}>
  {/* ... other fields ... */}
  
  {/* ❌ REMOVE THIS FIELD */}
  {/* <div>
    <label>Max Capacity</label>
    <input type="number" name="max_capacity" />
  </div> */}
  
  {/* ... rest of form ... */}
</form>
```

**Display max capacity from course:**
```jsx
// ClassDetails.jsx
<div className="class-info">
  <p>Course: {class.course.name}</p>
  <p>Max Capacity: {class.course.max_capacity}</p>  {/* ⚠️ From course */}
  <p>Enrolled: {class.enrolled_count} / {class.course.max_capacity}</p>
</div>
```

---

### 3. Class List/Table Components

**Update to use course.max_capacity:**
```jsx
// ClassList.jsx
{classes.map(class => (
  <tr key={class.id}>
    <td>{class.course.name}</td>
    <td>{class.start_date}</td>
    <td>
      {class.enrolled_count} / {class.course.max_capacity}  {/* ⚠️ Changed */}
    </td>
    <td>{class.status}</td>
  </tr>
))}
```

**Before:**
```jsx
<td>{class.enrolled_count} / {class.max_capacity}</td>  // ❌ Old way
```

**After:**
```jsx
<td>{class.enrolled_count} / {class.course.max_capacity}</td>  // ✅ New way
```

---

### 4. TypeScript/Interface Updates

**Update Course interface:**
```typescript
interface Course {
  id: number;
  name: string;
  code: string;
  duration_hours: number;
  max_capacity: number;  // ⚠️ NEW FIELD
  level: string;
  status: string;
  // ... other fields
}
```

**Update TrainingClass interface:**
```typescript
interface TrainingClass {
  id: number;
  course_id: number;
  instructor_id: number;
  start_date: string;
  end_date: string;
  enrolled_count: number;
  status: string;
  // ❌ REMOVED: max_capacity: number;
  course: {
    id: number;
    name: string;
    code: string;
    max_capacity: number;  // ⚠️ Get from course
  };
  // ... other fields
}
```

---

### 5. API Service Updates

**Course Service:**
```typescript
// courseService.ts
export const createCourse = async (data: {
  sub_category_id: number;
  name: string;
  code: string;
  duration_hours: number;
  max_capacity: number;  // ⚠️ NEW REQUIRED FIELD
  level: string;
  status: string;
  pricing?: {
    base_price: number;
    currency: string;
  };
}) => {
  return api.post('/acc/courses', data);
};
```

**Class Service:**
```typescript
// classService.ts
export const createClass = async (data: {
  course_id: number;
  class_id: number;
  instructor_id: number;
  start_date: string;
  end_date: string;
  schedule_json?: object;
  // ❌ REMOVED: max_capacity: number;
  location: string;
  location_details?: string;
}) => {
  return api.post('/training-center/classes', data);
};
```

---

## Migration Checklist

### For ACC (Course Management)
- [ ] Add `max_capacity` input field to course creation form
- [ ] Add `max_capacity` input field to course update form
- [ ] Add validation for `max_capacity` (required, min: 1)
- [ ] Update course TypeScript interface to include `max_capacity`
- [ ] Update course API service to include `max_capacity` in requests
- [ ] Display `max_capacity` in course details view

### For Training Center (Class Management)
- [ ] Remove `max_capacity` input field from class creation form
- [ ] Remove `max_capacity` input field from class update form
- [ ] Remove `max_capacity` from class TypeScript interface
- [ ] Update class API service to remove `max_capacity` from requests
- [ ] Update all class displays to use `class.course.max_capacity` instead of `class.max_capacity`
- [ ] Update enrollment progress indicators to use `class.course.max_capacity`

### For Instructor Dashboard
- [ ] Update class displays to use `class.course.max_capacity`
- [ ] Update enrollment statistics to use course capacity

### For ACC (Viewing Classes)
- [ ] Update class displays to show `course.max_capacity`
- [ ] Ensure course relationship is loaded when fetching classes

---

## Common Issues & Solutions

### Issue 1: `max_capacity` is undefined
**Problem:** Accessing `class.max_capacity` returns `undefined`

**Solution:** Use `class.course.max_capacity` instead
```javascript
// ❌ Wrong
const capacity = class.max_capacity;

// ✅ Correct
const capacity = class.course?.max_capacity || 0;
```

### Issue 2: Course relationship not loaded
**Problem:** `class.course` is `null` or `undefined`

**Solution:** Ensure course relationship is included in API calls
```javascript
// Make sure to include course in the query
const classes = await api.get('/training-center/classes', {
  params: { include: 'course' }  // If your API supports this
});
```

### Issue 3: Form validation errors
**Problem:** Course creation fails with "max_capacity is required"

**Solution:** Add `max_capacity` field to the form
```javascript
const formData = {
  // ... other fields
  max_capacity: parseInt(form.max_capacity.value),  // Ensure it's a number
};
```

---

## Testing Checklist

### ACC Course Management
- [ ] Create course with `max_capacity` - should succeed
- [ ] Create course without `max_capacity` - should fail validation
- [ ] Update course `max_capacity` - should succeed
- [ ] View course details - should display `max_capacity`

### Training Center Class Management
- [ ] Create class without `max_capacity` - should succeed
- [ ] Create class with `max_capacity` - should ignore it (or show warning)
- [ ] View class list - should display capacity from course
- [ ] View class details - should display capacity from course
- [ ] Enrollment progress - should use course capacity

### Instructor Dashboard
- [ ] View assigned classes - should show capacity from course
- [ ] Class statistics - should use course capacity

---

## Example Code Snippets

### React Component Example
```jsx
import React, { useState, useEffect } from 'react';

const ClassList = () => {
  const [classes, setClasses] = useState([]);

  useEffect(() => {
    fetchClasses();
  }, []);

  const fetchClasses = async () => {
    const response = await api.get('/training-center/classes');
    setClasses(response.data.classes);
  };

  return (
    <div>
      <h2>Classes</h2>
      <table>
        <thead>
          <tr>
            <th>Course</th>
            <th>Start Date</th>
            <th>Capacity</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          {classes.map(classItem => (
            <tr key={classItem.id}>
              <td>{classItem.course?.name}</td>
              <td>{classItem.start_date}</td>
              <td>
                {classItem.enrolled_count} / {classItem.course?.max_capacity || 'N/A'}
              </td>
              <td>{classItem.status}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default ClassList;
```

### Course Form Example
```jsx
const CourseForm = ({ onSubmit, initialData }) => {
  const [formData, setFormData] = useState({
    name: initialData?.name || '',
    code: initialData?.code || '',
    duration_hours: initialData?.duration_hours || '',
    max_capacity: initialData?.max_capacity || '',  // ⚠️ NEW FIELD
    level: initialData?.level || 'beginner',
    status: initialData?.status || 'active',
    // ... other fields
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    onSubmit({
      ...formData,
      max_capacity: parseInt(formData.max_capacity),  // Ensure it's a number
    });
  };

  return (
    <form onSubmit={handleSubmit}>
      {/* ... other fields ... */}
      
      <div className="form-group">
        <label htmlFor="max_capacity">
          Max Capacity <span className="required">*</span>
        </label>
        <input
          type="number"
          id="max_capacity"
          name="max_capacity"
          value={formData.max_capacity}
          onChange={(e) => setFormData({
            ...formData,
            max_capacity: e.target.value
          })}
          min="1"
          required
        />
        <small>Maximum number of trainees per class</small>
      </div>
      
      <button type="submit">Save Course</button>
    </form>
  );
};
```

---

## API Endpoint Summary

| Endpoint | Method | Change | Action Required |
|----------|--------|--------|-----------------|
| `/api/acc/courses` | POST | Added `max_capacity` (required) | Add field to form |
| `/api/acc/courses/{id}` | PUT | Added `max_capacity` (optional) | Add field to form |
| `/api/training-center/classes` | POST | Removed `max_capacity` | Remove field from form |
| `/api/training-center/classes/{id}` | PUT | Removed `max_capacity` | Remove field from form |
| `/api/training-center/classes` | GET | Response: `course.max_capacity` | Update display logic |
| `/api/training-center/classes/{id}` | GET | Response: `course.max_capacity` | Update display logic |
| `/api/instructor/dashboard` | GET | Response: `course.max_capacity` | Update display logic |
| `/api/acc/classes` | GET | Response: `course.max_capacity` | Update display logic |

---

## Questions or Issues?

If you encounter any issues during the migration:

1. Check that the course relationship is loaded when fetching classes
2. Verify that `max_capacity` is included in course creation/update requests
3. Ensure all class displays use `class.course.max_capacity` instead of `class.max_capacity`
4. Check browser console for API errors
5. Verify TypeScript types are updated correctly

---

## Migration Date

**Effective Date:** December 29, 2024

**Backend Version:** After migration `2024_12_29_000001_move_max_capacity_to_courses`

**Breaking Changes:** Yes - This is a breaking change that requires frontend updates.

---

## Summary

- ✅ `max_capacity` is now set by ACCs when creating courses (required)
- ✅ Training Centers no longer set `max_capacity` when creating classes
- ✅ All classes inherit `max_capacity` from their course
- ✅ Update all frontend code to use `class.course.max_capacity` instead of `class.max_capacity`
- ✅ Add `max_capacity` field to course creation/update forms

