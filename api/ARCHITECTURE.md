# API Architecture Overview

## Complete e_rentalHub Project Structure

```
e_rentalHub/
│
├── 📁 api/                    ← NEW API LAYER (Mobile-First)
│   ├── 🔧 config.php          ← Core functions & middleware
│   ├── 🔐 login.php           ← POST: User login
│   ├── 👤 register.php        ← POST: User registration
│   ├── 🏠 get_properties.php   ← GET: List properties (with filters & pagination)
│   ├── 📄 get_property_details.php ← GET: Single property details
│   ├── 🔍 search_properties.php    ← GET: Search properties
│   ├── ❤️  save_property.php   ← POST: Save to favorites
│   ├── 💔 unsave_property.php  ← POST: Remove from favorites
│   ├── 📅 create_booking.php   ← POST: Create booking
│   ├── 📋 get_my_bookings.php  ← GET: User's bookings (with filters)
│   ├── 📖 README.md           ← Full API documentation
│   ├── 🧪 TESTING_GUIDE.php   ← Testing examples & CURL commands
│   └── 📊 IMPLEMENTATION_SUMMARY.md ← Architecture summary
│
├── 📁 dashboards/             ← Web Dashboard (Unchanged)
│   ├── student_dashboard.php
│   └── sections/
│       ├── search_properties.php
│       ├── saved_properties.php
│       └── other sections...
│
├── 📁 houses/                 ← Property Management (Unchanged)
│   └── view.php
│
├── 📁 auth/                   ← Authentication (Unchanged)
│   ├── login.php
│   ├── register.php
│   └── logout.php
│
├── 📁 config/                 ← Configuration (Unchanged)
│   └── db.php
│
├── 📁 uploads/                ← User Uploads
│   └── property images...
│
├── 📁 logs/                   ← API Logs (Auto-created)
│   └── api.log
│
├── index.html                 ← Landing Page (Unchanged)
├── login.html                 ← Login Page (Unchanged)
├── register.html              ← Register Page (Unchanged)
└── index.php                  ← Home (Unchanged)
```

## API Endpoints Summary

### 🔐 Authentication (2 endpoints)
```
POST   /api/login.php           → User login
POST   /api/register.php        → User registration
```

### 🏠 Properties (3 endpoints)
```
GET    /api/get_properties.php           → List all properties
GET    /api/get_property_details.php     → Get single property
GET    /api/search_properties.php        → Search properties
```

### ❤️ Saved Properties (2 endpoints)
```
POST   /api/save_property.php            → Save property
POST   /api/unsave_property.php          → Unsave property
```

### 📅 Bookings (2 endpoints)
```
POST   /api/create_booking.php           → Create new booking
GET    /api/get_my_bookings.php          → Get user's bookings
```

**Total: 9 API Endpoints**

## Data Flow

### Without API (Current - Web Only)
```
Mobile Browser
     ↓
Dashboards/Sections (PHP)
     ↓
Database
```

### With API (New - Mobile + Web)
```
┌─ Mobile App
│   ↓
├─ Web Browser ─→ API Layer (config.php + endpoints)
│   ↓                ↓
└─ Third-party     Database
```

## Request/Response Cycle

### Example: Get Properties
```
Client Request:
  GET /api/get_properties.php?page=1&limit=10&city=Nairobi

API Processing:
  1. Load config.php (helper functions)
  2. Validate request method (GET)
  3. Extract parameters
  4. Build database query with filters
  5. Execute prepared statement
  6. Format results
  7. Log activity

Server Response:
  {
    "success": true,
    "message": "Properties retrieved",
    "data": {
      "total": 150,
      "page": 1,
      "limit": 10,
      "total_pages": 15,
      "properties": [...]
    }
  }
```

## Security Layers

✅ **Input Validation**
   - Required field checking
   - Email format validation
   - Date format validation

✅ **SQL Injection Prevention**
   - Prepared statements with parameter binding
   - Input sanitization

✅ **Authentication**
   - Password hashing (bcrypt)
   - Session-based authentication
   - Role validation (student/landlord/admin)

✅ **Authorization**
   - User can only view own bookings
   - Save/unsave only available to students
   - Booking creation validates student role

