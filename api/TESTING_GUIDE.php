<?php
/**
 * API Quick Reference & Testing Guide
 * 
 * This file provides quick testing URLs and examples for the API
 * 
 * CURL Examples:
 * 
 * 1. LOGIN
 * curl -X POST http://localhost/e_rentalHub/api/login.php \
 *   -H "Content-Type: application/json" \
 *   -d '{"email":"test@example.com","password":"password123"}'
 * 
 * 2. REGISTER
 * curl -X POST http://localhost/e_rentalHub/api/register.php \
 *   -H "Content-Type: application/json" \
 *   -d '{"username":"John Doe","email":"john@example.com","password":"password123","role":"student"}'
 * 
 * 3. GET PROPERTIES
 * curl "http://localhost/e_rentalHub/api/get_properties.php?page=1&limit=10"
 * 
 * 4. SEARCH PROPERTIES
 * curl "http://localhost/e_rentalHub/api/search_properties.php?q=apartment"
 * 
 * 5. GET PROPERTY DETAILS
 * curl "http://localhost/e_rentalHub/api/get_property_details.php?id=1"
 * 
 * 6. SAVE PROPERTY (requires auth)
 * curl -X POST http://localhost/e_rentalHub/api/save_property.php \
 *   -H "Content-Type: application/json" \
 *   -d '{"property_id":123}' \
 *   -c cookies.txt
 * 
 * 7. CREATE BOOKING (requires auth)
 * curl -X POST http://localhost/e_rentalHub/api/create_booking.php \
 *   -H "Content-Type: application/json" \
 *   -d '{"property_id":123,"check_in_date":"2024-06-01","check_out_date":"2024-12-31","notes":"My notes"}' \
 *   -b cookies.txt
 * 
 * 8. GET MY BOOKINGS (requires auth)
 * curl "http://localhost/e_rentalHub/api/get_my_bookings.php?status=pending" \
 *   -b cookies.txt
 * 
 * =======================
 * API ENDPOINT SUMMARY
 * =======================
 * 
 * Authentication:
 * - POST   /api/login.php
 * - POST   /api/register.php
 * 
 * Properties:
 * - GET    /api/get_properties.php
 * - GET    /api/get_property_details.php
 * - GET    /api/search_properties.php
 * 
 * Saved Properties:
 * - POST   /api/save_property.php (auth required)
 * - POST   /api/unsave_property.php (auth required)
 * 
 * Bookings:
 * - POST   /api/create_booking.php (auth required)
 * - GET    /api/get_my_bookings.php (auth required)
 * 
 * =======================
 * REQUEST/RESPONSE FORMATS
 * =======================
 * 
 * POST Request with JSON:
 * POST /api/endpoint.php HTTP/1.1
 * Host: localhost
 * Content-Type: application/json
 * Content-Length: length
 * 
 * {
 *   "field1": "value1",
 *   "field2": "value2"
 * }
 * 
 * Success Response (200, 201):
 * {
 *   "success": true,
 *   "message": "Operation successful",
 *   "data": { ... }
 * }
 * 
 * Error Response (400, 401, 404, 500, etc):
 * {
 *   "success": false,
 *   "message": "Error description",
 *   "data": null
 * }
 * 
 * =======================
 * AUTHENTICATION NOTES
 * =======================
 * 
 * - Authentication uses PHP sessions
 * - Session ID stored in cookies
 * - Send credentials: 'include' when fetching from JavaScript
 * - Future: JWT tokens for stateless authentication
 * 
 * JavaScript Fetch Example:
 * fetch('/e_rentalHub/api/endpoint.php', {
 *   method: 'POST',
 *   credentials: 'include',  // Important for session
 *   headers: { 'Content-Type': 'application/json' },
 *   body: JSON.stringify({ ... })
 * })
 * 
 * =======================
 * TESTING CHECKLIST
 * =======================
 * 
 * [ ] Register a new user (student)
 * [ ] Login with registered user
 * [ ] Get properties list
 * [ ] Search for a property
 * [ ] Get property details
 * [ ] Save a property
 * [ ] Unsave a property
 * [ ] Create a booking
 * [ ] Get my bookings
 * [ ] Test with invalid inputs
 * [ ] Test authentication requirements
 * [ ] Check error responses
 * 
 * =======================
 * DATABASE REQUIREMENTS
 * =======================
 * 
 * Required Tables:
 * - users (id, username, email, password_hash, role, created_at)
 * - students (id, user_id, full_name, email, avatar, created_at)
 * - landlords (id, user_id, full_name, email, phone, created_at)
 * - properties (id, title, city, address, rent, type, bedrooms, area, description, status, landlord_id, created_at)
 * - saved_properties (id, property_id, student_id, created_at)
 * - reservations (id, property_id, student_id, landlord_id, check_in_date, check_out_date, amount, status, notes, created_at)
 * - property_images (id, property_id, image_path, uploaded_at)
 * 
 * =======================
 * API LOGS
 * =======================
 * 
 * All API requests are logged to: /logs/api.log
 * Check this file for debugging and monitoring
 * 
 * Log Format:
 * [YYYY-MM-DD HH:MM:SS] METHOD ENDPOINT - SUCCESS/FAILURE - Message
 * 
 */

// If accessed directly, show this message
header('Content-Type: text/plain');
echo "API Testing and Reference Guide\n";
echo "================================\n\n";
echo "See the comments in this file for:\n";
echo "- CURL examples\n";
echo "- Endpoint summary\n";
echo "- Request/response formats\n";
echo "- Authentication notes\n";
echo "- Testing checklist\n";
echo "- Database requirements\n\n";
echo "For full documentation, see README.md\n";
?>
