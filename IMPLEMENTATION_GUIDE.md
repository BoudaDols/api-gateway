# API Gateway Implementation Guide

A complete step-by-step guide documenting how we built this JWT-based API Gateway for microservices.

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Step 1: JWT Configuration](#step-1-jwt-configuration)
3. [Step 2: JWT Service](#step-2-jwt-service)
4. [Step 3: Login Endpoint](#step-3-login-endpoint)
5. [Step 4: Register Endpoint](#step-4-register-endpoint)
6. [Step 5: JWT Middleware](#step-5-jwt-middleware)
7. [Step 6: Docker Setup](#step-6-docker-setup)
8. [Architecture Decisions](#architecture-decisions)
9. [Security Considerations](#security-considerations)

---

## Project Overview

### Goal
Build an API Gateway that:
- Authenticates users with JWT tokens
- Provides centralized authentication for microservices
- Supports role-based access control
- Is stateless (no sessions)

### Tech Stack
- **Framework**: Laravel 12
- **PHP**: 8.2+
- **Database**: MySQL 8.0
- **Authentication**: Custom JWT implementation
- **Containerization**: Docker + Docker Compose

---

## Step 1: JWT Configuration

### Purpose
Store JWT settings (secret key, token lifetime, algorithm).

### File Created
`config/jwt.php`

### Implementation
```php
<?php

return [
    'secret' => env('JWT_SECRET', env('APP_KEY')),
    'ttl' => env('JWT_TTL', 60),              // 60 minutes
    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160), // 2 weeks
    'algo' => 'HS256',                        // HMAC-SHA256
];
```

### Why These Settings?
- **secret**: Uses APP_KEY as fallback (always available)
- **ttl**: 60 minutes balances security and UX
- **refresh_ttl**: 2 weeks for refresh tokens (future feature)
- **algo**: HS256 is industry standard, fast, and secure

### Environment Variables
Added to `.env`:
```env
JWT_SECRET=your-secret-key
JWT_TTL=60
JWT_REFRESH_TTL=20160
```

---

## Step 2: JWT Service

### Purpose
Handle all JWT operations (generation and validation).

### File Created
`app/Services/JWTService.php`

### Key Methods

#### 1. generateToken()
```php
public function generateToken(array $payload): string
{
    // Create header
    $header = base64UrlEncode(json_encode([
        'typ' => 'JWT',
        'alg' => 'HS256'
    ]));

    // Add timestamps
    $payload['iat'] = time();           // Issued at
    $payload['exp'] = time() + $ttl;    // Expiration

    $payload = base64UrlEncode(json_encode($payload));

    // Create signature
    $signature = base64UrlEncode(
        hash_hmac('sha256', "$header.$payload", $secret, true)
    );

    return "$header.$payload.$signature";
}
```

**How it works:**
1. Creates header with algorithm info
2. Adds timestamps to payload
3. Base64 URL encodes both
4. Signs with HMAC-SHA256
5. Returns: `header.payload.signature`

#### 2. validateToken()
```php
public function validateToken(string $token): ?array
{
    [$header, $payload, $signature] = explode('.', $token);

    // Verify signature
    $validSignature = base64UrlEncode(
        hash_hmac('sha256', "$header.$payload", $secret, true)
    );

    if ($signature !== $validSignature) {
        return null;  // Invalid signature
    }

    $payload = json_decode(base64UrlDecode($payload), true);

    // Check expiration
    if ($payload['exp'] < time()) {
        return null;  // Expired
    }

    return $payload;
}
```

**How it works:**
1. Splits token into 3 parts
2. Recalculates signature
3. Compares signatures
4. Checks expiration
5. Returns payload or null

### Why Custom JWT?
- **No external dependencies** - Lightweight
- **Full control** - Customize as needed
- **Learning** - Understand how JWT works
- **Simple** - Only 100 lines of code

---

## Step 3: Login Endpoint

### Purpose
Authenticate users and issue JWT tokens.

### Files Created

#### 1. LoginRequest.php
`app/Http/Requests/LoginRequest.php`

```php
public function rules(): array
{
    return [
        'email' => 'required|email',
        'password' => 'required|string|min:6',
    ];
}
```

**Why validation?**
- Prevents invalid data from reaching controller
- Returns consistent error format
- Validates email format
- Ensures password minimum length

#### 2. AuthController.php
`app/Http/Controllers/AuthController.php`

```php
public function login(LoginRequest $request): JsonResponse
{
    // Find user
    $user = User::where('email', $request->email)->first();

    // Verify password
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials'
        ], 401);
    }

    // Generate token
    $token = $this->jwtService->generateToken([
        'email' => $user->email,
        'name' => $user->name,
        'role' => $user->role,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]
    ]);
}
```

**Flow:**
1. Request validated by LoginRequest
2. Find user by email
3. Verify password with bcrypt
4. Generate JWT with user info
5. Return token + user data

#### 3. Route
`routes/api.php`

```php
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});
```

### Security Decisions

#### ❌ User ID NOT in Token
```php
// We do NOT include:
'user_id' => $user->id,  // ❌ Security risk
```

**Why?**
- User IDs are sequential (1, 2, 3...)
- Attackers can enumerate users
- Email is unique and less predictable

#### ✅ Role in Token
```php
// We DO include:
'role' => $user->role,  // ✅ For authorization
```

**Why?**
- Needed for role-based access control
- Microservices need to know user role
- Avoids database lookups

---

## Step 4: Register Endpoint

### Purpose
Allow new users to create accounts with default 'user' role.

### Files Created

#### 1. RegisterRequest.php
`app/Http/Requests/RegisterRequest.php`

```php
public function rules(): array
{
    return [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6|confirmed',
        // NO role field - security!
    ];
}
```

**Key validations:**
- `unique:users,email` - Prevents duplicate accounts
- `confirmed` - Requires password_confirmation field
- **No role field** - Prevents privilege escalation

#### 2. Database Migration
`database/migrations/*_add_role_to_users_table.php`

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->default('user')->after('email');
    });
}
```

**Why default 'user'?**
- Security: New users can't be admins
- Explicit: Clear what role new users get
- Database-level: Even direct inserts get 'user' role

#### 3. Register Method
`app/Http/Controllers/AuthController.php`

```php
public function register(RegisterRequest $request): JsonResponse
{
    // Create user with HARDCODED 'user' role
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'role' => 'user',  // ALWAYS 'user'
    ]);

    // Generate token immediately
    $token = $this->jwtService->generateToken([
        'email' => $user->email,
        'name' => $user->name,
        'role' => $user->role,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Registration successful',
        'data' => [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]
    ], 201);
}
```

**Security Features:**
1. Role is hardcoded to 'user'
2. User can't specify role in request
3. Password is hashed with bcrypt
4. Password confirmation required
5. Email uniqueness enforced

### User Experience
- User registers and gets token immediately
- No need to login after registration
- Seamless onboarding

---

## Step 5: JWT Middleware

### Purpose
Validate JWT tokens on protected routes and attach user info to requests.

### File Created
`app/Http/Middleware/JwtMiddleware.php`

```php
public function handle(Request $request, Closure $next): Response
{
    // Get token from Authorization header
    $token = $request->bearerToken();

    if (!$token) {
        return response()->json([
            'success' => false,
            'message' => 'Token not provided'
        ], 401);
    }

    // Validate token
    $payload = $this->jwtService->validateToken($token);

    if (!$payload) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired token'
        ], 401);
    }

    // Attach user info to request
    $request->merge([
        'user_email' => $payload['email'],
        'user_name' => $payload['name'],
        'user_role' => $payload['role'],
    ]);

    return $next($request);
}
```

**How it works:**
1. Extracts token from `Authorization: Bearer <token>` header
2. Validates token signature and expiration
3. Attaches user info to request
4. Allows request to proceed

### Registration
`bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'jwt' => \App\Http\Middleware\JwtMiddleware::class,
    ]);
})
```

### Usage in Routes
```php
Route::middleware('jwt')->group(function () {
    Route::get('/profile', function (Request $request) {
        return response()->json([
            'email' => $request->input('user_email'),
            'name' => $request->input('user_name'),
            'role' => $request->input('user_role'),
        ]);
    });
});
```

### Usage in Controllers
```php
public function dashboard(Request $request)
{
    $email = $request->input('user_email');
    $role = $request->input('user_role');
    
    // Your logic here
}
```

---

## Step 6: Docker Setup

### Purpose
Containerize the application for consistent deployment across environments.

### Files Created

#### 1. Dockerfile
`Dockerfile`

```dockerfile
FROM php:8.2-fpm

WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    zip unzip nginx supervisor

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application
COPY . /var/www/html

# Copy configs
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

**Why PHP 8.2?**
- Stable and well-tested with Laravel 12
- All packages compatible
- Production-ready

#### 2. docker-compose.yml
```yaml
services:
  app:
    build: .
    ports:
      - "8000:80"
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_DATABASE=api_gateway
      - DB_USERNAME=root
      - DB_PASSWORD=secret

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: api_gateway
      MYSQL_ROOT_PASSWORD: secret
    ports:
      - "3307:3306"
    volumes:
      - dbdata:/var/lib/mysql

volumes:
  dbdata:
```

**Architecture:**
- **app**: Laravel application (Nginx + PHP-FPM)
- **db**: MySQL database
- **Network**: Private network for communication
- **Volume**: Persistent database storage

#### 3. Nginx Configuration
`docker/nginx/default.conf`

```nginx
server {
    listen 80;
    root /var/www/html/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

**Why Nginx?**
- Fast static file serving
- Efficient reverse proxy
- Industry standard

#### 4. Supervisor Configuration
`docker/supervisor/supervisord.conf`

```ini
[program:php-fpm]
command=/usr/local/sbin/php-fpm

[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"
```

**Why Supervisor?**
- Runs multiple processes in one container
- Manages PHP-FPM and Nginx together
- Auto-restarts on failure

---

## Architecture Decisions

### 1. Why Custom JWT Instead of Package?

**Pros:**
- ✅ No external dependencies
- ✅ Full control over implementation
- ✅ Lightweight (100 lines)
- ✅ Easy to customize
- ✅ Learning opportunity

**Cons:**
- ❌ Need to maintain ourselves
- ❌ No advanced features (refresh tokens, blacklist)

**Decision:** Custom JWT is sufficient for this use case.

### 2. Why No User ID in Token?

**Security Risk:**
```php
// BAD:
'user_id' => 1  // Sequential, predictable
```

**Better:**
```php
// GOOD:
'email' => 'user@example.com'  // Unique, less predictable
```

**Reasoning:**
- User IDs are sequential (1, 2, 3...)
- Attackers can enumerate: "Try user_id 1, 2, 3..."
- Email is unique and harder to guess

### 3. Why Role in Token?

**Benefits:**
- ✅ Microservices know user role without database call
- ✅ Fast authorization decisions
- ✅ Stateless architecture

**Trade-off:**
- ❌ If role changes, old tokens still have old role
- ✅ Solution: Short token lifetime (60 min)

### 4. Why Stateless (No Sessions)?

**Benefits:**
- ✅ Horizontal scaling (no session storage)
- ✅ Works across multiple servers
- ✅ No database lookups per request
- ✅ Perfect for microservices

**Trade-offs:**
- ❌ Can't revoke tokens immediately
- ✅ Solution: Short expiration + token blacklist (future)

---

## Security Considerations

### 1. Password Security
```php
// Hashing
'password' => bcrypt($request->password)

// Verification
Hash::check($request->password, $user->password)
```

- **bcrypt** with cost factor 12
- Salted automatically
- Slow by design (prevents brute force)

### 2. Token Security
- **HMAC-SHA256** signing
- **Secret key** from environment
- **Expiration** after 60 minutes
- **Signature verification** on every request

### 3. Role Security
- **Default role** 'user' in database
- **Hardcoded** in registration
- **No user input** for role
- **Future:** Admin-only role update endpoint

### 4. Input Validation
- **Email format** validation
- **Password length** minimum 6 chars
- **Password confirmation** required
- **Email uniqueness** enforced

### 5. Error Messages
```php
// Generic error (don't reveal if email exists)
'message' => 'Invalid credentials'

// Not:
'message' => 'Email not found'  // ❌ Information leak
'message' => 'Wrong password'   // ❌ Information leak
```

---

## API Endpoints Summary

### Public Endpoints
```
POST /api/auth/register  - Create account
POST /api/auth/login     - Get JWT token
```

### Protected Endpoints (Require JWT)
```
GET /api/profile         - Get user info from token
```

### Future Endpoints
```
POST /api/auth/refresh   - Refresh token
POST /api/auth/logout    - Blacklist token
PUT /api/admin/users/{email}/role  - Update user role (admin only)
```

---

## Testing the API

### 1. Register
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### 2. Login
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

### 3. Access Protected Route
```bash
curl -X GET http://localhost:8000/api/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## What We Built

### ✅ Completed Features
1. JWT token generation and validation
2. User login endpoint
3. User registration endpoint
4. JWT middleware for protected routes
5. Role-based access (foundation)
6. Docker containerization
7. MySQL database integration
8. Postman collection for testing

### 🚧 Future Features
1. Token refresh endpoint
2. Logout with token blacklist
3. Admin middleware
4. Update user role endpoint (admin only)
5. Service proxy (forward to microservices)
6. Rate limiting
7. Request logging
8. CORS configuration

---

## Project Statistics

- **Files Created**: 15+
- **Lines of Code**: ~500
- **Time to Build**: ~3 hours
- **Dependencies**: Minimal (Laravel core only)
- **Docker Containers**: 2 (app + database)
- **API Endpoints**: 3 (register, login, profile)

---

## Key Takeaways

1. **JWT is simple** - Only 100 lines for full implementation
2. **Security first** - No user ID in token, hardcoded roles
3. **Stateless design** - Perfect for microservices
4. **Docker ready** - Consistent across environments
5. **Extensible** - Easy to add more features

---

## Next Steps

To continue building:
1. Implement token refresh
2. Add admin middleware
3. Build service proxy
4. Add rate limiting
5. Implement request logging
6. Add CORS support
7. Create admin dashboard

---

## Resources

- **Laravel Docs**: https://laravel.com/docs
- **JWT Spec**: https://jwt.io
- **Docker Docs**: https://docs.docker.com
- **Postman Collection**: `postman_collection.json`

---

**Built with ❤️ for learning and production use**
