# API Gateway — Technical Documentation

A complete technical reference for the Laravel 12 API Gateway with JWT authentication, token blacklist, rate limiting, request logging, and service proxy.

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Project Structure](#2-project-structure)
3. [Authentication Flow](#3-authentication-flow)
4. [JWT Token](#4-jwt-token)
5. [API Reference](#5-api-reference)
6. [Middleware Pipeline](#6-middleware-pipeline)
7. [Service Proxy](#7-service-proxy)
8. [Rate Limiting](#8-rate-limiting)
9. [Request Logging](#9-request-logging)
10. [Token Blacklist](#10-token-blacklist)
11. [Configuration Reference](#11-configuration-reference)
12. [Environment Variables](#12-environment-variables)
13. [Error Responses](#13-error-responses)
14. [Observability](#14-observability)
15. [Deployment](#15-deployment)

---

## 1. Architecture Overview

```
Client
  │
  ▼
API Gateway (Laravel 12)
  ├── Rate Limiting        — throttle abusive clients
  ├── Request Logging      — structured JSON to stdout
  ├── JWT Validation       — verify token signature + expiry
  ├── Blacklist Check      — reject logged-out tokens
  ├── User Context         — attach email/name/role to request
  │
  ├── Auth Routes          — login, register, refresh, logout
  ├── Admin Routes         — role management (admin only)
  └── Service Proxy        — forward to microservices
        │
        ├── X-User-Email
        ├── X-User-Name
        └── X-User-Role
              │
              ▼
        Microservices (orders, products, users, ...)
```

The gateway is the **single entry point** for all clients. Microservices never receive JWT tokens — they receive trusted user context headers instead.

---

## 2. Project Structure

```
api-gateway/
├── app/
│   ├── Console/Commands/
│   │   └── PurgeExpiredTokens.php     # php artisan tokens:purge
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php     # login, register, refresh, logout
│   │   │   ├── AdminController.php    # role management
│   │   │   └── GatewayController.php  # service proxy
│   │   ├── Middleware/
│   │   │   ├── JwtMiddleware.php      # token validation + blacklist check
│   │   │   ├── AdminMiddleware.php    # role === 'admin' check
│   │   │   └── LogRequestMiddleware.php # stdout JSON logging
│   │   └── Requests/
│   │       ├── LoginRequest.php
│   │       ├── RegisterRequest.php
│   │       └── UpdateRoleRequest.php
│   ├── Models/
│   │   ├── User.php
│   │   └── TokenBlacklist.php
│   ├── Providers/
│   │   └── AppServiceProvider.php     # rate limiter definitions
│   └── Services/
│       ├── JWTService.php             # token generation + validation
│       ├── TokenBlacklistService.php  # blacklist operations
│       └── ServiceProxyService.php    # HTTP forwarding to microservices
├── config/
│   ├── cors.php                       # CORS settings
│   ├── gateway.php                    # service registry
│   ├── jwt.php                        # JWT settings
│   └── logging.php                    # stdout channel
├── database/migrations/
│   ├── *_create_users_table.php
│   ├── *_add_role_to_users_table.php
│   └── *_create_token_blacklist_table.php
└── routes/
    ├── api.php                        # all API routes
    └── console.php                    # scheduled commands
```

---

## 3. Authentication Flow

### Login
```
POST /api/auth/login
  │
  ├── LoginRequest validates email + password format
  ├── Find user by email
  ├── Hash::check() verifies password
  ├── JWTService::generateToken() creates signed token
  └── Return token + user data
```

### Register
```
POST /api/auth/register
  │
  ├── RegisterRequest validates name, email (unique), password (confirmed)
  ├── User::create() with role hardcoded to 'user'
  ├── JWTService::generateToken() creates signed token
  └── Return token + user data (201)
```

### Authenticated Request
```
GET /api/profile
Authorization: Bearer <token>
  │
  ├── JwtMiddleware extracts bearer token
  ├── JWTService::validateToken() checks signature + expiry
  ├── TokenBlacklistService::isBlacklisted() checks revocation
  ├── Merge user_email, user_name, user_role into request
  └── Controller reads $request->input('user_email') etc.
```

### Logout
```
POST /api/auth/logout
Authorization: Bearer <token>
  │
  ├── JwtMiddleware validates token (must be valid to logout)
  ├── TokenBlacklistService::blacklist() stores token + expires_at
  └── Return success (token is now rejected on all future requests)
```

### Token Refresh
```
POST /api/auth/refresh
Authorization: Bearer <token>  (can be expired)
  │
  ├── No JWT middleware (token may be expired)
  ├── JWTService::decodeToken() decodes without expiry check
  ├── JWTService::verifySignature() checks HMAC-SHA256 signature
  ├── Check token expired less than 14 days ago (refresh window)
  ├── JWTService::generateToken() issues new token
  └── Return new token
```

---

## 4. JWT Token

### Structure
```
header.payload.signature
```

### Header
```json
{
  "typ": "JWT",
  "alg": "HS256"
}
```

### Payload
```json
{
  "email": "john@example.com",
  "name": "John Doe",
  "role": "user",
  "iat": 1234567890,
  "exp": 1234571490
}
```

**Note:** User ID is deliberately excluded to prevent enumeration attacks.

### Signature
```
HMAC-SHA256(base64url(header) + "." + base64url(payload), JWT_SECRET)
```

### Lifetime
| Setting | Default | Config |
|---|---|---|
| Token TTL | 60 minutes | `JWT_TTL` |
| Refresh window | 14 days | `JWT_REFRESH_TTL` |

---

## 5. API Reference

### Public Endpoints

#### POST /api/auth/register
Rate limited: 10 requests/hour per IP.

**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response `201`:**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1Qi...",
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

---

#### POST /api/auth/login
Rate limited: 5 requests/minute per IP.

**Request:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response `200`:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1Qi...",
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

---

#### POST /api/auth/refresh
No rate limit. Token may be expired.

**Headers:**
```
Authorization: Bearer <expired_or_valid_token>
```

**Response `200`:**
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "token": "eyJ0eXAiOiJKV1Qi...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

---

### Protected Endpoints (JWT required)

#### GET /api/profile

**Headers:**
```
Authorization: Bearer <token>
```

**Response `200`:**
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

---

#### POST /api/auth/logout

**Headers:**
```
Authorization: Bearer <token>
```

**Response `200`:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### Admin Endpoints (JWT + admin role required)

#### PUT /api/admin/users/role

**Headers:**
```
Authorization: Bearer <admin_token>
```

**Request:**
```json
{
  "email": "user@example.com",
  "role": "admin"
}
```

**Response `200`:**
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

---

### Service Proxy

#### ANY /api/services/{service}/{path}
JWT required. Rate limited: 60 requests/minute per IP.

**Headers:**
```
Authorization: Bearer <token>
```

**Example:**
```bash
GET /api/services/orders/123
# Forwards to: SERVICE_ORDERS_URL/123
# With headers: X-User-Email, X-User-Name, X-User-Role
```

---

## 6. Middleware Pipeline

Every API request passes through this pipeline in order:

```
1. HandleCors              — adds CORS headers
2. LogRequestMiddleware    — logs request to stdout (after response)
3. throttle:login|register|api  — rate limiting (route-specific)
4. JwtMiddleware           — token validation + blacklist check
5. AdminMiddleware         — role check (admin routes only)
6. Controller              — business logic
```

### JwtMiddleware detail
```
Extract bearer token
  → null? return 401 "Token not provided"
Validate token (signature + expiry)
  → invalid? return 401 "Invalid or expired token"
Check blacklist
  → blacklisted? return 401 "Token has been revoked"
Merge user_email, user_name, user_role into request
  → pass to next middleware
```

### AdminMiddleware detail
```
Read user_role from request (set by JwtMiddleware)
  → not 'admin'? return 403 "Admin access required"
  → pass to next middleware
```

---

## 7. Service Proxy

### How it works

```
Client request
  → GatewayController::proxy($service, $path)
  → Look up service in config/gateway.php
  → Not found? return 404
  → ServiceProxyService::forward()
      → Build target URL: SERVICE_URL/path?query
      → Add user context headers
      → HTTP::send(method, url, body)
      → Connection failed? return 502
      → Return microservice response as-is
```

### Service Registry

Services are auto-discovered from environment variables. Any `SERVICE_*_URL` variable is automatically registered:

```env
SERVICE_ORDERS_URL=http://order-service:3000
# registers 'orders' → /api/services/orders/*

SERVICE_PAYMENTS_URL=http://payment-service:3003
# registers 'payments' → /api/services/payments/*
```

No code changes needed to add a new microservice.

### Headers forwarded to microservices

| Header | Value | Source |
|---|---|---|
| `X-User-Email` | john@example.com | JWT payload |
| `X-User-Name` | John Doe | JWT payload |
| `X-User-Role` | user | JWT payload |
| `Accept` | application/json | Gateway |

The JWT token itself is **never forwarded** to microservices.

### Error responses

| Situation | Status | Message |
|---|---|---|
| Service not in registry | 404 | Service '{name}' not found |
| Microservice unreachable | 502 | Service unavailable |
| Timeout (default 10s) | 504 | Gateway timeout |

---

## 8. Rate Limiting

Defined in `AppServiceProvider::boot()` using Laravel's `RateLimiter` facade.

| Limiter | Route | Limit | Key |
|---|---|---|---|
| `login` | POST /api/auth/login | 5/minute | IP |
| `register` | POST /api/auth/register | 10/hour | IP |
| `api` | All JWT-protected routes | 60/minute | IP |

**Response when exceeded `429`:**
```json
{
  "success": false,
  "message": "Too many requests. Please slow down."
}
```

---

## 9. Request Logging

Every API request is logged as a single JSON line to stdout.

### Log format
```json
{
  "message": "api_request",
  "context": {
    "timestamp": "2024-01-15T10:30:00+00:00",
    "method": "POST",
    "url": "api/auth/login",
    "status": 200,
    "duration_ms": 45,
    "ip": "192.168.1.1",
    "user": "john@example.com"
  },
  "level": 200,
  "level_name": "INFO",
  "channel": "stdout"
}
```

### What is NOT logged (security)
- Request body (may contain passwords)
- Request headers (may contain tokens)
- Response body (may contain sensitive data)

### Cloud integration
Stdout is captured automatically by Docker and forwarded to:
- **AWS ECS** → CloudWatch Logs (via `awslogs` log driver)
- **Kubernetes** → Fluent Bit / Loki
- **Any platform** → Vector, Datadog, ELK

---

## 10. Token Blacklist

### Database table: `token_blacklist`

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `token` | varchar(500) | Full JWT string (unique) |
| `expires_at` | timestamp | Copied from token `exp` claim |
| `created_at` | timestamp | When blacklisted |

### How it works
1. On logout, token is inserted with its `expires_at`
2. On every authenticated request, `JwtMiddleware` checks the blacklist **after** signature validation (no wasted DB queries on invalid tokens)
3. Daily cleanup via `php artisan tokens:purge` removes expired entries

### Scheduled cleanup
Registered in `routes/console.php`:
```php
Schedule::command('tokens:purge')->daily();
```

Requires the Laravel scheduler cron on the server:
```bash
* * * * * cd /var/www/api-gateway && php artisan schedule:run >> /dev/null 2>&1
```

---

## 11. Configuration Reference

### config/jwt.php
| Key | Default | Description |
|---|---|---|
| `secret` | `JWT_SECRET` | HMAC signing key |
| `ttl` | 60 | Token lifetime in minutes |
| `refresh_ttl` | 20160 | Refresh window in minutes (14 days) |
| `algo` | HS256 | Signing algorithm |

### config/gateway.php
| Key | Default | Description |
|---|---|---|
| `services` | auto-discovered | Map of service name → URL |
| `timeout` | 10 | Seconds before gateway timeout |

### config/cors.php
| Key | Default | Description |
|---|---|---|
| `paths` | `['api/*']` | Routes CORS applies to |
| `allowed_origins` | `CORS_ALLOWED_ORIGINS` | Allowed origins |
| `allowed_methods` | GET, POST, PUT, DELETE, OPTIONS | Allowed HTTP methods |
| `allowed_headers` | Content-Type, Authorization, X-Requested-With | Allowed headers |
| `supports_credentials` | false | Cookie/credential support |

---

## 12. Environment Variables

```env
# Application
APP_NAME="API Gateway"
APP_ENV=production
APP_KEY=                        # php artisan key:generate
APP_DEBUG=false
APP_URL=https://api.example.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=api_gateway
DB_USERNAME=root
DB_PASSWORD=

# JWT
JWT_SECRET=                     # strong random string, min 32 chars
JWT_TTL=60                      # token lifetime in minutes
JWT_REFRESH_TTL=20160           # refresh window in minutes (14 days)

# CORS
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com

# Service Proxy
# Any SERVICE_*_URL is auto-registered as a service
SERVICE_ORDERS_URL=http://order-service:3000
SERVICE_PRODUCTS_URL=http://product-service:3001
SERVICE_USERS_URL=http://user-service:3002
GATEWAY_TIMEOUT=10
```

---

## 13. Error Responses

All error responses follow the same structure:

```json
{
  "success": false,
  "message": "Human readable message"
}
```

### HTTP Status Codes

| Code | Meaning | When |
|---|---|---|
| 400 | Bad Request | Validation failed |
| 401 | Unauthorized | Missing/invalid/expired/revoked token |
| 403 | Forbidden | Valid token but insufficient role |
| 404 | Not Found | Service not in registry |
| 422 | Unprocessable | Validation errors with details |
| 429 | Too Many Requests | Rate limit exceeded |
| 502 | Bad Gateway | Microservice unreachable |
| 504 | Gateway Timeout | Microservice too slow |

### Validation error format
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 6 characters."]
  }
}
```

---

## 14. Observability

### Logs (implemented)
Structured JSON logs are written to stdout on every request. See [Request Logging](#9-request-logging).

### Metrics (infrastructure level)

**On AWS:**
```
stdout → CloudWatch Logs → Metric Filters → CloudWatch Alarms → Grafana
```

Example CloudWatch metric filter for 5xx errors:
```
{ $.status >= 500 }
```

**On Kubernetes:**
```
stdout → Fluent Bit sidecar → Loki (logs) + Prometheus (metrics) → Grafana
```

**On any platform:**
```
stdout → Vector → Prometheus remote_write → Grafana
```

### Useful queries (CloudWatch Insights)
```
# All 5xx errors in last hour
fields @timestamp, method, url, status, ip
| filter status >= 500
| sort @timestamp desc

# Slowest endpoints
fields url, duration_ms
| stats avg(duration_ms) as avg_ms by url
| sort avg_ms desc

# Requests per user
fields user
| filter ispresent(user)
| stats count() as requests by user
| sort requests desc
```

---

## 15. Deployment

### Docker (included)

```bash
# Build and start
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate

# View logs (stdout)
docker-compose logs -f app
```

### AWS ECS

Add to your task definition to send stdout to CloudWatch:
```json
"logConfiguration": {
  "logDriver": "awslogs",
  "options": {
    "awslogs-group": "/api-gateway",
    "awslogs-region": "us-east-1",
    "awslogs-stream-prefix": "api"
  }
}
```

### Production checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `JWT_SECRET` set to a strong random string (min 32 chars)
- [ ] `CORS_ALLOWED_ORIGINS` restricted to your frontend domains
- [ ] Database credentials set
- [ ] Laravel scheduler cron configured (`tokens:purge` runs daily)
- [ ] `php artisan config:cache` run after deployment
- [ ] `php artisan route:cache` run after deployment
- [ ] `php artisan migrate --force` run after deployment

### Useful commands

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Cache for production
php artisan config:cache
php artisan route:cache

# Purge expired blacklisted tokens manually
php artisan tokens:purge

# Run migrations
php artisan migrate

# Check scheduled commands
php artisan schedule:list
```

---

*Built with Laravel 12 — Custom JWT, no external auth packages.*
