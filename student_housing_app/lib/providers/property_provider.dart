import 'package:flutter/material.dart';
import '../models/property.dart';
import '../services/api.dart';

/// Property Provider for managing property listings and filters
class PropertyProvider extends ChangeNotifier {
  final ApiService _apiService = ApiService();

  List<Property> _properties = [];
  List<Property> _filteredProperties = [];
  bool _isLoading = false;
  String? _error;
  int _currentPage = 1;
  final int _pageSize = 10;

  // Getters
  List<Property> get properties =>
      _filteredProperties.isNotEmpty ? _filteredProperties : _properties;
  bool get isLoading => _isLoading;
  String? get error => _error;

  /// Load properties from API
  Future<void> loadProperties({int page = 1}) async {
    _isLoading = true;
    _error = null;
    _currentPage = page;
    notifyListeners();

    try {
      final result = await _apiService.getProperties(
        page: page,
        limit: _pageSize,
      );

      if (result.containsKey('properties')) {
        final propertiesList = result['properties'] as List?;
        if (propertiesList != null) {
          _properties = List<Property>.from(
            propertiesList.map(
              (p) => Property.fromJson(p as Map<String, dynamic>),
            ),
          );
        }
      }

      _isLoading = false;
      _filteredProperties = [];
      notifyListeners();
    } catch (e) {
      _error = e
          .toString()
          .replaceAll('Exception: ', '')
          .replaceAll('ApiException: ', '');
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Search properties
  Future<void> searchProperties(String query) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final result = await _apiService.searchProperties(query);

      if (result.containsKey('properties')) {
        final propertiesList = result['properties'] as List?;
        if (propertiesList != null) {
          _properties = List<Property>.from(
            propertiesList.map(
              (p) => Property.fromJson(p as Map<String, dynamic>),
            ),
          );
        }
      }

      _isLoading = false;
      _filteredProperties = [];
      notifyListeners();
    } catch (e) {
      _error = e
          .toString()
          .replaceAll('Exception: ', '')
          .replaceAll('ApiException: ', '');
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Filter properties by type and city
  void filterProperties({String? type, String? city}) {
    _filteredProperties = _properties.where((property) {
      bool matchesType =
          type == null ||
          type.isEmpty ||
          property.type.toLowerCase() == type.toLowerCase();
      bool matchesCity =
          city == null ||
          city.isEmpty ||
          property.city.toLowerCase() == city.toLowerCase();
      return matchesType && matchesCity;
    }).toList();
    notifyListeners();
  }

  /// Save property to favorites
  Future<void> saveProperty(int propertyId) async {
    try {
      await _apiService.saveProperty(propertyId);

      // Update property saved status in local list
      for (var property in _properties) {
        if (property.id == propertyId) {
          // Create updated property with isSaved = true
          final updatedProperty = property.copyWith(isSaved: true);
          final index = _properties.indexOf(property);
          _properties[index] = updatedProperty;
          break;
        }
      }
      notifyListeners();
    } catch (e) {
      _error = e
          .toString()
          .replaceAll('Exception: ', '')
          .replaceAll('ApiException: ', '');
      notifyListeners();
    }
  }

  /// Unsave property from favorites
  Future<void> unsaveProperty(int propertyId) async {
    try {
      await _apiService.unsaveProperty(propertyId);

      // Update property saved status in local list
      for (var property in _properties) {
        if (property.id == propertyId) {
          // Create updated property with isSaved = false
          final updatedProperty = property.copyWith(isSaved: false);
          final index = _properties.indexOf(property);
          _properties[index] = updatedProperty;
          break;
        }
      }
      notifyListeners();
    } catch (e) {
      _error = e
          .toString()
          .replaceAll('Exception: ', '')
          .replaceAll('ApiException: ', '');
      notifyListeners();
    }
  }

  /// Clear filters
  void clearFilters() {
    _filteredProperties = [];
    notifyListeners();
  }
}
