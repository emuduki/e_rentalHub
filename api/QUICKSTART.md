# API Quick Start Guide

## 🚀 Getting Started in 5 Minutes

### Prerequisites
- XAMPP running (Apache + MySQL)
- e_rentalHub application installed
- Database tables set up (see ARCHITECTURE.md)

### Step 1: Access the API

API base URL:
```
http://localhost/e_rentalHub/api/
```

### Step 2: Test Authentication

**Register a new user:**
```bash
curl -X POST http://localhost/e_rentalHub/api/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "username": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "role": "student"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user_id": 1,
    "username": "Test User",
    "email": "test@example.com",
    "role": "student"
  }
}
```

### Step 3: Login

**Login with registered user:**
```bash
curl -X POST http://localhost/e_rentalHub/api/login.php \
  -c cookies.txt \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'
```

**Note:** Use `-c cookies.txt` to save session for authenticated requests

### Step 4: Explore Properties

**Get all properties:**
```bash
curl "http://localhost/e_rentalHub/api/get_properties.php?limit=5"
```

**Filter by city:**
```bash
curl "http://localhost/e_rentalHub/api/get_properties.php?city=Nairobi&limit=10"
```

**Search properties:**
```bash
curl "http://localhost/e_rentalHub/api/search_properties.php?q=apartment"
```

**Get property details:**
```bash
curl "http://localhost/e_rentalHub/api/get_property_details.php?id=1"
```

### Step 5: Save & Book

**Save a property:**
```bash
curl -X POST http://localhost/e_rentalHub/api/save_property.php \
  -b cookies.txt \
  -H "Content-Type: application/json" \
  -d '{"property_id": 1}'
```

**Create a booking:**
```bash
curl -X POST http://localhost/e_rentalHub/api/create_booking.php \
  -b cookies.txt \
  -H "Content-Type: application/json" \
  -d '{
    "property_id": 1,
    "check_in_date": "2024-06-01",
    "check_out_date": "2024-12-31",
    "notes": "Student group"
  }'
```

**Get your bookings:**
```bash
curl "http://localhost/e_rentalHub/api/get_my_bookings.php" -b cookies.txt
```

## 📱 JavaScript Usage

### Basic Fetch Example
```javascript
// Register
async function register() {
  const res = await fetch('/e_rentalHub/api/register.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      username: 'John Doe',
      email: 'john@example.com',
      password: 'password123',
      role: 'student'
    })
  });
  return await res.json();
}

// Login
async function login(email, password) {
  const res = await fetch('/e_rentalHub/api/login.php', {
    method: 'POST',
    credentials: 'include', // Include cookies
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  return await res.json();
}

// Get Properties
async function getProperties(page = 1) {
  const res = await fetch(`/e_rentalHub/api/get_properties.php?page=${page}&limit=10`);
  return await res.json();
}

// Search
async function search(query) {
  const res = await fetch(`/e_rentalHub/api/search_properties.php?q=${encodeURIComponent(query)}`);
  return await res.json();
}

// Save Property
async function saveProperty(propertyId) {
  const res = await fetch('/e_rentalHub/api/save_property.php', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ property_id: propertyId })
  });
  return await res.json();
}

// Create Booking
async function createBooking(propertyId, checkIn, checkOut) {
  const res = await fetch('/e_rentalHub/api/create_booking.php', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      property_id: propertyId,
      check_in_date: checkIn,
      check_out_date: checkOut
    })
  });
  return await res.json();
}

// Get My Bookings
async function getMyBookings() {
  const res = await fetch('/e_rentalHub/api/get_my_bookings.php', {
    credentials: 'include'
  });
  return await res.json();
}

// Usage Example
(async () => {
  // Register
  const reg = await register();
  console.log(reg);
  
  // Login
  const auth = await login('john@example.com', 'password123');
  console.log(auth);
  
  // Get properties
  const props = await getProperties(1);
  console.log(props.data.properties);
  
  // Search
  const search_res = await search('apartment');
  console.log(search_res.data.properties);
  
  // Save property
  const save = await saveProperty(1);
  console.log(save);
  
  // Create booking
  const booking = await createBooking(1, '2024-06-01', '2024-12-31');
  console.log(booking);
  
  // Get bookings
  const bookings = await getMyBookings();
  console.log(bookings.data.bookings);
})();
```

