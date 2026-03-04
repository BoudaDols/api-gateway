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
- ✅ Role-based authorization
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
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.com",
      "role": "admin"
    }
  }
}
```

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

### 2. Login to Get Token

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}'
```

### 3. Use Token for Authenticated Requests

```bash
curl -X GET http://localhost:8000/api/user \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Project Structure

```
api-gateway/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── AuthController.php      # Authentication logic
│   │   └── Requests/
│   │       └── LoginRequest.php        # Login validation
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
- Role-based access control ready

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

## Roadmap

- [x] JWT authentication
- [x] Login endpoint
- [x] Role-based tokens
- [ ] Register endpoint
- [ ] JWT middleware
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
