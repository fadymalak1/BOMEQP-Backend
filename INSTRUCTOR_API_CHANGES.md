# Instructor API Changes - Search and Filter Enhancement

## Overview

This document describes the enhancements made to the instructor listing endpoints across Admin, ACC, and Training Center modules. The updates provide comprehensive search and filtering capabilities for instructor data, including support for searching in JSON fields and filtering by multiple criteria.

## Affected Endpoints

### 1. Admin Instructor Endpoint
**Endpoint:** `GET /api/admin/instructors`

### 2. ACC Instructor Endpoint
**Endpoint:** `GET /api/acc/instructors`

### 3. Training Center Instructor Endpoint
**Endpoint:** `GET /api/training-center/instructors`

---

## New Filter Parameters

All three endpoints now support the following additional filter parameters:

### Country Filter
- **Parameter:** `country`
- **Type:** String
- **Description:** Filters instructors by country. Supports partial matching.
- **Example:** `?country=USA` or `?country=United`

### City Filter
- **Parameter:** `city`
- **Type:** String
- **Description:** Filters instructors by city. Supports partial matching.
- **Example:** `?city=New York` or `?city=London`

### Assessor Status Filter
- **Parameter:** `is_assessor`
- **Type:** Boolean
- **Description:** Filters instructors by assessor status. Use `true` for Assessors and `false` for regular Instructors.
- **Example:** `?is_assessor=true` or `?is_assessor=false`

### Status Filter (Training Center Only)
- **Parameter:** `status`
- **Type:** String (enum)
- **Description:** Filters instructors by status. Available values: `pending`, `active`, `suspended`, `inactive`.
- **Example:** `?status=active`
- **Note:** This filter was already available in Admin endpoint and is now also available in Training Center endpoint.

---

## Enhanced Search Functionality

The `search` parameter has been significantly enhanced to search across multiple fields and data types.

### Searchable Fields

The search functionality now covers the following fields:

#### Basic Text Fields
- First name
- Last name
- Full name (both "First Last" and "Last First" formats)
- Email address
- Phone number
- ID number
- Country
- City

#### JSON Fields
- **certificates_json**: Searches within certificate data stored in JSON format
- **specializations**: Searches within specialization arrays stored in JSON format

### Search Behavior

- **Case-insensitive**: Search is not case-sensitive
- **Partial matching**: Supports partial word matching
- **Multiple fields**: Searches across all listed fields simultaneously
- **JSON support**: Can find data stored in JSON arrays and objects within certificates and specializations

### Use Cases

The enhanced search is particularly useful for finding instructors by:
- Language preferences (if stored in specializations or certificates)
- Nationality information (if stored in certificates or other JSON fields)
- Certification details
- Specialization areas
- Any other data stored in the JSON fields

---

## Endpoint-Specific Details

### Admin Instructor Endpoint

**Existing Filters:**
- `status`: Filter by instructor status
- `training_center_id`: Filter by training center

**New Filters:**
- `country`: Filter by country
- `city`: Filter by city
- `is_assessor`: Filter by assessor status

**Search Enhancement:**
- Searches across all instructor fields including JSON data
- Does not search training center information

### ACC Instructor Endpoint

**Existing Filters:**
- None (only search was available)

**New Filters:**
- `country`: Filter by country
- `city`: Filter by city
- `is_assessor`: Filter by assessor status

**Search Enhancement:**
- Searches across all instructor fields including JSON data
- Also searches training center name

**Note:** This endpoint only returns instructors who have approved authorizations with the authenticated ACC.

### Training Center Instructor Endpoint

**Existing Filters:**
- None (only search was available)

**New Filters:**
- `status`: Filter by instructor status
- `country`: Filter by country
- `city`: Filter by city
- `is_assessor`: Filter by assessor status

**Search Enhancement:**
- Searches across all instructor fields including JSON data
- Only returns instructors belonging to the authenticated training center

---

## Usage Examples

### Example 1: Search by Name and Filter by Country
**Request:**
```
GET /api/admin/instructors?search=John&country=USA
```
**Description:** Finds instructors with "John" in their name, email, or other fields, and filters results to only those in USA.

