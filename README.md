# API Gateway with JWT Authentication

A Laravel-based API Gateway for microservices architecture with JWT token-based authentication.

## Overview

This API Gateway serves as a single entry point for microservices, handling:
- **JWT Authentication** - Stateless token-based authentication
- **Request Routing** - Forward authenticated requests to microservices
- **Centralized Security** - Single point for authentication and authorization
- **Role-Based Access** - User roles included in JWT tokens

## Architecture

```
Client → API Gateway (JWT Auth) → Microservices
         ↓
    Validates JWT
    Adds user context
    Forwards request
```

## Features

- ✅ JWT token generation and validation
- ✅ User authentication (login)
- ✅ User registration
- ✅ JWT middleware for protected routes
- ✅ Role-based authorization
- ✅ Admin middleware for protected routes
- ✅ Admin role management endpoint
- ✅ Stateless authentication (no sessions)
- ✅ Token expiration handling
- 🚧 Service proxy (coming soon)
- 🚧 Rate limiting (coming soon)
- 🚧 Request logging (coming soon)

## Requirements

- PHP 8.2+
- MySQL 5.7+
- Composer
- Laravel 12

## Installation

### 1. Clone and Install Dependencies

```bash
git clone <repository-url>
cd api-gateway
composer install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure Database

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=api_gateway
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 4. Configure JWT

Already configured in `.env`:

```env
JWT_SECRET=your-secret-key
JWT_TTL=60
JWT_REFRESH_TTL=20160
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Create Test User

```bash
php artisan tinker
```

```php
User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => bcrypt('password123'),
    'role' => 'admin'
]);
exit
```

## API Endpoints

### Authentication

#### Register
```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "name": "John Doe",
      "email": "john@example.com",
      "role": "user"
    }
  }
}
```

**Note:** All new users are assigned the 'user' role by default. Only admins can change user roles.

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "name": "Admin User",
      "email": "admin@example.com",
      "role": "admin"
    }
  }
}
```

### Protected Routes

#### Get Profile
```http
GET /api/profile
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response:**
```json
{
  "success": true,
  "data": {
    "email": "admin@example.com",
    "name": "Admin User",
    "role": "admin"
  }
}
```

### Admin Routes

#### Update User Role (Admin Only)
```http
PUT /api/admin/users/role
Authorization: Bearer ADMIN_JWT_TOKEN
Content-Type: application/json

{
  "email": "user@example.com",
  "role": "admin"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "User role updated successfully",
  "data": {
    "email": "user@example.com",
    "name": "John Doe",
    "role": "admin"
  }
}
```

**Response (Non-admin):**
```json
{
  "success": false,
  "message": "Admin access required"
}
```

**Response (User not found):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The selected email is invalid."]
  }
}
```

**Note:** Only users with 'admin' role can update user roles. Valid roles are 'user' and 'admin'.

## JWT Token Structure

### Payload
```json
{
  "email": "admin@example.com",
  "name": "Admin User",
  "role": "admin",
  "iat": 1234567890,
  "exp": 1234571490
}
```

**Note:** User ID is NOT included in the token for security reasons.

## Usage

### 1. Start the Server

```bash
php artisan serve
```

### 2. Register a New User

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### 3. Login to Get Token

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"jane@example.com","password":"password123"}'
```

### 4. Use Token for Authenticated Requests

```bash
curl -X GET http://localhost:8000/api/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Protecting Routes with JWT Middleware

### Using JWT Middleware

```php
// In routes/api.php
Route::middleware('jwt')->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

### Using Admin Middleware

```php
// In routes/api.php
Route::middleware(['jwt', 'admin'])->group(function () {
    Route::put('/admin/users/role', [AdminController::class, 'updateRole']);
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});
```

### Accessing User Info in Controllers

```php
public function profile(Request $request)
{
    $email = $request->input('user_email');
    $name = $request->input('user_name');
    $role = $request->input('user_role');
    
    // Your logic here
}
```

## Project Structure

```
api-gateway/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php      # Authentication logic
│   │   │   └── AdminController.php     # Admin role management
│   │   ├── Middleware/
│   │   │   ├── JwtMiddleware.php       # JWT validation
│   │   │   └── AdminMiddleware.php     # Admin authorization
│   │   └── Requests/
│   │       ├── LoginRequest.php        # Login validation
│   │       ├── RegisterRequest.php     # Register validation
│   │       └── UpdateRoleRequest.php   # Role update validation
│   ├── Models/
│   │   └── User.php                    # User model with role
│   └── Services/
│       └── JWTService.php              # JWT generation/validation
├── config/
│   └── jwt.php                         # JWT configuration
├── database/
│   └── migrations/
│       └── *_add_role_to_users_table.php
└── routes/
    └── api.php                         # API routes
```

## Configuration

### JWT Settings (`config/jwt.php`)

```php
'secret' => env('JWT_SECRET'),      // Secret key for signing
'ttl' => env('JWT_TTL', 60),        // Token lifetime (minutes)
'refresh_ttl' => env('JWT_REFRESH_TTL', 20160), // Refresh token lifetime
'algo' => 'HS256',                  // Signing algorithm
```

## Security

- JWT tokens are signed with HMAC-SHA256
- Passwords are hashed with bcrypt
- Tokens expire after 60 minutes (configurable)
- User ID not exposed in JWT payload
- Role-based access control with admin middleware
- All new users default to 'user' role (prevents privilege escalation)
- Password confirmation required for registration
- Sensitive data (emails) in request body, not URL (prevents logging exposure)
- Admin-only endpoints protected with dual middleware (JWT + Admin)

## Development

### Run Tests

```bash
php artisan test
```

### Code Style

```bash
./vendor/bin/pint
```

### Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
```

## Testing with Postman

Import the `postman_collection.json` file into Postman for easy API testing.

The collection includes:
- Register endpoint
- Login endpoint
- Protected profile endpoint
- Auto-save JWT tokens
- Example responses

## Roadmap

- [x] JWT authentication
- [x] Login endpoint
- [x] Register endpoint
- [x] JWT middleware
- [x] Role-based tokens
- [x] Admin middleware
- [x] Update user role endpoint (admin only)
- [ ] Token refresh endpoint
- [ ] Logout endpoint
- [ ] Service proxy
- [ ] Rate limiting
- [ ] Request logging
- [ ] CORS configuration

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

MIT License

## Support

For issues and questions, please open an issue on GitHub.
