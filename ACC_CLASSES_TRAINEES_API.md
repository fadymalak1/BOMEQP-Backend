# ACC Classes API - Trainees Data Update

## Overview

The `/acc/classes` endpoints now include trainee data for each class. This allows ACC admins to see all trainees enrolled in classes from authorized training centers.

## What Changed?

### Before
- Classes endpoint returned class information without trainee data
- Trainees had to be fetched separately

### After
- Classes endpoint includes `trainees` array in the response
- Each trainee includes enrollment details (pivot data)
- No additional API calls needed to get trainee information

## API Endpoints

### 1. List ACC Classes

**Endpoint**: `GET /api/acc/classes`

**Description**: Get all classes from training centers that have authorization from this ACC. Only shows classes for courses that belong to the ACC.

**Authentication**: Required (ACC Admin)

**Query Parameters**:
- `status` (optional) - Filter by status: `scheduled`, `in_progress`, `completed`, `cancelled`
- `training_center_id` (optional) - Filter by training center ID
- `course_id` (optional) - Filter by course ID
- `date_from` (optional) - Filter classes starting from this date (format: `YYYY-MM-DD`)
- `date_to` (optional) - Filter classes starting until this date (format: `YYYY-MM-DD`)
- `per_page` (optional) - Number of items per page (default: 15)
- `page` (optional) - Page number (default: 1)

