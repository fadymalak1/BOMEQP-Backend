# Dashboard APIs Documentation

This document describes the dashboard API endpoints for Group Admin, ACC Admin, and Training Center Admin roles.

**Base URL:** `/api`

**Authentication:** All endpoints require Bearer token authentication.

---

## 1. Group Admin Dashboard

**Endpoint:** `GET /admin/dashboard`

**Description:** Get dashboard statistics for Group Admin including total counts of accreditation bodies, training centers, instructors, and revenue information.

**Authorization:** Requires `group_admin` role

**Response (200):**
```json
{
  "accreditation_bodies": 12,
  "training_centers": 17,
  "instructors": 15,
  "revenue": {
    "monthly": 0.00,
    "total": 91254.00
  }
}
```

**Response Fields:**
- `accreditation_bodies` (integer): Total number of approved ACCs
- `training_centers` (integer): Total number of active training centers
- `instructors` (integer): Total number of active instructors
- `revenue.monthly` (float): Revenue for the current month
- `revenue.total` (float): Total revenue from all completed transactions

**Error Responses:**
- `401 Unauthorized`: User is not authenticated
- `403 Forbidden`: User does not have group_admin role

---

## 2. ACC Admin Dashboard

**Endpoint:** `GET /acc/dashboard`

**Description:** Get dashboard statistics for ACC Admin including pending requests, active training centers, active instructors, certificates generated, and revenue information.

**Authorization:** Requires `acc_admin` role

**Response (200):**
```json
{
  "pending_requests": 2,
  "active_training_centers": 1,
  "active_instructors": 9,
  "certificates_generated": 0,
  "revenue": {
    "monthly": 46700.00,
    "total": 46700.00
  }
}
```

**Response Fields:**
- `pending_requests` (integer): Total pending requests (training centers + instructors)
- `active_training_centers` (integer): Number of training centers with approved authorization
- `active_instructors` (integer): Number of instructors with approved authorization
- `certificates_generated` (integer): Total number of certificates generated for courses belonging to this ACC
- `revenue.monthly` (float): Revenue for the current month
- `revenue.total` (float): Total revenue from all completed transactions

**Error Responses:**
- `401 Unauthorized`: User is not authenticated
- `403 Forbidden`: User does not have acc_admin role
- `404 Not Found`: ACC not found for the authenticated user

---

## 3. Training Center Admin Dashboard

**Endpoint:** `GET /training-center/dashboard`

**Description:** Get dashboard statistics for Training Center Admin including authorized accreditations, classes, instructors, certificates, and training center state information.

**Authorization:** Requires `training_center_admin` role

**Response (200):**
```json
{
  "authorized_accreditations": 3,
  "classes": 4,
  "instructors": 10,
  "certificates": 0,
  "training_center_state": {
    "status": "active",
    "registration_date": "2024-01-15",
    "accreditation_status": "Verified"
  }
}
```

**Response Fields:**
- `authorized_accreditations` (integer): Number of ACCs with approved authorization for this training center
- `classes` (integer): Total number of training classes
- `instructors` (integer): Total number of instructors belonging to this training center
- `certificates` (integer): Total number of certificates issued by this training center
- `training_center_state.status` (string): Training center status (e.g., "active", "pending", "suspended")
- `training_center_state.registration_date` (string|null): Registration date in YYYY-MM-DD format, or null if not available
- `training_center_state.accreditation_status` (string): "Verified" if training center has at least one approved authorization, otherwise "Not Verified"

**Error Responses:**
- `401 Unauthorized`: User is not authenticated
- `403 Forbidden`: User does not have training_center_admin role
- `404 Not Found`: Training center not found for the authenticated user

---

## Frontend Implementation Notes

### Request Headers
All requests must include:
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Example Request (JavaScript/Axios)

```javascript
// Group Admin Dashboard
const getGroupAdminDashboard = async () => {
  const response = await axios.get('/api/admin/dashboard', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  return response.data;
};

// ACC Admin Dashboard
const getACCDashboard = async () => {
  const response = await axios.get('/api/acc/dashboard', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  return response.data;
};

// Training Center Admin Dashboard
const getTrainingCenterDashboard = async () => {
  const response = await axios.get('/api/training-center/dashboard', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  return response.data;
};
```

### Dashboard Cards Mapping

**Group Admin Dashboard:**
- Accreditation Bodies → `accreditation_bodies`
- Training Centers → `training_centers`
- Instructors → `instructors`
- Total Revenue → `revenue.total`
- Monthly Revenue → `revenue.monthly`

**ACC Admin Dashboard:**
- Pending Requests → `pending_requests`
- Active Training Centers → `active_training_centers`
- Active Instructors → `active_instructors`
- Certificates Generated → `certificates_generated`
- Monthly Revenue → `revenue.monthly`
- Total Revenue → `revenue.total`

**Training Center Admin Dashboard:**
- Authorized Accreditation → `authorized_accreditations`
- Classes → `classes`
- Instructors → `instructors`
- Certificates → `certificates`
- Status → `training_center_state.status`
- Registration Date → `training_center_state.registration_date`
- Accreditation Status → `training_center_state.accreditation_status`

---

## Notes

1. All revenue values are returned as floats with 2 decimal places.
2. Counts are based on active/approved records only (where applicable).
3. Monthly revenue is calculated for the current month and year.
4. Total revenue includes all completed transactions regardless of date.
5. The `registration_date` may be null if the training center was created before this field was tracked.
6. `accreditation_status` is "Verified" if the training center has at least one approved ACC authorization, otherwise "Not Verified".

---

## Testing

You can test these endpoints using:
- Postman
- cURL
- Swagger UI (if available at `/api/documentation`)

**Example cURL:**
```bash
# Group Admin Dashboard
curl -X GET "http://your-domain.com/api/admin/dashboard" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# ACC Admin Dashboard
curl -X GET "http://your-domain.com/api/acc/dashboard" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# Training Center Admin Dashboard
curl -X GET "http://your-domain.com/api/training-center/dashboard" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

**Last Updated:** December 29, 2025

