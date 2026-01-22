/// User model for authentication and profile data
class User {
  final int userId;
  final String username;
  final String email;
  final String role; // 'student' or 'landlord'
  final String? firstName;
  final String? lastName;
  final String? phoneNumber;
  final String? profileImage;
  final String? bio;
  final DateTime? createdAt;
  final String? authToken;

  User({
    required this.userId,
    required this.username,
    required this.email,
    required this.role,
    this.firstName,
    this.lastName,
    this.phoneNumber,
    this.profileImage,
    this.bio,
    this.createdAt,
    this.authToken,
  });

  /// Create User from JSON response
  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      userId: json['user_id'] is int
          ? json['user_id']
          : int.tryParse(json['user_id'].toString()) ?? 0,
      username: json['username']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      role: json['role']?.toString() ?? 'student',
      firstName: json['first_name']?.toString(),
      lastName: json['last_name']?.toString(),
      phoneNumber: json['phone_number']?.toString(),
      profileImage: json['profile_image']?.toString(),
      bio: json['bio']?.toString(),
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      authToken: json['auth_token']?.toString(),
    );
  }

  /// Convert User to JSON
  Map<String, dynamic> toJson() {
    return {
      'user_id': userId,
      'username': username,
      'email': email,
      'role': role,
      'first_name': firstName,
      'last_name': lastName,
      'phone_number': phoneNumber,
      'profile_image': profileImage,
      'bio': bio,
      'created_at': createdAt?.toIso8601String(),
      'auth_token': authToken,
    };
  }

  /// Create a copy with modified fields
  User copyWith({
    int? userId,
    String? username,
    String? email,
    String? role,
    String? firstName,
    String? lastName,
    String? phoneNumber,
    String? profileImage,
    String? bio,
    DateTime? createdAt,
    String? authToken,
  }) {
    return User(
      userId: userId ?? this.userId,
      username: username ?? this.username,
      email: email ?? this.email,
      role: role ?? this.role,
      firstName: firstName ?? this.firstName,
      lastName: lastName ?? this.lastName,
      phoneNumber: phoneNumber ?? this.phoneNumber,
      profileImage: profileImage ?? this.profileImage,
      bio: bio ?? this.bio,
      createdAt: createdAt ?? this.createdAt,
      authToken: authToken ?? this.authToken,
    );
  }

  @override
  String toString() =>
      'User(userId: $userId, username: $username, email: $email, role: $role)';
}