**Response (200 OK)**:
```json
{
    "data": [
        {
            "id": 1,
            "course_id": 5,
            "training_center_id": 3,
            "instructor_id": 2,
            "class_id": 10,
            "start_date": "2024-01-15",
            "end_date": "2024-01-20",
            "schedule_json": {
                "monday": "09:00-17:00",
                "tuesday": "09:00-17:00"
            },
            "enrolled_count": 15,
            "max_capacity": 20,
            "status": "in_progress",
            "location": "physical",
            "location_details": "Training Center Main Hall",
            "created_at": "2024-01-01T10:00:00.000000Z",
            "updated_at": "2024-01-10T15:30:00.000000Z",
            "course": {
                "id": 5,
                "name": "Fire Safety Training",
                "code": "FST-001",
                "acc_id": 1,
                "duration_hours": 40,
                "status": "active"
            },
            "training_center": {
                "id": 3,
                "name": "ABC Training Center",
                "email": "info@abctraining.com",
                "phone": "+1234567890"
            },
            "instructor": {
                "id": 2,
                "first_name": "John",
                "last_name": "Smith",
                "email": "john.smith@example.com",
                "phone": "+1234567891"
            },
            "trainees": [
                {
                    "id": 1,
                    "training_center_id": 3,
                    "first_name": "Ahmed",
                    "last_name": "Mohamed",
                    "email": "ahmed.mohamed@example.com",
                    "phone": "+201234567890",
                    "id_number": "12345678901234",
                    "id_image_url": "/storage/trainees/id/123456789.jpg",
                    "card_image_url": "/storage/trainees/card/123456789.jpg",
                    "status": "active",
                    "created_at": "2024-01-05T08:00:00.000000Z",
                    "updated_at": "2024-01-10T12:00:00.000000Z",
                    "pivot": {
                        "training_class_id": 1,
                        "trainee_id": 1,
                        "status": "enrolled",
                        "enrolled_at": "2024-01-10T10:00:00.000000Z",
                        "completed_at": null,
                        "created_at": "2024-01-10T10:00:00.000000Z",
                        "updated_at": "2024-01-10T10:00:00.000000Z"
                    }
                },
                {
                    "id": 2,
                    "training_center_id": 3,
                    "first_name": "Sara",
                    "last_name": "Ali",
                    "email": "sara.ali@example.com",
                    "phone": "+201234567891",
                    "id_number": "12345678901235",
                    "id_image_url": "/storage/trainees/id/123456790.jpg",
                    "card_image_url": "/storage/trainees/card/123456790.jpg",
                    "status": "active",
                    "created_at": "2024-01-05T08:00:00.000000Z",
                    "updated_at": "2024-01-10T12:00:00.000000Z",
                    "pivot": {
                        "training_class_id": 1,
                        "trainee_id": 2,
                        "status": "completed",
                        "enrolled_at": "2024-01-10T10:00:00.000000Z",
                        "completed_at": "2024-01-20T17:00:00.000000Z",
                        "created_at": "2024-01-10T10:00:00.000000Z",
                        "updated_at": "2024-01-20T17:00:00.000000Z"
                    }
                }
            ]
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

### 2. Get Class Details

**Endpoint**: `GET /api/acc/classes/{id}`

**Description**: Get detailed information about a specific class. Only shows classes from authorized training centers.

**Authentication**: Required (ACC Admin)

**Path Parameters**:
- `id` (required) - Class ID

**Response (200 OK)**:
```json
{
    "id": 1,
    "course_id": 5,
    "training_center_id": 3,
    "instructor_id": 2,
    "class_id": 10,
    "start_date": "2024-01-15",
    "end_date": "2024-01-20",
    "schedule_json": {
        "monday": "09:00-17:00",
        "tuesday": "09:00-17:00"
    },
    "enrolled_count": 15,
    "max_capacity": 20,
    "status": "in_progress",
    "location": "physical",
    "location_details": "Training Center Main Hall",
    "created_at": "2024-01-01T10:00:00.000000Z",
    "updated_at": "2024-01-10T15:30:00.000000Z",
    "course": {
        "id": 5,
        "name": "Fire Safety Training",
        "code": "FST-001",
        "acc_id": 1,
        "duration_hours": 40,
        "status": "active"
    },
    "training_center": {
        "id": 3,
        "name": "ABC Training Center",
        "email": "info@abctraining.com",
        "phone": "+1234567890"
    },
    "instructor": {
        "id": 2,
        "first_name": "John",
        "last_name": "Smith",
        "email": "john.smith@example.com",
        "phone": "+1234567891"
    },
    "trainees": [
        {
            "id": 1,
            "training_center_id": 3,
            "first_name": "Ahmed",
            "last_name": "Mohamed",
            "email": "ahmed.mohamed@example.com",
            "phone": "+201234567890",
            "id_number": "12345678901234",
            "id_image_url": "/storage/trainees/id/123456789.jpg",
            "card_image_url": "/storage/trainees/card/123456789.jpg",
            "status": "active",
            "created_at": "2024-01-05T08:00:00.000000Z",
            "updated_at": "2024-01-10T12:00:00.000000Z",
            "pivot": {
                "training_class_id": 1,
                "trainee_id": 1,
                "status": "enrolled",
                "enrolled_at": "2024-01-10T10:00:00.000000Z",
                "completed_at": null,
                "created_at": "2024-01-10T10:00:00.000000Z",
                "updated_at": "2024-01-10T10:00:00.000000Z"
            }
        }
    ]
}
```

## Response Fields

### Class Object

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Class ID |
| `course_id` | integer | Course ID |
| `training_center_id` | integer | Training center ID |
| `instructor_id` | integer | Instructor ID |
| `class_id` | integer | Global class catalog ID |
| `start_date` | date | Class start date |
| `end_date` | date | Class end date |
| `schedule_json` | object | Class schedule (days and times) |
| `enrolled_count` | integer | Number of enrolled trainees |
| `max_capacity` | integer | Maximum class capacity |
| `status` | string | Class status: `scheduled`, `in_progress`, `completed`, `cancelled` |
| `location` | string | Location type: `physical`, `online` |
| `location_details` | string | Location details/address |
| `course` | object | Course details |
| `training_center` | object | Training center details |
| `instructor` | object | Instructor details |
| `trainees` | array | **⭐ NEW** - List of enrolled trainees |

### Trainee Object

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Trainee ID |
| `training_center_id` | integer | Training center ID |
| `first_name` | string | Trainee first name |
| `last_name` | string | Trainee last name |
| `email` | string | Trainee email |
| `phone` | string | Trainee phone number |
| `id_number` | string | Trainee ID number |
| `id_image_url` | string | URL to ID image |
| `card_image_url` | string | URL to card image |
| `status` | string | Trainee status: `active`, `inactive` |
| `pivot` | object | **⭐ NEW** - Enrollment details (see below) |

### Pivot Object (Enrollment Details)

| Field | Type | Description |
|-------|------|-------------|
| `training_class_id` | integer | Training class ID |
| `trainee_id` | integer | Trainee ID |
| `status` | string | Enrollment status: `enrolled`, `completed`, `cancelled` |
| `enrolled_at` | datetime | When trainee was enrolled |
| `completed_at` | datetime | When trainee completed the class (null if not completed) |
| `created_at` | datetime | Pivot record creation timestamp |
| `updated_at` | datetime | Pivot record update timestamp |

## Frontend Implementation

### React Example

```jsx
import { useState, useEffect } from 'react';

