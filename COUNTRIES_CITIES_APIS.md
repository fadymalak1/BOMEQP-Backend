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
    },
    {
      "code": "US",
      "name": "United States"
    },
    {
      "code": "GB",
      "name": "United Kingdom"
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
    },
    {
      "name": "Dubai",
      "country_code": "AE"
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
    },
    {
      "name": "Shubra El Kheima",
      "country_code": "EG"
    },
    {
      "name": "Port Said",
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
  } else {
    citySelect.innerHTML = '<option value="">Select City</option>';
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
  const [selectedCity, setSelectedCity] = useState('');

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
      setSelectedCity(''); // Reset city selection
    } else {
      setCities([]);
      setSelectedCity('');
    }
  }, [selectedCountry]);

  return (
    <div>
      <label>Country:</label>
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

      <label>City:</label>
      <select 
        value={selectedCity}
        onChange={(e) => setSelectedCity(e.target.value)}
        disabled={!selectedCountry}
      >
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
    <label>Country:</label>
    <select v-model="selectedCountry" @change="loadCities">
      <option value="">Select Country</option>
      <option v-for="country in countries" :key="country.code" :value="country.code">
        {{ country.name }}
      </option>
    </select>

    <label>City:</label>
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
        this.selectedCity = '';
      }
    }
  }
};
</script>
```

### Vanilla JavaScript Example
```html
<!DOCTYPE html>
<html>
<head>
  <title>Country & City Selector</title>
</head>
<body>
  <select id="countrySelect">
    <option value="">Select Country</option>
  </select>

  <select id="citySelect" disabled>
    <option value="">Select City</option>
  </select>

  <script>
    const countrySelect = document.getElementById('countrySelect');
    const citySelect = document.getElementById('citySelect');

    // Load countries on page load
    fetch('/api/countries')
      .then(response => response.json())
      .then(data => {
        data.countries.forEach(country => {
          const option = document.createElement('option');
          option.value = country.code;
          option.textContent = country.name;
          countrySelect.appendChild(option);
        });
      })
      .catch(error => console.error('Error:', error));

    // Load cities when country changes
    countrySelect.addEventListener('change', async (e) => {
      const countryCode = e.target.value;
      citySelect.innerHTML = '<option value="">Select City</option>';
      
      if (countryCode) {
        citySelect.disabled = false;
        try {
          const response = await fetch(`/api/cities?country=${countryCode}`);
          const data = await response.json();
          
          data.cities.forEach(city => {
            const option = document.createElement('option');
            option.value = city.name;
            option.textContent = city.name;
            citySelect.appendChild(option);
          });
        } catch (error) {
          console.error('Error:', error);
        }
      } else {
        citySelect.disabled = true;
      }
    });
  </script>
</body>
</html>
```

---

## Available Countries

The API currently includes the following countries (with more available in the full list):

- **Middle East & North Africa:** Egypt, Saudi Arabia, UAE, Kuwait, Qatar, Bahrain, Oman, Jordan, Lebanon, Morocco
- **Europe:** United Kingdom, France, Germany, Italy, Spain, Netherlands, Belgium, Greece, and more
- **Americas:** United States, Canada, Brazil, Argentina, Mexico, Colombia
- **Asia:** India, China, Japan, Singapore, Thailand, Malaysia, Indonesia, and more
- **Oceania:** Australia, New Zealand
- **Africa:** South Africa, Nigeria, and more

---

## Available Cities by Country

### Egypt (EG)
Cairo, Alexandria, Giza, Shubra El Kheima, Port Said, Suez, Luxor, Aswan, Mansoura, Tanta, Ismailia, Zagazig

### Saudi Arabia (SA)
Riyadh, Jeddah, Mecca, Medina, Dammam, Khobar, Abha, Tabuk

### United Arab Emirates (AE)
Dubai, Abu Dhabi, Sharjah, Al Ain, Ajman, Ras Al Khaimah, Fujairah

### Kuwait (KW)
Kuwait City, Al Ahmadi, Hawalli, Al Jahra

### Qatar (QA)
Doha, Al Rayyan, Al Wakrah, Al Khor

### Bahrain (BH)
Manama, Riffa, Muharraq, Hamad Town

### Oman (OM)
Muscat, Salalah, Sohar, Nizwa

### Jordan (JO)
Amman, Irbid, Zarqa, Aqaba

### Lebanon (LB)
Beirut, Tripoli, Sidon, Tyre

### United States (US)
New York, Los Angeles, Chicago, Houston, Phoenix, Philadelphia, San Antonio, San Diego, Dallas, San Jose

### United Kingdom (GB)
London, Manchester, Birmingham, Liverpool, Leeds, Glasgow, Edinburgh, Bristol

### Canada (CA)
Toronto, Vancouver, Montreal, Calgary, Ottawa, Edmonton

### France (FR)
Paris, Lyon, Marseille, Toulouse, Nice

### Germany (DE)
Berlin, Munich, Hamburg, Frankfurt, Cologne

### India (IN)
Mumbai, Delhi, Bangalore, Hyderabad, Chennai, Kolkata, Pune

### China (CN)
Beijing, Shanghai, Guangzhou, Shenzhen, Chengdu

### Australia (AU)
Sydney, Melbourne, Brisbane, Perth, Adelaide

*Note: The city list can be extended. Contact the backend team to add more cities.*

---

## Notes

1. **Country Codes**: All country codes follow the ISO 3166-1 alpha-2 standard (2-letter codes).
2. **City Filtering**: When a country code is provided, only cities for that country are returned.
3. **Case Insensitive**: Country code filtering is case-insensitive (e.g., "eg", "EG", "Eg" all work).
4. **Extensibility**: The city list can be easily extended by adding more cities to the backend controller.
5. **No Authentication Required**: These endpoints are public and can be accessed without authentication tokens.
6. **CORS**: If you're making requests from a different domain, ensure CORS is properly configured on the backend.

---

## Testing

**Example cURL Commands:**

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

# Get cities for United States
curl -X GET "http://your-domain.com/api/cities?country=US" \
  -H "Accept: application/json"
```

**Example Postman Collection:**

1. Create a new request: `GET {{base_url}}/api/countries`
2. Create a new request: `GET {{base_url}}/api/cities`
3. Create a new request: `GET {{base_url}}/api/cities?country=EG`

---

## Error Handling

Both endpoints return standard HTTP status codes:

- **200 OK**: Request successful
- **400 Bad Request**: Invalid query parameters
- **500 Internal Server Error**: Server error

**Example Error Response:**
```json
{
  "message": "Error message here"
}
```

---

## Integration Tips

1. **Caching**: Consider caching the countries list on the frontend since it rarely changes.
2. **Loading States**: Show loading indicators while fetching data.
3. **Error Handling**: Always handle errors gracefully and show user-friendly messages.
4. **Validation**: Validate country and city selections before form submission.
5. **Accessibility**: Ensure dropdowns are keyboard accessible and screen-reader friendly.

---

**Last Updated:** December 29, 2025

