/// Property model for rental listings
class Property {
  final int id;
  final String title;
  final String description;
  final double price; // Monthly rent price
  final String type; // 'apartment', 'house', 'bedsitter', etc.
  final int bedrooms;
  final int bathrooms;
  final double area; // Square meters
  final String city;
  final String address;
  final double? latitude;
  final double? longitude;
  final List<String> images;
  final Landlord? landlord;
  final bool isSaved;
  final List<Review>? reviews;
  final DateTime? createdAt;

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
    this.latitude,
    this.longitude,
    this.images = const [],
    this.landlord,
    this.isSaved = false,
    this.reviews,
    this.createdAt,
  });

  /// Create Property from JSON response
  factory Property.fromJson(Map<String, dynamic> json) {
    return Property(
      id: json['id'] is int
          ? json['id']
          : int.tryParse(json['id'].toString()) ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      price: json['price'] is num
          ? (json['price'] as num).toDouble()
          : (json['rent'] is num ? (json['rent'] as num).toDouble() : 0.0),
      type: json['type']?.toString() ?? '',
      bedrooms: json['bedrooms'] is int
          ? json['bedrooms']
          : int.tryParse(json['bedrooms'].toString()) ?? 0,
      bathrooms: json['bathrooms'] is int
          ? json['bathrooms']
          : int.tryParse(json['bathrooms'].toString()) ?? 0,
      area: json['area'] is num ? (json['area'] as num).toDouble() : 0.0,
      city: json['city']?.toString() ?? '',
      address: json['address']?.toString() ?? '',
      latitude: json['latitude'] is num
          ? (json['latitude'] as num).toDouble()
          : null,
      longitude: json['longitude'] is num
          ? (json['longitude'] as num).toDouble()
          : null,
      images: json['images'] is List
          ? List<String>.from(json['images'].map((x) => x.toString()))
          : json['image_paths'] is List
          ? List<String>.from(
              json['image_paths'].map((x) {
                final path = x.toString();
                // If path doesn't start with http, prepend the uploads directory
                if (!path.startsWith('http')) {
                  return 'http://192.168.0.108/e_rentalHub/uploads/$path';
                }
                return path;
              }),
            )
          : (json['image_url'] != null ? [json['image_url'].toString()] : []),
      landlord: json['landlord'] != null
          ? Landlord.fromJson(json['landlord'])
          : null,
      isSaved: json['is_saved'] == true || json['is_saved'] == 1,
      reviews: json['reviews'] is List
          ? List<Review>.from(json['reviews'].map((x) => Review.fromJson(x)))
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
    );
  }

  /// Convert Property to JSON
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
      'latitude': latitude,
      'longitude': longitude,
      'images': images,
      'landlord': landlord?.toJson(),
      'is_saved': isSaved,
      'reviews': reviews?.map((r) => r.toJson()).toList(),
      'created_at': createdAt?.toIso8601String(),
    };
  }

  /// Create a copy with modified fields
  Property copyWith({
    int? id,
    String? title,
    String? description,
    double? price,
    String? type,
    int? bedrooms,
    int? bathrooms,
    double? area,
    String? city,
    String? address,
    double? latitude,
    double? longitude,
    List<String>? images,
    Landlord? landlord,
    bool? isSaved,
    List<Review>? reviews,
    DateTime? createdAt,
  }) {
    return Property(
      id: id ?? this.id,
      title: title ?? this.title,
      description: description ?? this.description,
      price: price ?? this.price,
      type: type ?? this.type,
      bedrooms: bedrooms ?? this.bedrooms,
      bathrooms: bathrooms ?? this.bathrooms,
      area: area ?? this.area,
      city: city ?? this.city,
      address: address ?? this.address,
      latitude: latitude ?? this.latitude,
      longitude: longitude ?? this.longitude,
      images: images ?? this.images,
      landlord: landlord ?? this.landlord,
      isSaved: isSaved ?? this.isSaved,
      reviews: reviews ?? this.reviews,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  @override
  String toString() =>
      'Property(id: $id, title: $title, price: $price, city: $city)';
}

/// Landlord model for property owner information
class Landlord {
  final int userId;
  final String username;
  final String email;
  final String? phoneNumber;
  final String? profileImage;
  final double? rating;
  final int? reviewCount;

  Landlord({
    required this.userId,
    required this.username,
    required this.email,
    this.phoneNumber,
    this.profileImage,
    this.rating,
    this.reviewCount,
  });

  /// Create Landlord from JSON response
  factory Landlord.fromJson(Map<String, dynamic> json) {
    return Landlord(
      userId: json['user_id'] is int
          ? json['user_id']
          : int.tryParse(json['user_id'].toString()) ?? 0,
      username: json['username']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      phoneNumber: json['phone_number']?.toString(),
      profileImage: json['profile_image']?.toString(),
      rating: json['rating'] is num ? (json['rating'] as num).toDouble() : null,
      reviewCount: json['review_count'] is int ? json['review_count'] : null,
    );
  }

  /// Convert Landlord to JSON
  Map<String, dynamic> toJson() {
    return {
      'user_id': userId,
      'username': username,
      'email': email,
      'phone_number': phoneNumber,
      'profile_image': profileImage,
      'rating': rating,
      'review_count': reviewCount,
    };
  }

  @override
  String toString() =>
      'Landlord(userId: $userId, username: $username, email: $email)';
}

/// Review model for property reviews
class Review {
  final int id;
  final int propertyId;
  final int userId;
  final String userName;
  final double rating;
  final String comment;
  final DateTime createdAt;

  Review({
    required this.id,
    required this.propertyId,
    required this.userId,
    required this.userName,
    required this.rating,
    required this.comment,
    required this.createdAt,
  });

  /// Create Review from JSON response
  factory Review.fromJson(Map<String, dynamic> json) {
    return Review(
      id: json['id'] is int
          ? json['id']
          : int.tryParse(json['id'].toString()) ?? 0,
      propertyId: json['property_id'] is int
          ? json['property_id']
          : int.tryParse(json['property_id'].toString()) ?? 0,
      userId: json['user_id'] is int
          ? json['user_id']
          : int.tryParse(json['user_id'].toString()) ?? 0,
      userName: json['user_name']?.toString() ?? '',
      rating: json['rating'] is num ? (json['rating'] as num).toDouble() : 0.0,
      comment: json['comment']?.toString() ?? '',
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString()) ?? DateTime.now()
          : DateTime.now(),
    );
  }

  /// Convert Review to JSON
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'property_id': propertyId,
      'user_id': userId,
      'user_name': userName,
      'rating': rating,
      'comment': comment,
      'created_at': createdAt.toIso8601String(),
    };
  }

  @override
  String toString() =>
      'Review(id: $id, propertyId: $propertyId, rating: $rating)';
}
