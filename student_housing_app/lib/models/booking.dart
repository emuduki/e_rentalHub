/// Booking model for reservation data
class Booking {
  final int bookingId;
  final int studentId;
  final int propertyId;
  final String propertyTitle;
  final double propertyPrice;
  final String city;
  final DateTime checkInDate;
  final DateTime checkOutDate;
  final int numberOfDays;
  final double totalAmount;
  final String status; // 'pending', 'confirmed', 'cancelled', 'completed'
  final String? notes;
  final DateTime createdAt;
  final DateTime? updatedAt;
  final Property? propertyDetails;

  Booking({
    required this.bookingId,
    required this.studentId,
    required this.propertyId,
    required this.propertyTitle,
    required this.propertyPrice,
    required this.city,
    required this.checkInDate,
    required this.checkOutDate,
    required this.numberOfDays,
    required this.totalAmount,
    required this.status,
    this.notes,
    required this.createdAt,
    this.updatedAt,
    this.propertyDetails,
  });

  /// Create Booking from JSON response
  factory Booking.fromJson(Map<String, dynamic> json) {
    final checkIn = json['check_in_date'] != null
        ? DateTime.tryParse(json['check_in_date'].toString())
        : DateTime.now();
    final checkOut = json['check_out_date'] != null
        ? DateTime.tryParse(json['check_out_date'].toString())
        : DateTime.now();

    final days = checkOut != null && checkIn != null
        ? checkOut.difference(checkIn).inDays
        : 0;

    return Booking(
      bookingId: json['booking_id'] is int
          ? json['booking_id']
          : int.tryParse(json['booking_id'].toString()) ?? 0,
      studentId: json['student_id'] is int
          ? json['student_id']
          : int.tryParse(json['student_id'].toString()) ?? 0,
      propertyId: json['property_id'] is int
          ? json['property_id']
          : int.tryParse(json['property_id'].toString()) ?? 0,
      propertyTitle:
          json['property_title']?.toString() ?? json['title']?.toString() ?? '',
      propertyPrice: json['property_price'] is num
          ? (json['property_price'] as num).toDouble()
          : double.tryParse(json['price'].toString()) ?? 0.0,
      city: json['city']?.toString() ?? '',
      checkInDate: checkIn ?? DateTime.now(),
      checkOutDate: checkOut ?? DateTime.now(),
      numberOfDays: days,
      totalAmount: json['total_amount'] is num
          ? (json['total_amount'] as num).toDouble()
          : json['amount'] is num
          ? (json['amount'] as num).toDouble()
          : 0.0,
      status: json['status']?.toString().toLowerCase() ?? 'pending',
      notes: json['notes']?.toString(),
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString()) ?? DateTime.now()
          : DateTime.now(),
      updatedAt: json['updated_at'] != null
          ? DateTime.tryParse(json['updated_at'].toString())
          : null,
      propertyDetails: json['property'] != null
          ? Property.fromJson(json['property'])
          : null,
    );
  }

  /// Convert Booking to JSON
  Map<String, dynamic> toJson() {
    return {
      'booking_id': bookingId,
      'student_id': studentId,
      'property_id': propertyId,
      'property_title': propertyTitle,
      'property_price': propertyPrice,
      'city': city,
      'check_in_date': checkInDate.toIso8601String(),
      'check_out_date': checkOutDate.toIso8601String(),
      'number_of_days': numberOfDays,
      'total_amount': totalAmount,
      'status': status,
      'notes': notes,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt?.toIso8601String(),
      'property': propertyDetails?.toJson(),
    };
  }

  /// Create a copy with modified fields
  Booking copyWith({
    int? bookingId,
    int? studentId,
    int? propertyId,
    String? propertyTitle,
    double? propertyPrice,
    String? city,
    DateTime? checkInDate,
    DateTime? checkOutDate,
    int? numberOfDays,
    double? totalAmount,
    String? status,
    String? notes,
    DateTime? createdAt,
    DateTime? updatedAt,
    Property? propertyDetails,
  }) {
    return Booking(
      bookingId: bookingId ?? this.bookingId,
      studentId: studentId ?? this.studentId,
      propertyId: propertyId ?? this.propertyId,
      propertyTitle: propertyTitle ?? this.propertyTitle,
      propertyPrice: propertyPrice ?? this.propertyPrice,
      city: city ?? this.city,
      checkInDate: checkInDate ?? this.checkInDate,
      checkOutDate: checkOutDate ?? this.checkOutDate,
      numberOfDays: numberOfDays ?? this.numberOfDays,
      totalAmount: totalAmount ?? this.totalAmount,
      status: status ?? this.status,
      notes: notes ?? this.notes,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      propertyDetails: propertyDetails ?? this.propertyDetails,
    );
  }

  /// Get status color for UI display
  String get statusLabel {
    switch (status) {
      case 'pending':
        return 'Pending';
      case 'confirmed':
        return 'Confirmed';
      case 'cancelled':
        return 'Cancelled';
      case 'completed':
        return 'Completed';
      default:
        return 'Unknown';
    }
  }

  @override
  String toString() =>
      'Booking(bookingId: $bookingId, propertyId: $propertyId, status: $status, totalAmount: $totalAmount)';
}

/// Property class reference for booking details
class Property {
  final int id;
  final String title;
  final String description;
  final double price;
  final String type;
  final int bedrooms;
  final int bathrooms;
  final double area;
  final String city;
  final String address;
  final List<String> images;

  Property({
    required this.id,
    required this.title,
    required this.description,
    required this.price,
    required this.type,
    required this.bedrooms,
    required this.bathrooms,
    required this.area,
    required this.city,
    required this.address,
    this.images = const [],
  });

  factory Property.fromJson(Map<String, dynamic> json) {
    return Property(
      id: json['id'] is int
          ? json['id']
          : int.tryParse(json['id'].toString()) ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      price: json['price'] is num ? (json['price'] as num).toDouble() : 0.0,
      type: json['type']?.toString() ?? '',
      bedrooms: json['bedrooms'] is int ? json['bedrooms'] : 0,
      bathrooms: json['bathrooms'] is int ? json['bathrooms'] : 0,
      area: json['area'] is num ? (json['area'] as num).toDouble() : 0.0,
      city: json['city']?.toString() ?? '',
      address: json['address']?.toString() ?? '',
      images: json['images'] is List
          ? List<String>.from(json['images'].map((x) => x.toString()))
          : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'description': description,
      'price': price,
      'type': type,
      'bedrooms': bedrooms,
      'bathrooms': bathrooms,
      'area': area,
      'city': city,
      'address': address,
      'images': images,
    };
  }
}
