# API Gateway - Features & Roadmap

## ✅ Implemented Features

### 1. JWT Authentication System
**Status:** ✅ Complete

**What we built:**
- Custom JWT service (generation & validation)
- HMAC-SHA256 signing algorithm
- Token expiration (60 minutes configurable)
- Base64 URL encoding/decoding
- Signature verification

**Files:**
- `config/jwt.php` - JWT configuration
- `app/Services/JWTService.php` - JWT logic

---

### 2. User Registration
**Status:** ✅ Complete

**What we built:**
- Public registration endpoint
- Email uniqueness validation
- Password confirmation requirement
- Automatic 'user' role assignment (security)
- Immediate JWT token issuance
- Password hashing with bcrypt

**Endpoint:** `POST /api/auth/register`

**Request:**
```json
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

**Files:**
- `app/Http/Requests/RegisterRequest.php` - Validation
- `app/Http/Controllers/AuthController.php` - Logic
- `database/migrations/*_add_role_to_users_table.php` - Database

**Security:**
- ✅ No user ID in token (prevents enumeration)
- ✅ Role hardcoded to 'user' (prevents privilege escalation)
- ✅ Password confirmation required
- ✅ Email uniqueness enforced

---

### 3. User Login
**Status:** ✅ Complete

**What we built:**
- Public login endpoint
- Email/password authentication
- JWT token generation
- Generic error messages (security)
- User data in response

**Endpoint:** `POST /api/auth/login`

**Request:**
```json
{
  "email": "john@example.com",
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
      "name": "John Doe",
      "email": "john@example.com",
      "role": "user"
    }
  }
}
```

**Files:**
- `app/Http/Requests/LoginRequest.php` - Validation
- `app/Http/Controllers/AuthController.php` - Logic

**Security:**
- ✅ Generic error message (doesn't reveal if email exists)
- ✅ Password verification with bcrypt
- ✅ No user ID in token

---

### 4. JWT Middleware
**Status:** ✅ Complete

**What we built:**
- Token validation middleware
- Bearer token extraction
- Signature verification
- Expiration checking
- User context injection into requests

**Usage:**
```php
Route::middleware('jwt')->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
});
```

**Access user info in controllers:**
```php
$email = $request->input('user_email');
$name = $request->input('user_name');
$role = $request->input('user_role');
```

**Files:**
- `app/Http/Middleware/JwtMiddleware.php` - Middleware logic
- `bootstrap/app.php` - Middleware registration

**Features:**
- ✅ Validates token signature
- ✅ Checks token expiration
- ✅ Attaches user info to request
- ✅ Returns 401 for invalid/missing tokens

---

### 5. Protected Routes
**Status:** ✅ Complete

**What we built:**
- Profile endpoint (example)
- JWT middleware protection
- User context from token

**Endpoint:** `GET /api/profile`

**Headers:**
```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response:**
```json
{
  "success": true,
  "data": {
    "email": "john@example.com",
    "name": "John Doe",
    "role": "user"
  }
}
```

**Files:**
- `routes/api.php` - Route definition

---

### 6. Role-Based Access Foundation
**Status:** ✅ Complete

**What we built:**
- User role column in database
- Default 'user' role for new registrations
- Role included in JWT token
- Role available in all protected routes

**Roles:**
- `user` - Default role for all new users
- `admin` - Must be set manually (future: admin endpoint)

**Files:**
- `database/migrations/*_add_role_to_users_table.php`
- `app/Models/User.php` - Role in fillable

---

### 7. Docker Containerization
**Status:** ✅ Complete

**What we built:**
- Dockerfile with PHP 8.2-FPM
- Docker Compose with app + MySQL
- Nginx web server configuration
- Supervisor for process management
- Persistent database volume
- Setup automation script

**Services:**
- **API Gateway**: http://localhost:8000
- **MySQL**: localhost:3307

**Files:**
- `Dockerfile` - Application container
- `docker-compose.yml` - Multi-container setup
- `docker/nginx/default.conf` - Nginx config
- `docker/supervisor/supervisord.conf` - Process manager
- `docker/php/local.ini` - PHP settings
- `docker-setup.sh` - Automation script
- `.dockerignore` - Build optimization

**Commands:**
```bash
# Start
docker-compose up -d

# Stop
docker-compose down

# Logs
docker-compose logs -f

# Shell access
docker-compose exec app bash
```

---

### 8. API Documentation
**Status:** ✅ Complete

**What we built:**
- Comprehensive README
- Postman collection
- Implementation guide
- Docker documentation

**Files:**
- `README.md` - Main documentation
- `postman_collection.json` - API testing
- `IMPLEMENTATION_GUIDE.md` - Build process
- `DOCKER.md` - Docker usage
- `DOCKER_TROUBLESHOOTING.md` - Troubleshooting

---

### 9. Admin Middleware
**Status:** ✅ Complete

**What we built:**
- Admin middleware that checks user role
- Returns 403 Forbidden if user is not admin
- Works in combination with JWT middleware
- Dual middleware protection (JWT + Admin)

**Usage:**
```php
Route::middleware(['jwt', 'admin'])->group(function () {
    Route::put('/admin/users/role', [AdminController::class, 'updateRole']);
});
```

**Files:**
- `app/Http/Middleware/AdminMiddleware.php` - Admin authorization
- `bootstrap/app.php` - Middleware registration

**Security:**
- ✅ Checks user_role from JWT token
- ✅ Returns 403 for non-admin users
- ✅ Must be used after JWT middleware

---

### 10. Admin Role Management
**Status:** ✅ Complete

**What we built:**
- Admin-only endpoint to update user roles
- Email in request body (not URL) for security
- Validates role (only 'user' or 'admin' allowed)
- Validates email exists in database
- Uses validated() method to prevent SQL injection

**Endpoint:** `PUT /api/admin/users/role`

**Request:**
```json
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

**Response (Invalid email):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The selected email is invalid."]
  }
}
```

**Files:**
- `app/Http/Controllers/AdminController.php` - Role update logic
- `app/Http/Requests/UpdateRoleRequest.php` - Validation (email exists, role in:user,admin)
- `routes/api.php` - Admin route with dual middleware

**Security:**
- ✅ Email in request body, not URL (prevents logging exposure)
- ✅ Dual middleware protection (JWT + Admin)
- ✅ Email validation with exists:users,email
- ✅ Role validation with in:user,admin
- ✅ Uses validated() method explicitly
- ✅ Protected against SQL injection

---

### 11. Token Refresh
**Status:** ✅ Complete

**What we built:**
- Refresh endpoint to extend token lifetime
- Decode token without expiration check
- Verify signature validity
- Refresh window (14 days default)
- Generate new token with same user data

**Endpoint:** `POST /api/auth/refresh`

**Request:**
```http
POST /api/auth/refresh
Authorization: Bearer OLD_OR_EXPIRED_TOKEN
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

**Response (Token too old):**
```json
{
  "success": false,
  "message": "Token cannot be refreshed. Please login again."
}
```

**Response (No token):**
```json
{
  "success": false,
  "message": "Token not provided"
}
```

**Files:**
- `app/Services/JWTService.php` - Added refreshToken(), decodeToken(), verifySignature()
- `app/Http/Controllers/AuthController.php` - Added refresh() method
- `routes/api.php` - Added refresh route

**Features:**
- ✅ Refresh expired tokens (within 14 days)
- ✅ Verify signature before refresh
- ✅ Configurable refresh window
- ✅ No JWT middleware required (token might be expired)
- ✅ Timing-safe signature comparison

**Security:**
- ✅ Signature must be valid
- ✅ Limited refresh window (14 days)
- ✅ New token generated (old one discarded)
- ✅ Same user data preserved

---

### 12. CORS Configuration
**Status:** ✅ Complete

**What we built:**
- `config/cors.php` with allowed origins, methods, and headers
- `CORS_ALLOWED_ORIGINS` env variable for per-environment configuration
- Laravel's built-in `HandleCors` middleware handles it automatically

**Files:**
- `config/cors.php` - CORS configuration
- `.env.example` - Added `CORS_ALLOWED_ORIGINS`

**Security:**
- ✅ Origins configurable via environment (restrict in production)
- ✅ Only necessary headers allowed (Content-Type, Authorization)
- ✅ credentials not exposed by default

---

### 13. Logout with Token Blacklist
**Status:** ✅ Complete

**What we built:**
- `token_blacklist` table to store revoked tokens
- `TokenBlacklistService` with `blacklist()`, `isBlacklisted()`, `purgeExpired()` methods
- `POST /api/auth/logout` endpoint (JWT protected)
- Blacklist check in `JwtMiddleware` after signature validation
- `tokens:purge` artisan command scheduled daily to clean expired tokens

**Endpoint:** `POST /api/auth/logout`

**Request:**
```http
POST /api/auth/logout
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

**Files:**
- `database/migrations/*_create_token_blacklist_table.php`
- `app/Models/TokenBlacklist.php`
- `app/Services/TokenBlacklistService.php`
- `app/Console/Commands/PurgeExpiredTokens.php`
- `app/Http/Controllers/AuthController.php` - Added logout()
- `app/Http/Middleware/JwtMiddleware.php` - Added blacklist check
- `routes/api.php` - Added logout route
- `routes/console.php` - Registered daily purge schedule

**Security:**
- ✅ Blacklist check after signature validation (no wasted DB queries on invalid tokens)
- ✅ Token stored with `expires_at` for automatic cleanup
- ✅ Revoked tokens return 401 even if not expired
- ✅ Cleanup command prevents table bloat

---

### 14. Rate Limiting
**Status:** ✅ Complete

**What we built:**
- 3 named rate limiters defined in `AppServiceProvider`
- `login` — 5 attempts per minute per IP (brute force protection)
- `register` — 10 attempts per hour per IP (spam protection)
- `api` — 60 requests per minute per IP (general abuse protection)
- JSON 429 response override in `bootstrap/app.php`

**Response when limit exceeded:**
```json
{
  "success": false,
  "message": "Too many requests. Please slow down."
}
```

**Files:**
- `app/Providers/AppServiceProvider.php` - Rate limiter definitions
- `routes/api.php` - throttle middleware attached to routes
- `bootstrap/app.php` - JSON 429 response override

**Security:**
- ✅ Login brute force protection (5/min)
- ✅ Registration spam protection (10/hour)
- ✅ General API abuse protection (60/min)
- ✅ IP-based limiting (no auth required to limit)

---

### 15. Request Logging
**Status:** ✅ Complete

**What we built:**
- `stdout` log channel in `config/logging.php` using Monolog `JsonFormatter`
- `LogRequestMiddleware` that logs every API request as structured JSON
- Registered globally on the `api` middleware group

**Log format (one line per request):**
```json
{"timestamp":"2024-01-15T10:30:00Z","method":"POST","url":"api/auth/login","status":200,"duration_ms":45,"ip":"192.168.1.1","user":null}
```

**Fields logged:**
- `timestamp` - ISO 8601
- `method` - HTTP verb
- `url` - request path
- `status` - response status code
- `duration_ms` - response time in milliseconds
- `ip` - client IP address
- `user` - authenticated user email (null if unauthenticated)

**What is NOT logged (security):**
- Request body (could contain passwords)
- Request headers (could contain tokens)
- Response body (could contain sensitive data)

**Files:**
- `app/Http/Middleware/LogRequestMiddleware.php` - Logging logic
- `config/logging.php` - Added `stdout` channel with `JsonFormatter`
- `bootstrap/app.php` - Registered middleware on `api` group

**Cloud compatibility:**
- ✅ Writes to stdout — captured by Docker automatically
- ✅ JSON format — parsed natively by CloudWatch, Datadog, ELK
- ✅ Zero DB overhead
- ✅ Works with AWS ECS, GCP Cloud Run, Kubernetes

---

### 16. Service Proxy/Gateway
**Status:** ✅ Complete

**What we built:**
- `config/gateway.php` — dynamic service registry (auto-discovers `SERVICE_*_URL` env vars)
- `ServiceProxyService` — forwards requests with user context headers
- `GatewayController` — looks up service, calls proxy, returns response
- Catch-all route `ANY /api/services/{service}/{path}`
- 502 on connection failure, 404 on unknown service, 504 on timeout

**Endpoint:** `ANY /api/services/{service}/{path}`

**Example:**
```bash
GET /api/services/orders/123
Authorization: Bearer JWT_TOKEN

# Forwards to:
GET http://order-service:3000/123
X-User-Email: john@example.com
X-User-Name: John Doe
X-User-Role: user
```

**Files:**
- `config/gateway.php` - Dynamic service registry (auto-discovers `SERVICE_*_URL` env vars)
- `app/Services/ServiceProxyService.php` - HTTP forwarding
- `app/Http/Controllers/GatewayController.php` - Proxy logic
- `routes/api.php` - Catch-all proxy route
- `.env.example` - Service URL variables (any `SERVICE_*_URL` is auto-registered)

**Error responses:**
- `404` - Service not in registry
- `502` - Microservice is down
- `504` - Microservice timed out

**Security:**
- ✅ JWT required (jwt middleware)
- ✅ Rate limited (throttle:api)
- ✅ User context forwarded as headers (not JWT)
- ✅ Microservices never see the JWT token

---

## 🔮 Future Enhancements

### Observability (cloud-native, no app changes needed)
- See **Prometheus Metrics / Observability** section below for AWS, Kubernetes, and Vector approaches

### V2 Phone/OTP Authentication (~4 hours)
- Build after V1 is deployed and stable
- Requires choosing an SMS provider (Twilio, Vonage, AWS SNS)

### Redis-backed Metrics (~2 hours, optional)
- Only needed for self-hosted deployments without a log pipeline
- Adds Redis dependency

### Completed (16 features)
1. ✅ JWT Authentication System
2. ✅ User Registration
3. ✅ User Login
4. ✅ JWT Middleware
5. ✅ Protected Routes
6. ✅ Role-Based Access Foundation
7. ✅ Docker Containerization
8. ✅ API Documentation
9. ✅ Admin Middleware
10. ✅ Admin Role Management
11. ✅ Token Refresh
12. ✅ CORS Configuration
13. ✅ Logout with Token Blacklist
14. ✅ Rate Limiting
15. ✅ Request Logging
16. ✅ Service Proxy/Gateway

### Future (after V1)
- 📝 Observability via CloudWatch / Fluent Bit / Vector (documented, no app changes)
- 🔮 V2 Phone/OTP Authentication (~4 hours)
- 🔮 Redis-backed Metrics (~2 hours, self-hosted only)

---

## Recommended Implementation Order

**Phase 1: Authentication & Authorization (COMPLETE ✅)**
- ✅ JWT Authentication
- ✅ User Registration & Login
- ✅ JWT Middleware
- ✅ Admin Middleware
- ✅ Admin Role Management
- ✅ Token Refresh

**Phase 2: Production Configuration (COMPLETE ✅)**
- ✅ CORS Configuration
- ✅ Logout with Blacklist

**Phase 3: Production Features (COMPLETE ✅)**
- ✅ Rate Limiting
- ✅ Request Logging

**Phase 4: Gateway Core (COMPLETE ✅)**
- ✅ Service Proxy/Gateway

**Phase 5: Observability (infrastructure level — no app changes)**
- 📝 CloudWatch / Fluent Bit / Vector (see Observability section)

---

**Current Status:** 100% Complete (16/16 features) 🎉
**V1 Production Ready:** YES
**Next:** Prometheus metrics, then V2 Phone/OTP

---

## Why Build Service Proxy Last?

### Reasons:
1. **Most Complex** - Requires understanding of all other features
2. **Needs Real Services** - Hard to test without actual microservices
3. **Integration Point** - Should integrate rate limiting, logging, etc.
4. **Optional** - API Gateway works fine without it for authentication-only use cases
5. **Better Understanding** - You'll know the gateway better after building other features

### When to Build:
- ✅ After completing all other features
- ✅ When you have actual microservices to connect
- ✅ When you understand your microservice architecture
- ✅ When you need to integrate rate limiting and logging into proxy

### Alternative:
If you don't need a proxy, you can:
- Use this as authentication-only gateway
- Let frontend call microservices directly
- Use external gateway (Kong, Nginx, AWS API Gateway)

---

## 🔮 Future: Prometheus Metrics / Observability

**Status:** 📝 Documented (not implemented in app — handled at infrastructure level)

**Why not a PHP `/metrics` endpoint:**
A PHP-based `/metrics` endpoint using APCu is per-process only. In production with multiple PHP-FPM workers and multiple container replicas, each process has its own memory — Prometheus would get partial, inaccurate counts. It is not suitable for cloud deployments.

**The cloud-native approach (recommended):**

The stdout JSON logs already emitted by `LogRequestMiddleware` are the correct foundation. Metrics are derived from logs at the infrastructure level — no app changes needed.

**On AWS:**
```
stdout JSON → CloudWatch Logs
           → CloudWatch Metric Filters (count requests, errors, latency)
           → CloudWatch Alarms (alert on 5xx spike, high latency)
           → Grafana (via CloudWatch datasource)
```
Example metric filter — count all 5xx errors:
```
{ $.status >= 500 }
```

**On Kubernetes:**
```
stdout JSON → Fluent Bit sidecar → Loki (logs) + Prometheus (metrics)
                                 → Grafana dashboards
```
Fluent Bit reads stdout, parses the JSON, and emits Prometheus metrics automatically.

**On any platform with Vector:**
```
stdout JSON → Vector → Prometheus remote_write → Grafana
```
Vector is a lightweight log/metrics pipeline that can transform log lines into Prometheus metrics.

**If Redis-backed PHP metrics are needed (self-hosted):**
- Use `promphp/prometheus_client_php` with Redis storage
- Redis is shared across all workers and pods — accurate counts
- Adds Redis as a dependency
- Estimated time: ~2 hours

**What Prometheus would scrape (example metrics from logs):**
```
api_requests_total{method="POST",route="api/auth/login",status="200"} 42
api_requests_total{method="POST",route="api/auth/login",status="401"} 7
api_request_duration_ms_avg{route="api/auth/login"} 45
```

---


## 🔮 Future Version: V2 - Phone/OTP Authentication

**Status:** 📋 Planned (build after V1 is 100% complete)
**Estimated Total Time:** ~4 hours (log driver) / ~5 hours (real SMS provider)
**Prerequisite:** Choose SMS provider before starting

### Overview
A parallel authentication system running alongside V1. Same JWT, same middleware, same gateway — different identity method: **phone number + SMS OTP** instead of email + password.

```
V1: email + password → JWT
V2: phone + OTP     → JWT (same format, same middleware)
```

---

### Build Steps

#### Step 1: Database Migrations (~30 min)
**Status:** 📝 Not started

**Files to create:**
- `database/migrations/*_add_phone_to_users_table.php`
  - Add `phone` column (nullable, unique) to `users` table
- `database/migrations/*_create_phone_otps_table.php`
  - Create `phone_otps` table

**phone_otps table schema:**
```
id           - bigint, primary key
phone        - varchar, E.164 format (+1234567890)
code         - varchar(6), 6-digit OTP
type         - enum('register', 'login') - purpose of OTP
expires_at   - timestamp, 10 minutes from creation
verified_at  - timestamp, null until used (single-use enforcement)
timestamps
```

**users table change:**
```
phone  - varchar, nullable, unique
```

---

#### Step 2: SMS Configuration (~15 min)
**Status:** 📝 Not started

**Files to create:**
- `config/sms.php` — driver selection + provider credentials

```php
return [
    'driver' => env('SMS_DRIVER', 'log'), // log | twilio | vonage | aws_sns
    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],
    'vonage' => [
        'key'    => env('VONAGE_KEY'),
        'secret' => env('VONAGE_SECRET'),
        'from'   => env('VONAGE_FROM'),
    ],
    'aws_sns' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
];
```

**Files to modify:**
- `.env.example` — add SMS driver variables

---

#### Step 3: OtpService (~1 hour)
**Status:** 📝 Not started

**File to create:** `app/Services/OtpService.php`

**Methods:**
- `generate(string $phone, string $type): string` — creates 6-digit code, stores in DB, returns code
- `verify(string $phone, string $code, string $type): bool` — checks code is valid, not expired, not used, marks as verified
- `canRequest(string $phone): bool` — max 3 OTP requests per hour per phone
- `attemptsExceeded(string $phone, string $code): bool` — max 5 wrong attempts per OTP

**Security rules enforced:**
- OTP expires after 10 minutes
- OTP is single-use (verified_at set on first successful use)
- Max 3 OTP requests per hour per phone (brute force protection)
- Max 5 verification attempts per OTP (prevents guessing)

---

#### Step 4: SmsService (~30 min)
**Status:** 📝 Not started

**File to create:** `app/Services/SmsService.php`

**Methods:**
- `send(string $phone, string $message): void` — dispatches to correct driver

**Drivers:**

| Driver | How it works | Use case |
|---|---|---|
| `log` | Writes OTP to `storage/logs/laravel.log` | Development |
| `twilio` | Calls Twilio REST API via Guzzle | Production |
| `vonage` | Calls Vonage REST API via Guzzle | Production |
| `aws_sns` | Uses AWS SDK `SnsClient::publish()` | Production (AWS) |

**Start with `log` driver** — build and test everything without a real SMS account. Switch to real driver when ready to deploy.

---

#### Step 5: Form Requests (~15 min)
**Status:** 📝 Not started

**Files to create:**
- `app/Http/Requests/V2/SendOtpRequest.php`
  - `phone` — required, regex E.164 format: `/^\+[1-9]\d{7,14}$/`
  - `name` — required for register only
- `app/Http/Requests/V2/VerifyOtpRequest.php`
  - `phone` — required, E.164 format
  - `otp` — required, digits only, exactly 6 characters

---

#### Step 6: V2 AuthController (~1.5 hours)
**Status:** 📝 Not started

**File to create:** `app/Http/Controllers/V2/AuthController.php`

**Methods:**

`register(SendOtpRequest $request)` — Step 1 of registration
```
→ Check phone not already registered
→ Check OtpService::canRequest() (rate limit)
→ OtpService::generate(phone, 'register')
→ SmsService::send(phone, "Your OTP is: {code}")
→ Return { success: true, message: "OTP sent" }
```

`registerVerify(VerifyOtpRequest $request)` — Step 2 of registration
```
→ OtpService::verify(phone, otp, 'register')
→ Create user with phone + name, role = 'user'
→ JWTService::generateToken(phone, name, role)
→ Return token + user data (201)
```

`login(SendOtpRequest $request)` — Step 1 of login
```
→ Check OtpService::canRequest() (rate limit)
→ OtpService::generate(phone, 'login')
→ SmsService::send(phone, "Your OTP is: {code}")
→ Return { success: true, message: "OTP sent" }
  (same message whether phone exists or not — security)
```

`loginVerify(VerifyOtpRequest $request)` — Step 2 of login
```
→ OtpService::verify(phone, otp, 'login')
→ Find user by phone
→ JWTService::generateToken(phone, name, role)
→ Return token + user data
```

---

#### Step 7: Routes (~15 min)
**Status:** 📝 Not started

**File to modify:** `routes/api.php`

```php
// V2 Auth routes (phone + OTP)
Route::prefix('v2/auth')->group(function () {
    Route::post('register',        [V2AuthController::class, 'register']);
    Route::post('register/verify', [V2AuthController::class, 'registerVerify']);
    Route::post('login',           [V2AuthController::class, 'login']);
    Route::post('login/verify',    [V2AuthController::class, 'loginVerify']);
    // refresh + logout reuse V1 endpoints
});
```

**Note:** Token refresh and logout are shared with V1 — no new routes needed.

---

#### Step 8: Update User Model (~5 min)
**Status:** 📝 Not started

**File to modify:** `app/Models/User.php`
- Add `phone` to `$fillable`

---

#### Step 9: Tests (~30 min)
**Status:** 📝 Not started

**Files to create:**
- `tests/Unit/OtpServiceTest.php` — generate, verify, expiry, single-use, rate limit
- `tests/Unit/SmsServiceTest.php` — log driver writes to log, correct message format
- `tests/Feature/V2/RegisterTest.php` — full registration flow, duplicate phone, rate limit
- `tests/Feature/V2/LoginTest.php` — full login flow, unknown phone, wrong OTP, expired OTP

---

### JWT Token Structure (V2)
```json
{
  "phone": "+1234567890",
  "name": "John Doe",
  "role": "user",
  "iat": 1234567890,
  "exp": 1234571490
}
```

**Note:** No email in V2 token. Same JWT middleware works — it just reads whatever is in the token.

---

### SMS Provider Options

| Provider | Free Trial | Price/SMS | Best for |
|---|---|---|---|
| Log driver | N/A | Free | Development |
| Twilio | Yes ($15 credit) | ~$0.0075 | Most popular, easy setup |
| Vonage | Yes (€2 credit) | ~$0.0065 | Good alternative |
| AWS SNS | Yes (100 free/month) | ~$0.00645 | Already on AWS |

---

### What is reused from V1 (no changes needed)

| Component | Reused as-is |
|---|---|
| `JWTService` | ✅ generateToken, validateToken, refreshToken |
| `JwtMiddleware` | ✅ Works with any token payload |
| `AdminMiddleware` | ✅ Checks user_role regardless of auth method |
| `TokenBlacklistService` | ✅ Blacklists any JWT token |
| `POST /api/auth/refresh` | ✅ Shared endpoint |
| `POST /api/auth/logout` | ✅ Shared endpoint |
| All admin routes | ✅ Unchanged |
| Rate limiting | ✅ Add `otp` limiter in AppServiceProvider |
| Request logging | ✅ Logs V2 routes automatically |

---

### Prerequisites before starting
- [ ] Choose SMS provider (or confirm using `log` driver for now)
- [ ] If Twilio: create account, get Account SID + Auth Token + phone number
- [ ] If Vonage: create account, get API Key + Secret + sender name
- [ ] If AWS SNS: IAM user with `sns:Publish` permission in correct region

---
