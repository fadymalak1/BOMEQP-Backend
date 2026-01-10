# API Documentation - Training Center Categories & Sub-Categories

## Overview
This document describes the updated API endpoints for retrieving categories and sub-categories for Training Centers. These endpoints are used in the instructor authorization flow.

---

## Base URL
All endpoints are under: `/api/training-center`

**Authentication Required:** Yes (Bearer Token - Sanctum)

**Role Required:** `training_center_admin`

---

## Endpoints

### 1. Get Categories for an ACC

**Endpoint:** `GET /api/training-center/accs/{accId}/categories`

**Description:**  
Retrieves all categories assigned to a specific ACC (Accreditation Center). This endpoint should be called first to get the list of available categories for a selected ACC.

**URL Parameters:**
- `accId` (integer, required) - The ID of the ACC whose categories you want to retrieve

**Response Structure:**
- **Status Code:** 200 OK
- **Response Body:**
  - `categories` (array) - List of category objects
    - Each category object contains:
      - `id` (integer) - Category ID
      - `name` (string) - Category name in English
      - `name_ar` (string) - Category name in Arabic
      - `description` (string, nullable) - Category description
      - `icon_url` (string, nullable) - URL to category icon
      - `status` (string) - Status of the category (e.g., "active")
      - `created_by` (integer) - ID of user who created the category
      - `created_at` (timestamp) - Creation timestamp
      - `updated_at` (timestamp) - Last update timestamp
  - `acc` (object) - ACC information
    - `id` (integer) - ACC ID
    - `name` (string) - ACC name

**Error Responses:**
- **401 Unauthorized** - User is not authenticated
- **404 Not Found** - ACC with the provided ID does not exist

**Notes:**
- Only active categories are returned
- This endpoint should be used as the first step to get available categories after selecting an ACC

---

### 2. Get Sub-Categories for a Category

**Endpoint:** `GET /api/training-center/accs/{categoryId}/sub-categories`

**Description:**  
Retrieves all sub-categories that belong to a specific category. This endpoint should be called after selecting a category to get its sub-categories.

**Important Change:**  
⚠️ **This endpoint has been updated!**  
- **Previous version:** Used to accept `accId` parameter
- **Current version:** Now accepts `categoryId` parameter

**URL Parameters:**
- `categoryId` (integer, required) - The ID of the category whose sub-categories you want to retrieve

**Response Structure:**
- **Status Code:** 200 OK
- **Response Body:**
  - `sub_categories` (array) - List of sub-category objects
    - Each sub-category object contains:
      - `id` (integer) - Sub-category ID
      - `category_id` (integer) - Parent category ID
      - `name` (string) - Sub-category name in English
      - `name_ar` (string) - Sub-category name in Arabic
      - `description` (string, nullable) - Sub-category description
      - `status` (string) - Status of the sub-category (e.g., "active")
      - `created_by` (integer) - ID of user who created the sub-category
      - `created_at` (timestamp) - Creation timestamp
      - `updated_at` (timestamp) - Last update timestamp
  - `category` (object) - Category information
    - `id` (integer) - Category ID
    - `name` (string) - Category name in English
    - `name_ar` (string) - Category name in Arabic

**Error Responses:**
- **401 Unauthorized** - User is not authenticated
- **404 Not Found** - Category with the provided ID does not exist

**Notes:**
- Only active sub-categories are returned
- This endpoint should be called after selecting a category from the categories list

---

## Workflow Example

The typical flow for using these endpoints in the instructor authorization process:

1. **User selects an ACC**
   - Call: `GET /api/training-center/accs/{accId}/categories`
   - This returns all categories available for that ACC

2. **User selects a category from the list**
   - Use the `id` from the selected category object

3. **Get sub-categories for the selected category**
   - Call: `GET /api/training-center/accs/{categoryId}/sub-categories`
   - Pass the `categoryId` (not the accId)
   - This returns all sub-categories under that category

4. **User can then proceed with instructor authorization**
   - Sub-categories can be used for course selection or authorization requests

---

## Breaking Changes

⚠️ **Important:** The sub-categories endpoint has been changed!

**Old Endpoint:**
- `GET /api/training-center/accs/{accId}/sub-categories`
- Parameter was: `accId`

**New Endpoint:**
- `GET /api/training-center/accs/{categoryId}/sub-categories`
- Parameter is now: `categoryId`

**Migration Required:**
- Update all frontend calls to the sub-categories endpoint
- Change the parameter from `accId` to `categoryId`
- Ensure you're passing the category ID (from step 1) instead of the ACC ID

---

## Related Endpoints

For reference, here are related endpoints that work together:

1. **Get Courses for ACC** (unchanged)
   - `GET /api/training-center/accs/{accId}/courses`
   - Can optionally filter by sub_category_id using query parameter

2. **List ACCs** (unchanged)
   - `GET /api/training-center/accs`
   - Returns list of ACCs available to the training center

---

## Summary

### New Endpoint Added:
- ✅ `GET /api/training-center/accs/{accId}/categories` - Get categories for an ACC

### Modified Endpoint:
- ⚠️ `GET /api/training-center/accs/{categoryId}/sub-categories` - Changed parameter from `accId` to `categoryId`

### Recommended Flow:
1. Select ACC → Get Categories
2. Select Category → Get Sub-Categories
3. Use Sub-Categories for course selection/authorization

---

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Contact:** Backend Development Team