### Example 2: Filter by City and Assessor Status
**Request:**
```
GET /api/acc/instructors?city=New York&is_assessor=false
```
**Description:** Returns regular instructors (not assessors) located in New York who have approved authorizations with the ACC.

### Example 3: Search for Language Preference
**Request:**
```
GET /api/training-center/instructors?search=Arabic
```
**Description:** Searches for "Arabic" across all fields including JSON data (certificates_json and specializations), which may contain language preferences.

### Example 4: Combined Filters
**Request:**
```
GET /api/admin/instructors?status=active&country=UAE&city=Dubai&is_assessor=true
```
**Description:** Returns active assessors located in Dubai, UAE.

### Example 5: Search with Multiple Criteria
**Request:**
```
GET /api/training-center/instructors?search=English&status=active&is_assessor=false
```
**Description:** Searches for "English" across all fields and returns only active regular instructors (not assessors).

---

## Filter Combination Rules

- **All filters are optional**: You can use any combination of filters
- **Filters are AND conditions**: Multiple filters are combined with AND logic
- **Search is OR condition**: The search parameter searches across multiple fields with OR logic
- **Search and filters work together**: Search results are further filtered by the filter parameters

---

## Response Structure

The response structure remains unchanged. All endpoints continue to return the same pagination and data structure as before. The only difference is that the results are now filtered and searched according to the new parameters.

### Response Format (Unchanged)

**Admin Endpoint:**
- `instructors`: Array of instructor objects
- `statistics`: Object with total counts by status
- `pagination`: Pagination metadata

**ACC Endpoint:**
- `instructors`: Array of instructor objects
- `pagination`: Pagination metadata

**Training Center Endpoint:**
- `data`: Array of instructor objects
- `statistics`: Object with total counts by status
- `pagination`: Pagination metadata

---

## Backward Compatibility

All changes are **fully backward compatible**. Existing API calls will continue to work exactly as before:

- Endpoints without new parameters return the same results as before
- Existing filter parameters continue to work as before
- The search parameter works the same way but now searches more fields

---

## Implementation Notes

### JSON Field Searching

The implementation uses MySQL's JSON_SEARCH function to search within JSON fields. This allows finding data stored in:
- Arrays within `certificates_json`
- Arrays within `specializations`
- Nested objects within JSON structures

### Performance Considerations

- Indexes on frequently filtered fields (country, city, status) are recommended for optimal performance
- JSON searches may be slower on large datasets; consider adding appropriate indexes if needed
- Filtering before searching can improve performance

### Database Compatibility

The JSON search functionality requires MySQL 5.7+ or MariaDB 10.2.3+ for JSON_SEARCH support.

---

## Migration Guide

### For Frontend Developers

1. **Update API calls** to include new filter parameters as needed
2. **Enhance search UI** to take advantage of the expanded search capabilities
3. **Add filter UI components** for country, city, and assessor status
4. **Test search functionality** with JSON-stored data (languages, nationalities, etc.)

### For API Consumers

1. **Review existing integrations** - no changes required for backward compatibility
2. **Consider adding filters** to reduce result sets and improve performance
3. **Leverage enhanced search** for finding instructors by any stored data
4. **Test with real data** to understand search behavior with your specific JSON structures

---

## Benefits

1. **Improved User Experience**: Users can now find instructors more easily using multiple criteria
2. **Better Filtering**: Multiple filter options allow precise result sets
3. **JSON Data Access**: Search can find data stored in JSON fields (languages, nationalities, etc.)
4. **Consistent API**: All three endpoints now have similar filtering capabilities
5. **Flexible Queries**: Combine search and filters for powerful querying

---

## Future Enhancements

Potential future improvements could include:

- Date range filtering (e.g., by creation date, authorization date)
- Advanced JSON query syntax
- Full-text search capabilities
- Sorting by multiple fields
- Export functionality with filters applied

---

## Support

For questions or issues related to these API changes, please refer to the main API documentation or contact the development team.

