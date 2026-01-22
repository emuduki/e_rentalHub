# e_rentalHub API Documentation

## Overview
RESTful API layer for the e_rentalHub mobile application. All endpoints return JSON responses.

## Base URL
```
http://localhost/e_rentalHub/api/
```

## Response Format
All API responses follow this format:

```json
{
  "success": true|false,
  "message": "Response message",
  "data": {
    // Response data object
  }
}
```

---

## Authentication Endpoints

### 1. Login
**Endpoint:** `POST /api/login.php`

**Request:**
```json
{
  "email": "student@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user_id": 1,
    "username": "John Doe",
    "email": "student@example.com",
    "role": "student",
    "token": "session_or_jwt_token"
  }
}
```

**Errors:**
- `400` - Missing required fields
- `400` - Invalid email format
- `401` - Invalid email or password

---

### 2. Register
**Endpoint:** `POST /api/register.php`

**Request:**
```json
{
  "username": "John Doe",
  "email": "student@example.com",
  "password": "password123",
  "role": "student"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user_id": 1,
    "username": "John Doe",
    "email": "student@example.com",
    "role": "student"
  }
}
```

**Errors:**
- `400` - Missing required fields
- `400` - Invalid email format
- `400` - Password too short (minimum 6 characters)
- `400` - Invalid role
- `409` - Email already registered

---

## Property Endpoints

### 3. Get Properties
**Endpoint:** `GET /api/get_properties.php`

**Query Parameters:**
- `page` (optional, default: 1) - Page number
- `limit` (optional, default: 20, max: 100) - Results per page
- `type` (optional) - Filter by property type (e.g., "Apartment", "House")
- `city` (optional) - Filter by city
- `min_rent` (optional) - Minimum monthly rent
- `max_rent` (optional) - Maximum monthly rent
- `sort` (optional) - Sort order: "newest" (default), "oldest", "price_asc", "price_desc"

**Example Request:**
```
GET /api/get_properties.php?page=1&limit=20&city=Nairobi&sort=price_asc
```

**Response (200):**
```json
{
  "success": true,
  "message": "Properties retrieved",
  "data": {
    "total": 150,
    "page": 1,
    "limit": 20,
    "total_pages": 8,
    "properties": [
      {
        "id": 1,
        "title": "Modern Apartment",
        "city": "Nairobi",
        "address": "123 Main Street",
        "rent": 15000,
        "type": "Apartment",
        "bedrooms": 2,
        "area": "1200",
        "description": "...",
        "status": "Available",
        "image_paths": ["/uploads/img1.jpg", "/uploads/img2.jpg"]
      }
    ]
  }
}
```

---

### 4. Get Property Details
**Endpoint:** `GET /api/get_property_details.php`

**Query Parameters:**
- `id` (required) - Property ID

**Example Request:**
```
GET /api/get_property_details.php?id=123
```

**Response (200):**
```json
{
  "success": true,
  "message": "Property details retrieved",
  "data": {
    "id": 1,
    "title": "Modern Apartment",
    "city": "Nairobi",
    "address": "123 Main Street",
    "rent": 15000,
    "type": "Apartment",
    "bedrooms": 2,
    "area": "1200",
    "description": "...",
    "status": "Available",
    "landlord_id": 5,
    "landlord": {
      "full_name": "Jane Landlord",
      "email": "landlord@example.com",
      "phone": "0712345678"
    },
    "image_paths": [],
    "is_saved": false,
    "created_at": "2024-01-15 10:30:00"
  }
}
```

**Errors:**
- `400` - Property ID is required
- `404` - Property not found

---

### 5. Search Properties
**Endpoint:** `GET /api/search_properties.php`

**Query Parameters:**
- `q` (required) - Search query (searches title, address, city)
- `page` (optional, default: 1)
- `limit` (optional, default: 20)

**Example Request:**
```
GET /api/search_properties.php?q=apartment&page=1&limit=10
```

**Response (200):**
```json
{
  "success": true,
  "message": "Search results",
  "data": {
    "query": "apartment",
    "total": 45,
    "page": 1,
    "limit": 10,
    "total_pages": 5,
    "properties": [...]
  }
}
```

**Errors:**
- `400` - Search query is required

---

## Saved Properties Endpoints

