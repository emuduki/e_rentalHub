import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

/// Main API Service for e_rentalHub Flutter App
/// Handles all HTTP requests to the backend API
/// Base URL: http://192.168.0.108/e_rentalHub/api/
class ApiService {
  // Base URL - Update this to your local network IP
  static const String baseUrl = 'http://192.168.0.108/e_rentalHub/api';

  // Endpoint paths
  static const String loginEndpoint = '/login.php';
  static const String registerEndpoint = '/register.php';
  static const String getPropertiesEndpoint = '/get_properties.php';
  static const String getPropertyDetailsEndpoint = '/get_property_details.php';
  static const String searchPropertiesEndpoint = '/search_properties.php';
  static const String savePropertyEndpoint = '/save_property.php';
  static const String unsavePropertyEndpoint = '/unsave_property.php';
  static const String createBookingEndpoint = '/create_booking.php';
  static const String getMyBookingsEndpoint = '/get_my_bookings.php';

  late SharedPreferences _prefs;
  String? _authToken;
  String? _userId;
  String? _userRole;

  // Singleton pattern
  static final ApiService _instance = ApiService._internal();

  factory ApiService() {
    return _instance;
  }

  ApiService._internal();

  /// Initialize the API service
  /// Call this once in your app's main() function
  Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
    _authToken = _prefs.getString('auth_token');
    _userId = _prefs.getString('user_id');
    _userRole = _prefs.getString('user_role');
  }

  /// Get common headers for all requests
  Map<String, String> _getHeaders({bool includeAuth = false}) {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    if (includeAuth && _authToken != null) {
      headers['Authorization'] = 'Bearer $_authToken';
    }

    return headers;
  }

  /// Parse API response
  Map<String, dynamic> _parseResponse(http.Response response) {
    try {
      final decoded = json.decode(response.body) as Map<String, dynamic>;
      return decoded;
    } catch (e) {
      throw Exception('Failed to parse response: $e');
    }
  }

  /// Handle API errors
  void _handleError(http.Response response) {
    final data = _parseResponse(response);
    final message = data['message'] ?? 'An error occurred';

    switch (response.statusCode) {
      case 400:
        throw ApiException('Bad Request: $message');
      case 401:
        throw ApiException('Unauthorized: $message');
      case 404:
        throw ApiException('Not Found: $message');
      case 500:
        throw ApiException('Server Error: $message');
      default:
        throw ApiException(message);
    }
  }

  // ==================== AUTHENTICATION ====================

  /// User Login
  /// POST /api/login.php
  /// Returns: {user_id, username, email, role, auth_token}
  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl$loginEndpoint'),
            headers: _getHeaders(),
            body: json.encode({'email': email, 'password': password}),
          )
          .timeout(const Duration(seconds: 30));

      if (response.statusCode != 200) {
        _handleError(response);
      }

      final data = _parseResponse(response);

      if (data['success'] == true) {
        // Store auth data locally
        _authToken = data['data']?['auth_token'] ?? '';
        _userId = data['data']?['user_id']?.toString();
        _userRole = data['data']?['role'];

        await _prefs.setString('auth_token', _authToken ?? '');
        await _prefs.setString('user_id', _userId ?? '');
        await _prefs.setString('user_role', _userRole ?? '');

        return data['data'] ?? {};
      } else {
        throw ApiException(data['message'] ?? 'Login failed');
      }
    } catch (e) {
      rethrow;
    }
  }

  /// User Registration
  /// POST /api/register.php
  /// Returns: {user_id, username, email, role}
  Future<Map<String, dynamic>> register(
    String username,
    String email,
    String password,
    String passwordConfirm,
  ) async {
    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl$registerEndpoint'),
            headers: _getHeaders(),
            body: json.encode({
              'username': username,
              'email': email,
              'password': password,
              'password_confirm': passwordConfirm,
            }),
          )
          .timeout(const Duration(seconds: 30));

      if (response.statusCode != 200 && response.statusCode != 201) {
        _handleError(response);
      }

      final data = _parseResponse(response);

      if (data['success'] == true) {
        return data['data'] ?? {};
      } else {
        throw ApiException(data['message'] ?? 'Registration failed');
      }
    } catch (e) {
      rethrow;
    }
  }

  /// Logout (clear local auth data)
  Future<void> logout() async {
    _authToken = null;
    _userId = null;
    _userRole = null;
    await _prefs.remove('auth_token');
    await _prefs.remove('user_id');
    await _prefs.remove('user_role');
  }

  /// Check if user is authenticated
  bool get isAuthenticated => _authToken != null && _authToken!.isNotEmpty;

  /// Get current user ID
  String? get userId => _userId;

  /// Get current user role
  String? get userRole => _userRole;

  // ==================== PROPERTIES ====================

  /// Get Properties List with Pagination and Filters
  /// GET /api/get_properties.php?page=1&limit=10&type=apartment&city=nairobi&min_rent=5000&max_rent=50000
  /// Returns: {properties: [], total_count, page, pages}
  Future<Map<String, dynamic>> getProperties({
    int page = 1,
    int limit = 10,
    String? type,
    String? city,
    double? minRent,
    double? maxRent,
  }) async {
    try {
      final queryParams = {'page': page.toString(), 'limit': limit.toString()};

      if (type != null && type.isNotEmpty) queryParams['type'] = type;
      if (city != null && city.isNotEmpty) queryParams['city'] = city;
      if (minRent != null) queryParams['min_rent'] = minRent.toString();
      if (maxRent != null) queryParams['max_rent'] = maxRent.toString();

      final uri = Uri.parse(
        '$baseUrl$getPropertiesEndpoint',
      ).replace(queryParameters: queryParams);

      final response = await http
          .get(uri, headers: _getHeaders(includeAuth: isAuthenticated))
          .timeout(const Duration(seconds: 30));

      if (response.statusCode != 200) {
        _handleError(response);
      }

      final data = _parseResponse(response);

      if (data['success'] == true) {
        return data['data'] ?? {};
      } else {
        throw ApiException(data['message'] ?? 'Failed to fetch properties');
      }
    } catch (e) {
      rethrow;
    }
  }

  /// Get Property Details by ID
  /// GET /api/get_property_details.php?id=123
  /// Returns: {id, title, description, price, type, bedrooms, bathrooms, area, city, address, images: [], landlord: {}}
  Future<Map<String, dynamic>> getPropertyDetails(int propertyId) async {
    try {
      final uri = Uri.parse(
        '$baseUrl$getPropertyDetailsEndpoint',
      ).replace(queryParameters: {'id': propertyId.toString()});

      final response = await http
          .get(uri, headers: _getHeaders(includeAuth: isAuthenticated))
          .timeout(const Duration(seconds: 30));

      if (response.statusCode != 200) {
        _handleError(response);
      }

      final data = _parseResponse(response);

      if (data['success'] == true) {
        return data['data'] ?? {};
      } else {
        throw ApiException(
          data['message'] ?? 'Failed to fetch property details',
        );
      }
    } catch (e) {
      rethrow;
    }
  }

  /// Search Properties
  /// GET /api/search_properties.php?q=apartment&page=1&limit=10
  /// Returns: {properties: [], total_count, page, pages}
  Future<Map<String, dynamic>> searchProperties(
    String query, {
    int page = 1,
    int limit = 10,
  }) async {
    try {
      final queryParams = {
        'q': query,
        'page': page.toString(),
        'limit': limit.toString(),
      };

      final uri = Uri.parse(
        '$baseUrl$searchPropertiesEndpoint',
      ).replace(queryParameters: queryParams);

      final response = await http
          .get(uri, headers: _getHeaders(includeAuth: isAuthenticated))
          .timeout(const Duration(seconds: 30));

      if (response.statusCode != 200) {
        _handleError(response);
      }

      final data = _parseResponse(response);

      if (data['success'] == true) {
        return data['data'] ?? {};
      } else {
        throw ApiException(data['message'] ?? 'Search failed');
      }
    } catch (e) {
      rethrow;
    }
  }

  // ==================== SAVED PROPERTIES ====================

  /// Save Property to Favorites
  /// POST /api/save_property.php
  /// Body: {property_id: 123}
  /// Returns: {success: true}
  Future<bool> saveProperty(int propertyId) async {
    if (!isAuthenticated) {
      throw ApiException('User must be logged in to save properties');
    }

    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl$savePropertyEndpoint'),
            headers: _getHeaders(includeAuth: true),
            body: json.encode({'property_id': propertyId}),
          )
          .timeout(const Duration(seconds: 30));

      if (response.statusCode != 200) {
        _handleError(response);
      }

      final data = _parseResponse(response);
      return data['success'] == true;
    } catch (e) {
      rethrow;
    }
  }

  /// Unsave Property from Favorites
  /// POST /api/unsave_property.php
  /// Body: {property_id: 123}
  /// Returns: {success: true}
  Future<bool> unsaveProperty(int propertyId) async {
    if (!isAuthenticated) {
      throw ApiException('User must be logged in to unsave properties');
    }

    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl$unsavePropertyEndpoint'),
            headers: _getHeaders(includeAuth: true),
            body: json.encode({'property_id': propertyId}),
          )
          .timeout(const Duration(seconds: 30));

      if (response.statusCode != 200) {
        _handleError(response);
      }

      final data = _parseResponse(response);
      return data['success'] == true;
    } catch (e) {
      rethrow;
    }
  }

  // ==================== BOOKINGS ====================

  /// Create Booking
  /// POST /api/create_booking.php
  /// Body: {property_id, check_in_date, check_out_date, notes}
  /// Returns: {booking_id, status, amount, created_at}
  Future<Map<String, dynamic>> createBooking(
    int propertyId,
    String checkInDate,
    String checkOutDate, {
    String? notes,
  }) async {
    if (!isAuthenticated) {
      throw ApiException('User must be logged in to create bookings');
    }

    try {
      final body = {
        'property_id': propertyId,
        'check_in_date': checkInDate,
        'check_out_date': checkOutDate,
      };

      if (notes != null && notes.isNotEmpty) {
        body['notes'] = notes;
      }

      final response = await http
          .post(
            Uri.parse('$baseUrl$createBookingEndpoint'),
            headers: _getHeaders(includeAuth: true),
            body: json.encode(body),
          )
          .timeout(const Duration(seconds: 30));

      if (response.statusCode != 200 && response.statusCode != 201) {
        _handleError(response);
      }

      final data = _parseResponse(response);

      if (data['success'] == true) {
        return data['data'] ?? {};
      } else {
        throw ApiException(data['message'] ?? 'Failed to create booking');
      }
    } catch (e) {
      rethrow;
    }
  }

  /// Get User's Bookings
  /// GET /api/get_my_bookings.php?status=confirmed&page=1&limit=10
  /// Returns: {bookings: [], total_count, page, pages}
  Future<Map<String, dynamic>> getMyBookings({
    String? status,
    int page = 1,
    int limit = 10,
  }) async {
    if (!isAuthenticated) {
      throw ApiException('User must be logged in to view bookings');
    }

    try {
      final queryParams = {'page': page.toString(), 'limit': limit.toString()};

      if (status != null && status.isNotEmpty) {
        queryParams['status'] = status;
      }

      final uri = Uri.parse(
        '$baseUrl$getMyBookingsEndpoint',
      ).replace(queryParameters: queryParams);

      final response = await http
          .get(uri, headers: _getHeaders(includeAuth: true))
          .timeout(const Duration(seconds: 30));

      if (response.statusCode != 200) {
        _handleError(response);
      }

      final data = _parseResponse(response);

      if (data['success'] == true) {
        return data['data'] ?? {};
      } else {
        throw ApiException(data['message'] ?? 'Failed to fetch bookings');
      }
    } catch (e) {
      rethrow;
    }
  }
}

/// Custom API Exception
class ApiException implements Exception {
  final String message;

  ApiException(this.message);

  @override
  String toString() => 'ApiException: $message';
}
