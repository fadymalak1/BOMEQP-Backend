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

---

# Countries & Cities APIs Documentation

This document describes the API endpoints for retrieving countries and cities data for dropdown selections.

**Base URL:** `/api`

**Authentication:** These endpoints are public and do not require authentication.

---

## 1. Get Countries

**Endpoint:** `GET /countries`

**Description:** Get a list of all available countries with their ISO 3166-1 alpha-2 country codes and names.

**Authorization:** Public (no authentication required)

**Response (200):**
```json
{
  "countries": [
    {
      "code": "EG",
      "name": "Egypt"
    },
    {
      "code": "SA",
      "name": "Saudi Arabia"
    },
    {
      "code": "AE",
      "name": "United Arab Emirates"
    }
  ]
}
```

**Response Fields:**
- `countries` (array): List of countries
  - `code` (string): ISO 3166-1 alpha-2 country code (e.g., "EG", "US", "GB")
  - `name` (string): Full country name

**Example Usage:**
```javascript
// Fetch countries
const getCountries = async () => {
  const response = await axios.get('/api/countries');
  return response.data.countries;
};

// Use in dropdown
countries.forEach(country => {
  const option = document.createElement('option');
  option.value = country.code;
  option.textContent = country.name;
  countrySelect.appendChild(option);
});
```

---

## 2. Get Cities

**Endpoint:** `GET /cities`

**Description:** Get a list of cities. Optionally filter by country code to get cities for a specific country.

**Authorization:** Public (no authentication required)

**Query Parameters:**
- `country` (optional, string): Filter cities by country code (ISO 3166-1 alpha-2). Example: `?country=EG`

**Response (200) - All Cities:**
```json
{
  "cities": [
    {
      "name": "Cairo",
      "country_code": "EG"
    },
    {
      "name": "Alexandria",
      "country_code": "EG"
    },
    {
      "name": "Riyadh",
      "country_code": "SA"
    }
  ]
}
```

**Response (200) - Filtered by Country:**
```json
{
  "cities": [
    {
      "name": "Cairo",
      "country_code": "EG"
    },
    {
      "name": "Alexandria",
      "country_code": "EG"
    },
    {
      "name": "Giza",
      "country_code": "EG"
    }
  ]
}
```

**Response Fields:**
- `cities` (array): List of cities
  - `name` (string): City name
  - `country_code` (string): ISO 3166-1 alpha-2 country code

**Example Usage:**
```javascript
// Fetch all cities
const getAllCities = async () => {
  const response = await axios.get('/api/cities');
  return response.data.cities;
};

// Fetch cities for a specific country
const getCitiesByCountry = async (countryCode) => {
  const response = await axios.get(`/api/cities?country=${countryCode}`);
  return response.data.cities;
};

// Cascading dropdown example
countrySelect.addEventListener('change', async (e) => {
  const countryCode = e.target.value;
  if (countryCode) {
    const cities = await getCitiesByCountry(countryCode);
    citySelect.innerHTML = '<option value="">Select City</option>';
    cities.forEach(city => {
      const option = document.createElement('option');
      option.value = city.name;
      option.textContent = city.name;
      citySelect.appendChild(option);
    });
  }
});
```

---

## Frontend Implementation Examples

### React Example
```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function LocationSelector() {
  const [countries, setCountries] = useState([]);
  const [cities, setCities] = useState([]);
  const [selectedCountry, setSelectedCountry] = useState('');

  useEffect(() => {
    // Load countries on mount
    axios.get('/api/countries')
      .then(response => setCountries(response.data.countries))
      .catch(error => console.error('Error fetching countries:', error));
  }, []);

  useEffect(() => {
    // Load cities when country changes
    if (selectedCountry) {
      axios.get(`/api/cities?country=${selectedCountry}`)
        .then(response => setCities(response.data.cities))
        .catch(error => console.error('Error fetching cities:', error));
    } else {
      setCities([]);
    }
  }, [selectedCountry]);

  return (
    <div>
      <select 
        value={selectedCountry} 
        onChange={(e) => setSelectedCountry(e.target.value)}
      >
        <option value="">Select Country</option>
        {countries.map(country => (
          <option key={country.code} value={country.code}>
            {country.name}
          </option>
        ))}
      </select>

      <select disabled={!selectedCountry}>
        <option value="">Select City</option>
        {cities.map((city, index) => (
          <option key={index} value={city.name}>
            {city.name}
          </option>
        ))}
      </select>
    </div>
  );
}
```

### Vue.js Example
```vue
<template>
  <div>
    <select v-model="selectedCountry" @change="loadCities">
      <option value="">Select Country</option>
      <option v-for="country in countries" :key="country.code" :value="country.code">
        {{ country.name }}
      </option>
    </select>

    <select v-model="selectedCity" :disabled="!selectedCountry">
      <option value="">Select City</option>
      <option v-for="city in cities" :key="city.name" :value="city.name">
        {{ city.name }}
      </option>
    </select>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      countries: [],
      cities: [],
      selectedCountry: '',
      selectedCity: ''
    };
  },
  mounted() {
    this.loadCountries();
  },
  methods: {
    async loadCountries() {
      try {
        const response = await axios.get('/api/countries');
        this.countries = response.data.countries;
      } catch (error) {
        console.error('Error fetching countries:', error);
      }
    },
    async loadCities() {
      if (this.selectedCountry) {
        try {
          const response = await axios.get(`/api/cities?country=${this.selectedCountry}`);
          this.cities = response.data.cities;
          this.selectedCity = ''; // Reset city selection
        } catch (error) {
          console.error('Error fetching cities:', error);
        }
      } else {
        this.cities = [];
      }
    }
  }
};
</script>
```

---

## Notes

1. **Country Codes**: All country codes follow the ISO 3166-1 alpha-2 standard (2-letter codes).
2. **City Filtering**: When a country code is provided, only cities for that country are returned.
3. **Case Insensitive**: Country code filtering is case-insensitive.
4. **Extensibility**: The city list can be easily extended by adding more cities to the `CityController::getCities()` method.
5. **No Authentication Required**: These endpoints are public and can be accessed without authentication tokens.

---

## Testing

**Example cURL:**
```bash
# Get all countries
curl -X GET "http://your-domain.com/api/countries" \
  -H "Accept: application/json"

# Get all cities
curl -X GET "http://your-domain.com/api/cities" \
  -H "Accept: application/json"

# Get cities for Egypt
curl -X GET "http://your-domain.com/api/cities?country=EG" \
  -H "Accept: application/json"

# Get cities for Saudi Arabia
curl -X GET "http://your-domain.com/api/cities?country=SA" \
  -H "Accept: application/json"
```

---

**Last Updated:** December 29, 2025

