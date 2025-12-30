# Training Center Registration Approval Changes

**Date:** December 29, 2025  
**Status:** ✅ Implemented

---

## Overview

Training center registration has been updated to require **Group Admin approval** before activation. Previously, training centers were automatically activated upon registration. Now, both Training Centers and ACCs follow the same approval workflow.

---

## What Changed

### Before
- Training centers were automatically set to `active` status upon registration
- Training center admin users were immediately `active`
- No approval process required

### After
- Training centers are set to `pending` status upon registration
- Training center admin users are set to `pending` status
- Group Admin must approve training center applications
- Training center admins receive notifications when approved/rejected

---

## Impact on Frontend

### 1. Registration Flow

**No changes required** - The registration endpoint remains the same, but the response behavior is different:

**Endpoint:** `POST /api/auth/register`

**Request Body (unchanged):**
```json
{
  "name": "ABC Training Center",
  "email": "info@abctraining.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "training_center_admin",
  "country": "Egypt",
  "city": "Cairo",
  "address": "123 Main St",
  "phone": "+201234567890"
}
```

**Response (unchanged format):**
```json
{
  "message": "Registration successful",
  "user": {
    "id": 1,
    "name": "ABC Training Center",
    "email": "info@abctraining.com",
    "role": "training_center_admin",
    "status": "pending"
  },
  "token": "1|xxxxxxxxxxxxx"
}
```

**Important:** The user `status` will now be `"pending"` instead of `"active"`.

### 2. Post-Registration User Experience

After registration, the training center admin should see a **pending approval message** instead of immediate access to the dashboard.

**Recommended UI Flow:**
1. Show success message: "Registration successful! Your application is pending approval."
2. Display a waiting/approval screen instead of redirecting to dashboard
3. Show message: "Your training center application is under review. You will be notified once approved."
4. Optionally show a logout button

**Example Pending Approval Screen:**
```
✅ Registration Successful!

Your Training Center application has been submitted and is pending approval.

Status: Pending Review
Message: A Group Admin will review your application shortly. You will receive a notification once your application is approved or rejected.

[Logout]
```

### 3. Dashboard Access

**Important:** Training center admins with `pending` status should **NOT** be able to access the dashboard or other protected routes.

**Frontend Check:**
```javascript
// Check user status before allowing dashboard access
if (user.status === 'pending') {
  // Redirect to pending approval page
  router.push('/pending-approval');
  return;
}

// Only allow access if status is 'active'
if (user.status === 'active') {
  // Allow dashboard access
}
```

### 4. Notification Handling

Training center admins will receive notifications when their application is:
- **Approved** → Status changes to `active`, user can access dashboard
- **Rejected** → Status remains `pending` or changes to `inactive`, show rejection reason

**Notification Types:**
- `training_center_approved` - Application approved
- `training_center_rejected` - Application rejected

**Example Notification Handling:**
```javascript
// Listen for notifications
socket.on('notification', (notification) => {
  if (notification.type === 'training_center_approved') {
    // Refresh user data
    // Update status to 'active'
    // Redirect to dashboard
    showSuccessMessage('Your application has been approved!');
    router.push('/dashboard');
  }
  
  if (notification.type === 'training_center_rejected') {
    // Show rejection reason
    showErrorMessage(`Application rejected: ${notification.data.reason}`);
    // Keep on pending approval page
  }
});
```

---

## New API Endpoints (Group Admin Only)

These endpoints are for **Group Admin** to manage training center applications. Frontend developers working on the admin panel should implement these.

### 1. Get Training Center Applications

**Endpoint:** `GET /api/admin/training-centers/applications`

**Description:** Get all pending training center applications

**Authorization:** Requires `group_admin` role

**Response (200):**
```json
{
  "applications": [
    {
      "id": 1,
      "name": "ABC Training Center",
      "legal_name": "ABC Training Center",
      "registration_number": "TC-ABC12345",
      "email": "info@abctraining.com",
      "phone": "+201234567890",
      "country": "Egypt",
      "city": "Cairo",
      "address": "123 Main St",
      "status": "pending",
      "created_at": "2025-12-29T10:00:00.000000Z",
      "updated_at": "2025-12-29T10:00:00.000000Z"
    }
  ]
}
```

