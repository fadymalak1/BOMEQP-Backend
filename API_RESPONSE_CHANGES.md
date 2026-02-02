# API Response Changes - Training Center Authorization Requests

## Endpoint
`GET /api/acc/training-centers/requests`

## Overview
The endpoint has been updated to group multiple requests from the same training center into a single entry, showing the latest request status and including a history of previous requests.

## Key Changes

### Before
- Each request was returned as a separate item in the response
- Multiple requests from the same training center appeared as separate entries
- No history of previous requests was available

### After
- Only one entry per training center is returned
- The entry shows the **latest request** (most recent by `request_date`)
- Previous requests are included in a `previous_requests` array
- Each entry includes `total_requests_count` showing the total number of requests

## Response Structure

### Main Response Object
```json
{
    "data": [
        {
            // Latest request details
            "id": 25,
            "training_center_id": 18,
            "acc_id": 7,
            "request_date": "2026-02-02T17:04:12.000000Z",
            "status": "pending",
            "group_commission_percentage": null,
            "rejection_reason": null,
            "return_comment": null,
            "reviewed_by": null,
            "reviewed_at": null,
            "documents_json": [...],
            "created_at": "2026-02-02T17:04:12.000000Z",
            "updated_at": "2026-02-02T17:04:12.000000Z",
            
            // NEW: Previous requests history
            "previous_requests": [
                {
                    "id": 24,
                    "request_date": "2026-02-02T17:02:16.000000Z",
                    "status": "returned",
                    "rejection_reason": null,
                    "return_comment": "m4 mohm el resala ya 7biby a7na bngrb",
                    "reviewed_by": 22,
                    "reviewed_at": "2026-02-02T17:03:34.000000Z",
                    "documents_count": 2
                },
                {
                    "id": 23,
                    "request_date": "2026-02-02T16:53:12.000000Z",
                    "status": "rejected",
                    "rejection_reason": "Sorry We Cannot Work With U",
                    "return_comment": "Sorry We Need More Info About U",
                    "reviewed_by": 22,
                    "reviewed_at": "2026-02-02T16:55:55.000000Z",
                    "documents_count": 1
                }
            ],
            
            // NEW: Total requests count
            "total_requests_count": 3,
            
            // Training center details (unchanged)
            "training_center": {
                "id": 18,
                "name": "TC10",
                // ... other training center fields
            }
        }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 1,  // Now counts unique training centers, not total requests
    "last_page": 1,
    "from": 1,
    "to": 1,
    "statistics": {
        "total": 1,  // Unique training centers
        "pending": 1,
        "approved": 0,
        "rejected": 0,
        "returned": 0,
        "last_7_days": 1,
        "last_30_days": 1,
        "pending_older_than_7_days": 0
    }
}
```

## New Fields

### `previous_requests` (Array)
An array containing summary information about all previous requests for the same training center. Each item includes:

- `id` (integer): Request ID
- `request_date` (datetime): When the request was made
- `status` (string): Request status (`pending`, `approved`, `rejected`, `returned`)
- `rejection_reason` (string|null): Reason for rejection (if rejected)
- `return_comment` (string|null): Comment when returned (if returned)
- `reviewed_by` (integer|null): User ID who reviewed the request
- `reviewed_at` (datetime|null): When the request was reviewed
- `documents_count` (integer): Number of documents in the request

**Note:** The array is ordered by `request_date` descending (most recent first, excluding the latest which is in the main object).

### `total_requests_count` (integer)
Total number of requests made by this training center to the ACC, including the current one.

## Statistics Changes

The statistics now count **unique training centers** based on their **latest request status**, not total requests:

- `total`: Number of unique training centers with requests
- `pending`: Training centers with latest request status = `pending`
- `approved`: Training centers with latest request status = `approved`
- `rejected`: Training centers with latest request status = `rejected`
- `returned`: Training centers with latest request status = `returned`
- `last_7_days`: Training centers with latest request in last 7 days
- `last_30_days`: Training centers with latest request in last 30 days
- `pending_older_than_7_days`: Training centers with pending requests older than 7 days

## Filtering and Search

### Status Filter
When filtering by status (`?status=pending`), the filter is applied to the **latest request status** of each training center.

### Search
Search functionality works on:
- Request ID
- Training center name
- Training center email
- Training center country
- Training center city

Search is applied after grouping, so it searches within the latest requests.

## Example Scenarios

### Scenario 1: Training Center with Multiple Requests
**Before:** 3 separate entries (IDs: 25, 24, 23)
**After:** 1 entry with:
- Main data from request ID 25 (latest)
- `previous_requests` containing IDs 24 and 23
- `total_requests_count`: 3

### Scenario 2: Training Center with Single Request
**Before:** 1 entry
**After:** 1 entry with:
- Main data from the single request
- `previous_requests`: Empty array `[]`
- `total_requests_count`: 1

### Scenario 3: Multiple Training Centers
**Before:** 6 entries (some training centers had multiple requests)
**After:** 4 entries (one per unique training center)
- Each entry shows latest request + previous requests history

## Migration Notes

### Frontend Changes Required

1. **Update Response Parsing**
   - Access latest request data directly from the main object
   - Use `previous_requests` array to display request history
   - Use `total_requests_count` to show total requests badge/count

2. **Update Statistics Display**
   - Statistics now represent unique training centers, not total requests
   - Update any labels or tooltips that reference "total requests"

3. **Update Request History UI**
   - Add UI to display `previous_requests` array
   - Show status, dates, and comments for previous requests
   - Consider a timeline or accordion view for request history

4. **Pagination**
   - Pagination now works on unique training centers
   - Total count represents unique training centers, not total requests

## Benefits

1. **Reduced Duplication**: No more multiple entries for the same training center
2. **Better Overview**: See current status at a glance
3. **Request History**: Access to all previous requests in one place
4. **Cleaner UI**: Easier to display and manage in frontend
5. **Accurate Statistics**: Statistics reflect unique training centers, not duplicate requests

## Backward Compatibility

⚠️ **Breaking Change**: This is a breaking change for frontend applications. The response structure has changed significantly:

- Response array length will be different (fewer items)
- New fields added: `previous_requests`, `total_requests_count`
- Statistics calculations have changed
- Pagination totals have changed

Frontend applications must be updated to handle the new response structure.

