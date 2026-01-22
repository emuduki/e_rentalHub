# API Quick Reference Card

## 🎯 Base URL
```
http://localhost/e_rentalHub/api/
```

## 🔐 Authentication Endpoints

### Login
```
POST /login.php
Content-Type: application/json

{
  "email": "test@example.com",
  "password": "password123"
}

Response: { user_id, username, email, role, token }
```

### Register
```
POST /register.php
Content-Type: application/json

{
  "username": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "role": "student"
}

Response: { user_id, username, email, role }
```

## 🏠 Property Endpoints

### List Properties
```
GET /get_properties.php?page=1&limit=10&city=Nairobi&sort=newest

Query Parameters:
- page (default: 1)
- limit (default: 20, max: 100)
- type (optional: "Apartment", "House", etc.)
- city (optional)
- min_rent (optional)
- max_rent (optional)
- sort (newest, oldest, price_asc, price_desc)

Response: { total, page, limit, total_pages, properties[] }
```

### Get Property Details
```
GET /get_property_details.php?id=123

Query Parameters:
- id (required)

Response: { 
  id, title, city, address, rent, type, bedrooms, area,
  description, status, landlord_id, landlord, image_paths, 
  is_saved, created_at
}
```

### Search Properties
```
GET /search_properties.php?q=apartment&page=1&limit=10

Query Parameters:
- q (required: search query)
- page (default: 1)
- limit (default: 20, max: 100)

Response: { query, total, page, limit, total_pages, properties[] }
```

## ❤️ Saved Properties Endpoints

### Save Property
```
POST /save_property.php
Content-Type: application/json
Credentials: include

{
  "property_id": 123
}

Response: { property_id, saved: true }
Requires: Student Authentication
```

### Unsave Property
```
POST /unsave_property.php
Content-Type: application/json
Credentials: include

{
  "property_id": 123
}

Response: { property_id, saved: false }
Requires: Student Authentication
```

## 📅 Booking Endpoints

### Create Booking
```
POST /create_booking.php
Content-Type: application/json
Credentials: include

{
  "property_id": 123,
  "check_in_date": "2024-06-01",
  "check_out_date": "2024-12-31",
  "notes": "Optional notes"
}

Response: { booking_id, property_id, status, amount, check_in_date, check_out_date }
Requires: Student Authentication
```

### Get My Bookings
```
GET /get_my_bookings.php?status=pending&page=1&limit=20
Credentials: include

Query Parameters:
- status (optional: pending, confirmed, completed, cancelled)
- page (default: 1)
- limit (default: 20, max: 100)

Response: { total, page, limit, total_pages, bookings[] }
Requires: Student Authentication
```

## 📊 Response Format

### Success Response (200, 201)
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { /* response data */ }
}
```

### Error Response (400, 401, 404, 500)
```json
{
  "success": false,
  "message": "Error description",
  "data": null
}
```

## 🔗 JavaScript Fetch Template

### GET Request
```javascript
const response = await fetch('/e_rentalHub/api/endpoint.php?param=value');
const data = await response.json();
```

### POST Request (No Auth)
```javascript
const response = await fetch('/e_rentalHub/api/endpoint.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ field: 'value' })
});
const data = await response.json();
```

### POST Request (With Auth)
```javascript
const response = await fetch('/e_rentalHub/api/endpoint.php', {
  method: 'POST',
  credentials: 'include',  // Include session cookies
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ field: 'value' })
});
const data = await response.json();
```

## 📋 Common Queries

### Get Top 10 Latest Properties
```
GET /get_properties.php?limit=10&sort=newest
```

### Get Apartments in Nairobi Under 20,000
```
GET /get_properties.php?type=Apartment&city=Nairobi&max_rent=20000
```

### Search for Student Housing
```
GET /search_properties.php?q=student%20housing
```

### Get Pending Bookings
```
GET /get_my_bookings.php?status=pending
```

### Get Second Page of Properties
```
GET /get_properties.php?page=2&limit=20
```

## ⚠️ Common Errors

| Code | Error | Solution |
|------|-------|----------|
| 400 | Bad Request | Check required fields |
| 401 | Unauthorized | Login first, use credentials: 'include' |
| 404 | Not Found | Check ID or endpoint path |
| 405 | Method Not Allowed | Use correct HTTP method (GET/POST) |
| 409 | Conflict | Email already registered |
| 500 | Server Error | Check `/logs/api.log` |
| 503 | Service Unavailable | Feature not configured |

## 🧪 Testing with CURL

### Register
```bash
curl -X POST http://localhost/e_rentalHub/api/register.php \
  -H "Content-Type: application/json" \
  -d '{"username":"Test","email":"test@example.com","password":"pass123","role":"student"}'
```

### Login (Save Cookies)
```bash
curl -X POST http://localhost/e_rentalHub/api/login.php \
  -c cookies.txt \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"pass123"}'
```

### Use Saved Cookies
```bash
curl -b cookies.txt http://localhost/e_rentalHub/api/get_my_bookings.php
```

### Get Properties
```bash
curl "http://localhost/e_rentalHub/api/get_properties.php?limit=5"
```

### Search
```bash
curl "http://localhost/e_rentalHub/api/search_properties.php?q=apartment"
```

### Save Property (With Auth)
```bash
curl -X POST http://localhost/e_rentalHub/api/save_property.php \
  -b cookies.txt \
  -H "Content-Type: application/json" \
  -d '{"property_id":1}'
```

## 📱 Mobile App Integration

```javascript
// Configure base URL
const API_BASE = 'http://localhost/e_rentalHub/api';

// Create helper function
async function apiCall(endpoint, method = 'GET', data = null, auth = false) {
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' }
  };
  
  if (auth) options.credentials = 'include';
  if (data) options.body = JSON.stringify(data);
  
  const res = await fetch(`${API_BASE}/${endpoint}`, options);
  return await res.json();
}

// Usage
const properties = await apiCall('get_properties.php?limit=10');
const login = await apiCall('login.php', 'POST', { email, password });
const bookings = await apiCall('get_my_bookings.php', 'GET', null, true);
```

## 🔐 Security Notes

- Always use `credentials: 'include'` for authenticated requests
- Never expose sensitive data in logs
- Validate all user input on client side
- Check response.success before using data
- Handle errors gracefully
- Store tokens securely (if using JWT)

## 📖 Full Documentation

- **QUICKSTART.md** - Quick start guide
- **README.md** - Complete reference
- **ARCHITECTURE.md** - System design
- **IMPLEMENTATION_SUMMARY.md** - Details
- **TESTING_GUIDE.php** - More examples
- **index.html** - Documentation portal

---

**API Version:** 1.0  
**Status:** Production Ready  
**Last Updated:** December 4, 2025
