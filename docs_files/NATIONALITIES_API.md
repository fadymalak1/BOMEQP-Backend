# Nationalities API - Documentation

## Overview
This document describes the Nationalities API endpoint that provides a list of all available nationalities for selection throughout the application.

## Date
January 22, 2026

---

## API Endpoint

### Get All Nationalities
**Endpoint**: `GET /v1/api/nationalities`

**Description**: Returns a list of all active nationalities ordered by sort order and name. This is a public endpoint that can be used anywhere nationality selection is needed.

**Authentication**: Not required (Public endpoint)

**Request Parameters**: None

---

## Response Format

### Success Response (200 OK)

```json
{
  "nationalities": [
    {
      "id": 1,
      "name": "Egyptian",
      "code": "EGY",
      "sort_order": 1
    },
    {
      "id": 2,
      "name": "Saudi Arabian",
      "code": "SAU",
      "sort_order": 2
    },
    {
      "id": 3,
      "name": "Emirati",
      "code": "ARE",
      "sort_order": 3
    }
  ]
}
```

**Response Fields**:
- `id` (integer): Unique identifier for the nationality
- `name` (string): Display name of the nationality
- `code` (string, nullable): ISO 3166-1 alpha-3 country code (optional)
- `sort_order` (integer): Order for sorting nationalities

---

## Usage

### Where to Use
This API can be used in any form or dropdown where nationality selection is required, including:

1. **Trainee Registration/Update Forms**
   - Field: `nationality` (required)

2. **Instructor Registration/Update Forms**
   - May be used for nationality field if added in the future

3. **ACC/Training Center Contact Forms**
   - Primary/Secondary contact nationality fields

4. **Any other forms requiring nationality selection**

### Frontend Implementation Example

```javascript
// Fetch nationalities
const fetchNationalities = async () => {
  try {
    const response = await fetch('https://aeroenix.com/v1/api/nationalities');
    const data = await response.json();
    return data.nationalities;
  } catch (error) {
    console.error('Error fetching nationalities:', error);
    return [];
  }
};

// Use in dropdown
const NationalityDropdown = () => {
  const [nationalities, setNationalities] = useState([]);
  
  useEffect(() => {
    fetchNationalities().then(setNationalities);
  }, []);
  
  return (
    <select name="nationality" required>
      <option value="">Select Nationality</option>
      {nationalities.map(nat => (
        <option key={nat.id} value={nat.name}>
          {nat.name}
        </option>
      ))}
    </select>
  );
};
```

---

## Notes

1. **Public Endpoint**: No authentication is required to access this endpoint.

2. **Active Only**: Only active nationalities are returned. Inactive nationalities are excluded.

3. **Ordering**: Nationalities are ordered by `sort_order` first, then alphabetically by name.

4. **Data Storage**: When storing nationality in forms, you can use either:
   - The `id` (recommended for database foreign keys)
   - The `name` (for string storage, as currently used in trainees table)

5. **Migration Required**: Before using this API, run the database migrations:
   ```bash
   php artisan migrate
   ```
   This will create the `nationalities` table and seed it with common nationalities.

6. **Extensibility**: New nationalities can be added to the database by administrators through the admin panel or directly in the database.

---

## Database Structure

### Table: `nationalities`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Nationality name (unique) |
| code | string(3) | ISO country code (nullable) |
| sort_order | integer | Order for sorting |
| is_active | boolean | Whether the nationality is active |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Update timestamp |

---

## Support

For questions or issues related to this API, please contact the backend development team.