✅ **Error Handling**
   - Graceful error responses
   - Detailed logging (no sensitive data exposed)
   - HTTP status code standards

✅ **CORS Support**
   - Cross-Origin requests allowed
   - Headers set for mobile apps

## Usage Examples

### JavaScript Fetch
```javascript
// Get properties
const response = await fetch('/e_rentalHub/api/get_properties.php?limit=10');
const data = await response.json();
console.log(data.data.properties);

// Save property (requires authentication)
const saveResponse = await fetch('/e_rentalHub/api/save_property.php', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ property_id: 123 })
});
const saveData = await saveResponse.json();
```

### CURL Commands
```bash
# Get properties
curl "http://localhost/e_rentalHub/api/get_properties.php?limit=10"

# Login
curl -X POST http://localhost/e_rentalHub/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'

# Search
curl "http://localhost/e_rentalHub/api/search_properties.php?q=apartment"
```

## Features by Endpoint

### Login
- Email/password authentication
- Returns user data
- Sets session

### Register  
- Create new account
- Role selection (student/landlord)
- Email uniqueness validation
- Auto-create profile

### Get Properties
- Pagination (page, limit)
- Filters: type, city, min/max rent
- Sorting: date, price
- Image paths included

### Search Properties
- Full-text search (title, address, city)
- Pagination
- Returns matching results

### Get Property Details
- Full property information
- Landlord contact details
- Multiple images
- Save status check

### Save/Unsave Property
- Toggle favorites
- Student-only
- Prevents duplicates

### Create Booking
- Check-in/check-out dates
- Automatic rent calculation
- Status tracking (pending)
- Property availability check

### Get My Bookings
- Filter by status
- Pagination
- Property details included
- Booking history

## Performance Considerations

✅ **Pagination** - Limits data per request (default 20, max 100)
✅ **Prepared Statements** - Efficient database queries
✅ **Filtering** - Reduces result set size
✅ **Indexed Queries** - Database indexes on common fields
✅ **Logging** - Minimal performance impact

## Error Handling

| Status | Meaning | Example |
|--------|---------|---------|
| 200 | Success | Get request completed |
| 201 | Created | Booking created |
| 400 | Bad Request | Missing required field |
| 401 | Unauthorized | Not logged in |
| 404 | Not Found | Property doesn't exist |
| 405 | Method Not Allowed | Used GET on POST endpoint |
| 409 | Conflict | Email already registered |
| 500 | Server Error | Database connection failed |
| 503 | Service Unavailable | Feature not configured |

## Database Tables (Required)

```sql
-- Users & Profiles
users (id, username, email, password_hash, role, created_at)
students (id, user_id, full_name, email, avatar, created_at)
landlords (id, user_id, full_name, email, phone, created_at)

-- Properties
properties (id, title, city, address, rent, type, bedrooms, area, description, status, landlord_id, created_at)
property_images (id, property_id, image_path, uploaded_at)

-- Bookings & Favorites
saved_properties (id, property_id, student_id, created_at)
reservations (id, property_id, student_id, landlord_id, check_in_date, check_out_date, amount, status, notes, created_at)
```

## File Sizes

| File | Lines | Purpose |
|------|-------|---------|
| config.php | 150+ | Core functions |
| login.php | 80+ | Authentication |
| register.php | 120+ | Registration |
| get_properties.php | 100+ | List & filter |
| search_properties.php | 90+ | Search |
| create_booking.php | 130+ | Bookings |
| get_my_bookings.php | 110+ | Booking history |

## Next Steps

1. ✅ **API Created** - All endpoints ready
2. ⏳ **Testing** - Use TESTING_GUIDE.php
3. ⏳ **Mobile App** - Consume API endpoints
4. ⏳ **JWT Tokens** - Replace sessions
5. ⏳ **Rate Limiting** - Prevent abuse
6. ⏳ **Caching** - Improve performance

## Support Files

- `README.md` - Complete API documentation
- `TESTING_GUIDE.php` - Examples & testing
- `IMPLEMENTATION_SUMMARY.md` - Detailed overview
- `config.php` - Helper functions

---

**API Implementation Complete** ✅
**Ready for Mobile Development** 📱
