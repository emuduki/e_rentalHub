import 'package:flutter/material.dart';
import '../models/user.dart';
import '../services/api.dart';

/// Auth Provider for managing user authentication state
class AuthProvider extends ChangeNotifier {
  User? _user;
  bool _isLoading = false;
  String? _error;
  final ApiService _apiService = ApiService();

  // Getters
  User? get user => _user;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isAuthenticated => _user != null && _apiService.isAuthenticated;

  /// Initialize auth provider (check for existing session)
  Future<void> init() async {
    await _apiService.init();
    // Check if user was previously logged in
    if (_apiService.isAuthenticated && _apiService.userId != null) {
      _user = User(
        userId: int.tryParse(_apiService.userId ?? '0') ?? 0,
        username: 'User',
        email: 'user@example.com',
        role: _apiService.userRole ?? 'student',
      );
      notifyListeners();
    }
  }

  /// Login user with email and password
  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final result = await _apiService.login(email, password);

      if (result.isNotEmpty) {
        _user = User.fromJson(result);
        _isLoading = false;
        _error = null;
        notifyListeners();
        return true;
      } else {
        _error = 'Login failed. Please try again.';
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _error = e
          .toString()
          .replaceAll('Exception: ', '')
          .replaceAll('ApiException: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  /// Register a new user
  Future<bool> register(
    String username,
    String email,
    String password,
    String passwordConfirm,
  ) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final result = await _apiService.register(
        username,
        email,
        password,
        passwordConfirm,
      );

      if (result.isNotEmpty) {
        _user = User.fromJson(result);
        _isLoading = false;
        _error = null;
        notifyListeners();
        return true;
      } else {
        _error = 'Registration failed. Please try again.';
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _error = e
          .toString()
          .replaceAll('Exception: ', '')
          .replaceAll('ApiException: ', '');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  /// Logout user
  Future<void> logout() async {
    _isLoading = true;
    notifyListeners();

    try {
      await _apiService.logout();
      _user = null;
      _error = null;
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _error = 'Logout failed';
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Clear error message
  void clearError() {
    _error = null;
    notifyListeners();
  }
}