**Frontend Implementation:**
```javascript
const getTrainingCenterApplications = async () => {
  const response = await axios.get('/api/admin/training-centers/applications', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  });
  return response.data.applications;
};
```

### 2. Approve Training Center Application

**Endpoint:** `PUT /api/admin/training-centers/applications/{id}/approve`

**Description:** Approve a training center application

**Authorization:** Requires `group_admin` role

**Parameters:**
- `id` (path parameter): Training center ID

**Response (200):**
```json
{
  "message": "Training center application approved",
  "training_center": {
    "id": 1,
    "name": "ABC Training Center",
    "status": "active",
    ...
  }
}
```

**Frontend Implementation:**
```javascript
const approveTrainingCenter = async (trainingCenterId) => {
  const response = await axios.put(
    `/api/admin/training-centers/applications/${trainingCenterId}/approve`,
    {},
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    }
  );
  return response.data;
};
```

### 3. Reject Training Center Application

**Endpoint:** `PUT /api/admin/training-centers/applications/{id}/reject`

**Description:** Reject a training center application with a reason

**Authorization:** Requires `group_admin` role

**Parameters:**
- `id` (path parameter): Training center ID

**Request Body:**
```json
{
  "rejection_reason": "Incomplete documentation. Please provide business license."
}
```

**Response (200):**
```json
{
  "message": "Training center application rejected",
  "training_center": {
    "id": 1,
    "name": "ABC Training Center",
    "status": "inactive",
    ...
  }
}
```

**Frontend Implementation:**
```javascript
const rejectTrainingCenter = async (trainingCenterId, rejectionReason) => {
  const response = await axios.put(
    `/api/admin/training-centers/applications/${trainingCenterId}/reject`,
    {
      rejection_reason: rejectionReason
    },
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    }
  );
  return response.data;
};
```

---

## Frontend Implementation Checklist

### For Training Center Registration Flow

- [ ] Update registration success message to indicate pending approval
- [ ] Create a "Pending Approval" page/screen
- [ ] Redirect to pending approval page after registration (if status is pending)
- [ ] Block dashboard access for users with `pending` status
- [ ] Handle notification for approval (`training_center_approved`)
- [ ] Handle notification for rejection (`training_center_rejected`)
- [ ] Show rejection reason when application is rejected
- [ ] Refresh user status after receiving approval/rejection notification

### For Group Admin Panel

- [ ] Create "Training Center Applications" page
- [ ] List all pending training center applications
- [ ] Display application details (name, email, country, city, etc.)
- [ ] Add "Approve" button for each application
- [ ] Add "Reject" button with reason input field
- [ ] Show confirmation dialog before approve/reject
- [ ] Update application list after approve/reject action
- [ ] Show success/error messages for actions

---

## Example Frontend Code

### React Example - Pending Approval Component

```jsx
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useNotifications } from '../contexts/NotificationContext';

function PendingApproval() {
  const { user, refreshUser } = useAuth();
  const { notifications } = useNotifications();
  const navigate = useNavigate();
  const [rejectionReason, setRejectionReason] = useState(null);

  useEffect(() => {
    // Check if user is still pending
    if (user?.status === 'active') {
      navigate('/dashboard');
    }
  }, [user, navigate]);

  useEffect(() => {
    // Listen for approval/rejection notifications
    const approvalNotification = notifications.find(
      n => n.type === 'training_center_approved' && !n.is_read
    );
    
    const rejectionNotification = notifications.find(
      n => n.type === 'training_center_rejected' && !n.is_read
    );

    if (approvalNotification) {
      refreshUser();
      navigate('/dashboard');
    }

    if (rejectionNotification) {
      setRejectionReason(rejectionNotification.data?.reason);
    }
  }, [notifications, navigate, refreshUser]);

  if (rejectionReason) {
    return (
      <div className="pending-approval">
        <div className="status-icon rejected">❌</div>
        <h1>Application Rejected</h1>
        <p>Your Training Center application has been rejected.</p>
        <div className="rejection-reason">
          <strong>Reason:</strong>
          <p>{rejectionReason}</p>
        </div>
        <button onClick={() => navigate('/logout')}>Logout</button>
      </div>
    );
  }

  return (
    <div className="pending-approval">
      <div className="status-icon pending">⏳</div>
      <h1>Application Pending</h1>
      <p>Your Training Center application is under review.</p>
      <p>A Group Admin will review your application shortly.</p>
      <p>You will receive a notification once your application is approved or rejected.</p>
      <button onClick={() => navigate('/logout')}>Logout</button>
    </div>
  );
}

export default PendingApproval;
```

