# Training Center Certificates Endpoint Updates

## Overview
The Training Center Certificates endpoint has been updated with enhanced sorting, pagination, and search functionality to improve the user experience when managing certificates.

## Endpoint
**GET** `/v1/api/training-center/certificates`

## New Features

### 1. Automatic Sorting (Newest First)
- **Default Behavior**: Certificates are now automatically sorted by creation date in descending order (newest to oldest)
- **No Action Required**: This sorting is applied automatically - the most recently created certificates will appear at the top of the list
- **Implementation**: The endpoint now returns certificates sorted by `created_at` field in descending order

### 2. Enhanced Pagination
- **Pagination Support**: The endpoint fully supports pagination with customizable page size
- **Query Parameters**:
  - `per_page`: Number of certificates per page (default: 15, maximum: 1000)
  - `page`: Page number to retrieve (default: 1)
- **Response Format**: The response includes standard Laravel pagination metadata:
  - `data`: Array of certificate objects
  - `current_page`: Current page number
  - `per_page`: Number of items per page
  - `total`: Total number of certificates
  - `last_page`: Last page number
  - `from`: Starting record number
  - `to`: Ending record number
  - `links`: Navigation links (first, last, prev, next)

### 3. Search Functionality
- **New Query Parameter**: `search`
- **Search Fields**: The search functionality searches across multiple fields:
  - Trainee name (partial match)
  - Certificate number (partial match)
  - Verification code (partial match)
  - Course name (partial match)
- **Case Insensitive**: Search is case-insensitive
- **Partial Matching**: Uses "contains" matching, so partial text will find results
- **Usage**: Add `?search=John` to search for certificates containing "John" in any of the searchable fields

## Query Parameters Summary

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `status` | string | No | - | Filter by status: `valid`, `expired`, or `revoked` |
| `course_id` | integer | No | - | Filter by specific course ID |
| `search` | string | No | - | Search across trainee name, certificate number, verification code, and course name |
| `per_page` | integer | No | 15 | Number of certificates per page (1-1000) |
| `page` | integer | No | 1 | Page number to retrieve |

## Example Requests

### Basic Request (Newest First)
```
GET /v1/api/training-center/certificates
```
Returns the first 15 certificates, sorted by newest first.

### With Pagination
```
GET /v1/api/training-center/certificates?per_page=50&page=2
```
Returns 50 certificates per page, page 2.

### With Search
```
GET /v1/api/training-center/certificates?search=John
```
Returns certificates where trainee name, certificate number, verification code, or course name contains "John".

### Combined Filters
```
GET /v1/api/training-center/certificates?status=valid&search=John&per_page=100&page=1
```
Returns valid certificates matching "John" search term, 100 per page, first page.

## Response Structure

The response maintains the standard Laravel pagination format:

```json
{
  "data": [
    {
      "id": 1,
      "certificate_number": "CERT-2024-12345678",
      "trainee_name": "John Doe",
      "status": "valid",
      "issue_date": "2024-01-15",
      "expiry_date": "2026-01-15",
      "certificate_pdf_url": "https://...",
      "verification_code": "ABC123XYZ",
      "course": { ... },
      "instructor": { ... },
      "template": { ... },
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 150,
  "last_page": 10,
  "from": 1,
  "to": 15,
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

## Frontend Implementation Recommendations

### 1. Display Order
- **No Changes Needed**: Since sorting is automatic (newest first), the frontend can display certificates in the order received
- **Visual Indicator**: Consider adding a visual indicator (e.g., "New" badge) for recently created certificates

### 2. Pagination UI
- **Pagination Controls**: Implement standard pagination controls using the `links` object from the response
- **Page Size Selector**: Allow users to select `per_page` values (e.g., 15, 25, 50, 100)
- **Total Count Display**: Show the total number of certificates using the `total` field
- **Page Information**: Display "Showing X to Y of Z certificates" using `from`, `to`, and `total` fields

### 3. Search Implementation
- **Search Input**: Add a search input field that sends the `search` query parameter
- **Debouncing**: Implement debouncing (300-500ms) to avoid excessive API calls while typing
- **Clear Button**: Provide a clear button to reset the search
- **Search Indicators**: Show which fields are being searched (trainee name, certificate number, etc.)
- **Empty State**: Display a helpful message when no results are found

### 4. Filter Combinations
- **Multiple Filters**: Support combining search with status and course_id filters
- **Filter Reset**: Provide a "Clear All Filters" button
- **Active Filters Display**: Show active filters as chips/badges that can be removed individually

### 5. Performance Considerations
- **Loading States**: Show loading indicators during API calls
- **Error Handling**: Handle pagination edge cases (e.g., user navigates to page that no longer exists)
- **Caching**: Consider caching the first page of results for better performance
- **Infinite Scroll Alternative**: For better UX, consider implementing infinite scroll instead of traditional pagination

## Migration Notes

### Breaking Changes
- **None**: All changes are backward compatible. Existing implementations will continue to work.

### New Default Behavior
- Certificates are now sorted by newest first by default. If your frontend was previously sorting client-side, you may want to remove that logic to avoid double sorting.

### Recommended Updates
1. **Remove Client-Side Sorting**: If you were sorting certificates on the frontend, remove that logic since the backend now handles it
2. **Add Search UI**: Implement a search input field to take advantage of the new search functionality
3. **Enhance Pagination**: Update pagination UI to use the full pagination metadata provided in the response
4. **Update Filter UI**: Ensure filter combinations work correctly with the new search parameter

## Testing Recommendations

### Test Cases
1. **Default Sorting**: Verify newest certificates appear first
2. **Pagination**: Test navigation through multiple pages
3. **Search**: Test searching by:
   - Trainee name (partial and full)
   - Certificate number
   - Verification code
   - Course name
4. **Filter Combinations**: Test combining search with status and course_id filters
5. **Edge Cases**:
   - Empty search results
   - Very large result sets
   - Special characters in search terms
   - Pagination beyond available pages

## Support

For questions or issues related to these updates, please refer to the API documentation or contact the development team.