function ACCClassesList() {
    const [classes, setClasses] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchClasses();
    }, []);

    const fetchClasses = async () => {
        try {
            const response = await fetch('/api/acc/classes', {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            const data = await response.json();
            setClasses(data.data);
        } catch (error) {
            console.error('Error fetching classes:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) return <div>Loading...</div>;

    return (
        <div className="classes-list">
            {classes.map(classItem => (
                <ClassCard key={classItem.id} classItem={classItem} />
            ))}
        </div>
    );
}

function ClassCard({ classItem }) {
    return (
        <div className="class-card">
            <h3>{classItem.course.name}</h3>
            <p>Training Center: {classItem.training_center.name}</p>
            <p>Instructor: {classItem.instructor.first_name} {classItem.instructor.last_name}</p>
            <p>Status: {classItem.status}</p>
            <p>Dates: {classItem.start_date} to {classItem.end_date}</p>
            
            {/* Trainees Section */}
            <div className="trainees-section">
                <h4>Trainees ({classItem.trainees.length})</h4>
                <div className="trainees-list">
                    {classItem.trainees.map(trainee => (
                        <TraineeCard key={trainee.id} trainee={trainee} />
                    ))}
                </div>
            </div>
        </div>
    );
}

function TraineeCard({ trainee }) {
    const enrollmentStatus = trainee.pivot.status;
    const isCompleted = trainee.pivot.completed_at !== null;

    return (
        <div className={`trainee-card ${enrollmentStatus}`}>
            <div className="trainee-info">
                <h5>{trainee.first_name} {trainee.last_name}</h5>
                <p>Email: {trainee.email}</p>
                <p>Phone: {trainee.phone}</p>
                <p>ID: {trainee.id_number}</p>
            </div>
            <div className="enrollment-info">
                <span className={`status-badge ${enrollmentStatus}`}>
                    {enrollmentStatus}
                </span>
                <p>Enrolled: {new Date(trainee.pivot.enrolled_at).toLocaleDateString()}</p>
                {isCompleted && (
                    <p>Completed: {new Date(trainee.pivot.completed_at).toLocaleDateString()}</p>
                )}
            </div>
        </div>
    );
}
```

### Vue Example

```vue
<template>
    <div class="classes-list">
        <div v-for="classItem in classes" :key="classItem.id" class="class-card">
            <h3>{{ classItem.course.name }}</h3>
            <p>Training Center: {{ classItem.training_center.name }}</p>
            <p>Instructor: {{ classItem.instructor.first_name }} {{ classItem.instructor.last_name }}</p>
            <p>Status: {{ classItem.status }}</p>
            <p>Dates: {{ classItem.start_date }} to {{ classItem.end_date }}</p>
            
            <!-- Trainees Section -->
            <div class="trainees-section">
                <h4>Trainees ({{ classItem.trainees.length }})</h4>
                <div class="trainees-list">
                    <div 
                        v-for="trainee in classItem.trainees" 
                        :key="trainee.id"
                        :class="['trainee-card', trainee.pivot.status]"
                    >
                        <div class="trainee-info">
                            <h5>{{ trainee.first_name }} {{ trainee.last_name }}</h5>
                            <p>Email: {{ trainee.email }}</p>
                            <p>Phone: {{ trainee.phone }}</p>
                            <p>ID: {{ trainee.id_number }}</p>
                        </div>
                        <div class="enrollment-info">
                            <span :class="['status-badge', trainee.pivot.status]">
                                {{ trainee.pivot.status }}
                            </span>
                            <p>Enrolled: {{ formatDate(trainee.pivot.enrolled_at) }}</p>
                            <p v-if="trainee.pivot.completed_at">
                                Completed: {{ formatDate(trainee.pivot.completed_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            classes: [],
            loading: true
        };
    },
    mounted() {
        this.fetchClasses();
    },
    methods: {
        async fetchClasses() {
            try {
                const response = await fetch('/api/acc/classes', {
                    headers: {
                        'Authorization': `Bearer ${this.token}`
                    }
                });
                const data = await response.json();
                this.classes = data.data;
            } catch (error) {
                console.error('Error fetching classes:', error);
            } finally {
                this.loading = false;
            }
        },
        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString();
        }
    }
}
</script>
```

### TypeScript Types

```typescript
interface Trainee {
    id: number;
    training_center_id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    id_number: string;
    id_image_url: string | null;
    card_image_url: string | null;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
    pivot: {
        training_class_id: number;
        trainee_id: number;
        status: 'enrolled' | 'completed' | 'cancelled';
        enrolled_at: string;
        completed_at: string | null;
        created_at: string;
        updated_at: string;
    };
}

interface TrainingClass {
    id: number;
    course_id: number;
    training_center_id: number;
    instructor_id: number;
    class_id: number;
    start_date: string;
    end_date: string;
    schedule_json: Record<string, string> | null;
    enrolled_count: number;
    max_capacity: number;
    status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled';
    location: 'physical' | 'online';
    location_details: string | null;
    created_at: string;
    updated_at: string;
    course: {
        id: number;
        name: string;
        code: string;
        acc_id: number;
        duration_hours: number;
        status: string;
    };
    training_center: {
        id: number;
        name: string;
        email: string;
        phone: string;
    };
    instructor: {
        id: number;
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
    };
    trainees: Trainee[]; // ⭐ NEW
}

interface ClassesResponse {
    data: TrainingClass[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number;
    to: number;
}
```

## Use Cases

### 1. Display Trainees in Class List

```jsx
// Show trainee count
<p>Enrolled: {classItem.trainees.length} / {classItem.max_capacity}</p>

// Show trainee names
<ul>
    {classItem.trainees.map(trainee => (
        <li key={trainee.id}>
            {trainee.first_name} {trainee.last_name}
        </li>
    ))}
</ul>
```

### 2. Filter Classes by Trainee Count

```javascript
// Show only classes with trainees
const classesWithTrainees = classes.filter(c => c.trainees.length > 0);

// Show only full classes
const fullClasses = classes.filter(c => c.trainees.length >= c.max_capacity);
```

### 3. Display Enrollment Status

```jsx
// Show enrollment statistics
const enrolledCount = classItem.trainees.filter(t => t.pivot.status === 'enrolled').length;
const completedCount = classItem.trainees.filter(t => t.pivot.status === 'completed').length;

<p>Enrolled: {enrolledCount} | Completed: {completedCount}</p>
```

### 4. Export Trainees List

```javascript
// Export trainees to CSV
function exportTrainees(classItem) {
    const csv = [
        ['Name', 'Email', 'Phone', 'ID Number', 'Status', 'Enrolled At', 'Completed At'],
        ...classItem.trainees.map(t => [
            `${t.first_name} ${t.last_name}`,
            t.email,
            t.phone,
            t.id_number,
            t.pivot.status,
            t.pivot.enrolled_at,
            t.pivot.completed_at || 'N/A'
        ])
    ].map(row => row.join(',')).join('\n');
    
    // Download CSV
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `class-${classItem.id}-trainees.csv`;
    a.click();
}
```

## Error Responses

### 401 Unauthorized
```json
{
    "message": "Unauthenticated."
}
```

### 404 Not Found (ACC)
```json
{
    "message": "ACC not found"
}
```

### 404 Not Found (Class)
```json
{
    "message": "Class not found or not authorized"
}
```

## Notes

1. **Empty Trainees Array**: If a class has no enrolled trainees, the `trainees` array will be empty `[]`.

2. **Pivot Data**: The `pivot` object contains enrollment-specific information. Use this to track enrollment status and dates.

3. **Performance**: Trainees are eager-loaded, so there's no N+1 query issue. However, for classes with many trainees, consider pagination or lazy loading.

4. **Authorization**: Only classes from training centers authorized by the ACC are returned.

5. **Trainee Status**: The `status` field in the trainee object refers to the trainee's account status, while `pivot.status` refers to their enrollment status in this specific class.

## Summary

✅ **Trainees Data** included in class responses  
✅ **Enrollment Details** (pivot) included for each trainee  
✅ **No Breaking Changes** - Existing fields remain unchanged  
✅ **Backward Compatible** - Frontend can ignore trainees if not needed  

The ACC classes endpoints now provide complete information about enrolled trainees, making it easier to manage and track class enrollments.