## 🔍 Common Queries

### Search by Price Range
```bash
curl "http://localhost/e_rentalHub/api/get_properties.php?min_rent=5000&max_rent=20000"
```

### Filter by Type
```bash
curl "http://localhost/e_rentalHub/api/get_properties.php?type=Apartment"
```

### Combined Filters
```bash
curl "http://localhost/e_rentalHub/api/get_properties.php?city=Nairobi&type=Apartment&min_rent=10000&sort=price_asc"
```

### Pagination
```bash
# Page 1
curl "http://localhost/e_rentalHub/api/get_properties.php?page=1&limit=20"

# Page 2
curl "http://localhost/e_rentalHub/api/get_properties.php?page=2&limit=20"
```

### Filter Bookings by Status
```bash
curl "http://localhost/e_rentalHub/api/get_my_bookings.php?status=pending" -b cookies.txt
curl "http://localhost/e_rentalHub/api/get_my_bookings.php?status=confirmed" -b cookies.txt
```

## ⚠️ Common Errors & Solutions

### 401 Unauthorized
**Problem:** Authentication required but not logged in

**Solution:**
```bash
# Login first to get session
curl -X POST http://localhost/e_rentalHub/api/login.php -c cookies.txt ...

# Then use session cookies in request
curl "http://localhost/e_rentalHub/api/save_property.php" -b cookies.txt -X POST ...
```

### 400 Bad Request
**Problem:** Missing or invalid fields

**Solution:** Check required fields in endpoint documentation
```bash
# Wrong (missing check_out_date)
curl -X POST http://localhost/e_rentalHub/api/create_booking.php \
  -H "Content-Type: application/json" \
  -d '{"property_id": 1, "check_in_date": "2024-06-01"}'

# Correct (all required fields)
curl -X POST http://localhost/e_rentalHub/api/create_booking.php \
  -H "Content-Type: application/json" \
  -d '{"property_id": 1, "check_in_date": "2024-06-01", "check_out_date": "2024-12-31"}'
```

### 404 Not Found
**Problem:** Resource doesn't exist

**Solution:** Check property ID or endpoint path
```bash
# Check if property exists
curl "http://localhost/e_rentalHub/api/get_property_details.php?id=9999"
```

### 500 Server Error
**Problem:** Database or server error

**Solution:** Check logs
```bash
# View API logs
cat c:\xampp\htdocs\e_rentalHub\logs\api.log
```

## 📊 API Response Examples

### Get Properties Response
```json
{
  "success": true,
  "message": "Properties retrieved",
  "data": {
    "total": 150,
    "page": 1,
    "limit": 10,
    "total_pages": 15,
    "properties": [
      {
        "id": 1,
        "title": "Modern 2BR Apartment",
        "city": "Nairobi",
        "address": "123 Main Street",
        "rent": 15000,
        "type": "Apartment",
        "bedrooms": 2,
        "area": "1200",
        "description": "...",
        "status": "Available",
        "image_paths": ["uploads/img1.jpg", "uploads/img2.jpg"]
      }
    ]
  }
}
```

### Create Booking Response
```json
{
  "success": true,
  "message": "Booking created",
  "data": {
    "booking_id": 123,
    "property_id": 1,
    "status": "pending",
    "amount": 90000,
    "check_in_date": "2024-06-01",
    "check_out_date": "2024-12-31"
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Invalid email or password",
  "data": null
}
```

## 📋 Checklist

- [ ] Register a user
- [ ] Login with that user
- [ ] Get list of properties
- [ ] Search for a property
- [ ] Get property details
- [ ] Save a property
- [ ] Create a booking
- [ ] Get your bookings
- [ ] Check logs at `/logs/api.log`

## 📚 Additional Resources

- `README.md` - Full API documentation
- `ARCHITECTURE.md` - System architecture
- `IMPLEMENTATION_SUMMARY.md` - Implementation details
- `TESTING_GUIDE.php` - More testing examples

## 🆘 Need Help?

1. Check the documentation files
2. Review error messages in `/logs/api.log`
3. Verify database tables exist
4. Test with curl before JavaScript
5. Check credentials and session

---

**API is ready to use!** 🎉