### React Example - Admin Applications List

```jsx
import { useEffect, useState } from 'react';
import axios from 'axios';

function TrainingCenterApplications() {
  const [applications, setApplications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [selectedApp, setSelectedApp] = useState(null);
  const [rejectionReason, setRejectionReason] = useState('');

  useEffect(() => {
    loadApplications();
  }, []);

  const loadApplications = async () => {
    try {
      const response = await axios.get('/api/admin/training-centers/applications', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      setApplications(response.data.applications);
    } catch (error) {
      console.error('Error loading applications:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleApprove = async (id) => {
    if (!confirm('Are you sure you want to approve this training center?')) {
      return;
    }

    try {
      await axios.put(`/api/admin/training-centers/applications/${id}/approve`, {}, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      alert('Training center approved successfully!');
      loadApplications();
    } catch (error) {
      alert('Error approving training center');
      console.error(error);
    }
  };

  const handleReject = async () => {
    if (!rejectionReason.trim()) {
      alert('Please provide a rejection reason');
      return;
    }

    try {
      await axios.put(
        `/api/admin/training-centers/applications/${selectedApp.id}/reject`,
        { rejection_reason: rejectionReason },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );
      alert('Training center rejected');
      setShowRejectModal(false);
      setRejectionReason('');
      setSelectedApp(null);
      loadApplications();
    } catch (error) {
      alert('Error rejecting training center');
      console.error(error);
    }
  };

  if (loading) return <div>Loading...</div>;

  return (
    <div>
      <h1>Training Center Applications</h1>
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Country</th>
            <th>City</th>
            <th>Registration Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {applications.map(app => (
            <tr key={app.id}>
              <td>{app.name}</td>
              <td>{app.email}</td>
              <td>{app.country}</td>
              <td>{app.city}</td>
              <td>{new Date(app.created_at).toLocaleDateString()}</td>
              <td>
                <button onClick={() => handleApprove(app.id)}>Approve</button>
                <button onClick={() => {
                  setSelectedApp(app);
                  setShowRejectModal(true);
                }}>Reject</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {showRejectModal && (
        <div className="modal">
          <h2>Reject Application</h2>
          <p>Training Center: {selectedApp?.name}</p>
          <label>
            Rejection Reason:
            <textarea
              value={rejectionReason}
              onChange={(e) => setRejectionReason(e.target.value)}
              placeholder="Enter rejection reason..."
              rows={4}
            />
          </label>
          <div>
            <button onClick={handleReject}>Confirm Reject</button>
            <button onClick={() => {
              setShowRejectModal(false);
              setRejectionReason('');
              setSelectedApp(null);
            }}>Cancel</button>
          </div>
        </div>
      )}
    </div>
  );
}

export default TrainingCenterApplications;
```

---

## Testing Checklist

### Training Center Registration
- [ ] Register a new training center
- [ ] Verify user status is `pending` after registration
- [ ] Verify redirect to pending approval page
- [ ] Verify dashboard is not accessible
- [ ] Test notification when approved
- [ ] Test notification when rejected
- [ ] Verify dashboard access after approval

### Group Admin Panel
- [ ] View pending training center applications
- [ ] Approve a training center application
- [ ] Verify training center status changes to `active`
- [ ] Verify user status changes to `active`
- [ ] Reject a training center application with reason
- [ ] Verify training center status changes to `inactive`
- [ ] Verify notification is sent to training center admin

---

## Migration Notes

**For Existing Training Centers:**
- Existing training centers with `active` status are not affected
- Only new registrations will be set to `pending`
- No database migration required for existing data

**For Frontend:**
- Update authentication/authorization checks to handle `pending` status
- Add pending approval UI components
- Update admin panel to include training center approval functionality

---

## Support

If you have any questions or need clarification on these changes, please contact the backend development team.

**Last Updated:** December 29, 2025