### 6. Save Property
**Endpoint:** `POST /api/save_property.php`

**Authentication:** Required (Student)

**Request:**
```json
{
  "property_id": 123
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Property saved",
  "data": {
    "property_id": 123,
    "saved": true
  }
}
```

**Errors:**
- `401` - Not authenticated
- `400` - Property ID is required
- `404` - Property not found
- `503` - Feature not available

---

### 7. Unsave Property
**Endpoint:** `POST /api/unsave_property.php`

**Authentication:** Required (Student)

**Request:**
```json
{
  "property_id": 123
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Property unsaved",
  "data": {
    "property_id": 123,
    "saved": false
  }
}
```

---

## Booking Endpoints

### 8. Create Booking
**Endpoint:** `POST /api/create_booking.php`

**Authentication:** Required (Student)

**Request:**
```json
{
  "property_id": 123,
  "check_in_date": "2024-06-01",
  "check_out_date": "2024-12-31",
  "notes": "Student group of 2"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Booking created",
  "data": {
    "booking_id": 456,
    "property_id": 123,
    "status": "pending",
    "amount": 90000,
    "check_in_date": "2024-06-01",
    "check_out_date": "2024-12-31"
  }
}
```

**Errors:**
- `401` - Not authenticated
- `400` - Missing required fields
- `400` - Invalid date format
- `400` - Invalid date values
- `400` - Check-out date must be after check-in date
- `404` - Property not found
- `503` - Feature not available

---

### 9. Get My Bookings
**Endpoint:** `GET /api/get_my_bookings.php`

**Authentication:** Required (Student)

**Query Parameters:**
- `status` (optional) - Filter by status: "pending", "confirmed", "completed", "cancelled"
- `page` (optional, default: 1)
- `limit` (optional, default: 20)

**Example Request:**
```
GET /api/get_my_bookings.php?status=pending&page=1
```

**Response (200):**
```json
{
  "success": true,
  "message": "Bookings retrieved",
  "data": {
    "total": 5,
    "page": 1,
    "limit": 20,
    "total_pages": 1,
    "bookings": [
      {
        "id": 456,
        "property": {
          "id": 123,
          "title": "Modern Apartment",
          "city": "Nairobi",
          "address": "123 Main St",
          "type": "Apartment"
        },
        "check_in_date": "2024-06-01",
        "check_out_date": "2024-12-31",
        "amount": 90000,
        "status": "pending",
        "notes": "Student group of 2",
        "created_at": "2024-05-20 14:30:00"
      }
    ]
  }
}
```

---

## Error Responses

### Common Status Codes
- `200` - Success
- `201` - Created (for POST requests)
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `405` - Method Not Allowed
- `409` - Conflict (e.g., email already exists)
- `500` - Internal Server Error
- `503` - Service Unavailable

### Error Response Format
```json
{
  "success": false,
  "message": "Error description",
  "data": null
}
```

---

## Usage Examples

### JavaScript/Fetch
```javascript
// Get properties
fetch('http://localhost/e_rentalHub/api/get_properties.php?page=1&limit=10')
  .then(res => res.json())
  .then(data => console.log(data));

// Search properties
fetch('http://localhost/e_rentalHub/api/search_properties.php?q=apartment')
  .then(res => res.json())
  .then(data => console.log(data));

// Save property (requires session/authentication)
fetch('http://localhost/e_rentalHub/api/save_property.php', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ property_id: 123 })
})
  .then(res => res.json())
  .then(data => console.log(data));

// Create booking
fetch('http://localhost/e_rentalHub/api/create_booking.php', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    property_id: 123,
    check_in_date: '2024-06-01',
    check_out_date: '2024-12-31',
    notes: 'Student group'
  })
})
  .then(res => res.json())
  .then(data => console.log(data));
```

---

## Notes
- All authentication-required endpoints check for active session
- Passwords are hashed using bcrypt
- Database errors are logged to `/logs/api.log`
- CORS headers are enabled for cross-origin requests
- Sensitive data (passwords, tokens) are never logged

---

## Future Enhancements
- JWT token authentication instead of sessions
- Rate limiting
- API key management
- Advanced filtering and pagination
- Booking update/cancel endpoints
- Payment integration
- Real estate metrics endpoints
