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
│   │   ├── PurgeExpiredTokens.php     # php artisan tokens:purge
│   │   └── PurgeExpiredOtps.php       # php artisan otps:purge
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php     # V1 authentication
│   │   │   ├── AdminController.php    # role management
│   │   │   ├── GatewayController.php  # service proxy
│   │   │   └── V2/
│   │   │       └── AuthController.php # V2 phone/OTP
│   │   ├── Middleware/
│   │   │   ├── JwtMiddleware.php      # token validation + blacklist check
│   │   │   ├── AdminMiddleware.php    # role === 'admin' check
│   │   │   └── LogRequestMiddleware.php # stdout JSON logging
│   │   └── Requests/
│   │       ├── LoginRequest.php
│   │       ├── RegisterRequest.php
│   │       ├── UpdateRoleRequest.php
│   │       └── V2/
│   │           ├── SendOtpRequest.php
│   │           └── VerifyOtpRequest.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── TokenBlacklist.php
│   │   └── PhoneOtp.php
│   ├── Providers/
│   │   └── AppServiceProvider.php     # rate limiter definitions
│   └── Services/
│       ├── JWTService.php             # token generation + validation
│       ├── TokenBlacklistService.php  # blacklist operations
│       ├── ServiceProxyService.php    # HTTP forwarding to microservices
│       ├── OtpService.php             # OTP generation/verification
│       └── SmsService.php             # SMS (log/twilio/vonage/sns)
├── config/
│   ├── cors.php                       # CORS settings
│   ├── gateway.php                    # service registry
│   ├── jwt.php                        # JWT settings
│   ├── logging.php                    # stdout channel
│   └── sms.php                        # SMS driver config
├── database/migrations/
│   ├── *_create_users_table.php
│   ├── *_add_role_to_users_table.php
│   ├── *_create_token_blacklist_table.php
│   ├── *_add_phone_to_users_table.php
│   ├── *_create_phone_otps_table.php
│   └── *_make_email_password_nullable_on_users_table.php
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

### V2 Phone/OTP Endpoints

#### POST /api/v2/auth/register
Rate limited: 3 requests/hour per IP.

**Request:**
```json
{
  "phone": "+1234567890",
  "name": "John Doe"
}
```

**Response `200`:**
```json
{
  "success": true,
  "message": "OTP sent. Please verify your phone number."
}
```

---

#### POST /api/v2/auth/register/verify

**Request:**
```json
{
  "phone": "+1234567890",
  "otp": "123456",
  "name": "John Doe"
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
      "phone": "+1234567890",
      "name": "John Doe",
      "role": "user"
    }
  }
}
```

---

#### POST /api/v2/auth/login
Rate limited: 3 requests/hour per IP.

**Request:**
```json
{
  "phone": "+1234567890"
}
```

**Response `200`:**
```json
{
  "success": true,
  "message": "If this number is registered, an OTP has been sent."
}
```

---

#### POST /api/v2/auth/login/verify

**Request:**
```json
{
  "phone": "+1234567890",
  "otp": "123456"
}
```

**Response `200`:** Same structure as V1 login, with `phone` instead of `email` in user data.

**Note:** Token refresh and logout use the shared V1 endpoints.

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
APP_KEY=<run: php artisan key:generate --show>
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

# SMS (V2 Phone/OTP)
# Driver: log (development) | twilio | vonage | aws_sns
SMS_DRIVER=log

# Twilio (SMS_DRIVER=twilio)
# TWILIO_SID=
# TWILIO_TOKEN=
# TWILIO_FROM=+1234567890

# Vonage (SMS_DRIVER=vonage)
# VONAGE_KEY=
# VONAGE_SECRET=
# VONAGE_FROM=APIGateway

# AWS SNS uses existing AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION
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

### Kubernetes

The following manifests cover a complete production-ready deployment. Adjust namespace, image, and resource values to match your environment.

#### Namespace
```yaml
apiVersion: v1
kind: Namespace
metadata:
  name: api-gateway
```

---

#### Secret — sensitive environment variables
Store JWT secret, DB password, and app key as a Kubernetes Secret. Never put these in ConfigMap.

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: api-gateway-secret
  namespace: api-gateway
type: Opaque
stringData:
  APP_KEY: "base64:your-app-key-here"
  JWT_SECRET: "your-strong-random-secret-min-32-chars"
  DB_PASSWORD: "your-db-password"
```

---

#### ConfigMap — non-sensitive environment variables
```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: api-gateway-config
  namespace: api-gateway
data:
  APP_NAME: "API Gateway"
  APP_ENV: "production"
  APP_DEBUG: "false"
  APP_URL: "https://api.example.com"
  DB_CONNECTION: "mysql"
  DB_HOST: "mysql-service"
  DB_PORT: "3306"
  DB_DATABASE: "api_gateway"
  DB_USERNAME: "api_gateway_user"
  JWT_TTL: "60"
  JWT_REFRESH_TTL: "20160"
  CORS_ALLOWED_ORIGINS: "https://app.example.com,https://admin.example.com"
  GATEWAY_TIMEOUT: "10"
  SERVICE_ORDERS_URL: "http://order-service.services.svc.cluster.local:3000"
  SERVICE_PRODUCTS_URL: "http://product-service.services.svc.cluster.local:3001"
  LOG_CHANNEL: "stdout"
  CACHE_STORE: "database"
  SESSION_DRIVER: "database"
  QUEUE_CONNECTION: "database"
```

---

#### Deployment
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: api-gateway
  namespace: api-gateway
  labels:
    app: api-gateway
spec:
  replicas: 3
  selector:
    matchLabels:
      app: api-gateway
  template:
    metadata:
      labels:
        app: api-gateway
    spec:
      containers:
        - name: api-gateway
          image: your-registry/api-gateway:latest
          ports:
            - containerPort: 80

          # Load all env vars from ConfigMap and Secret
          envFrom:
            - configMapRef:
                name: api-gateway-config
            - secretRef:
                name: api-gateway-secret

          # Resource limits — adjust based on load testing
          resources:
            requests:
              cpu: "100m"
              memory: "128Mi"
            limits:
              cpu: "500m"
              memory: "256Mi"

          # Health checks
          livenessProbe:
            httpGet:
              path: /up
              port: 80
            initialDelaySeconds: 30
            periodSeconds: 10
            failureThreshold: 3

          readinessProbe:
            httpGet:
              path: /up
              port: 80
            initialDelaySeconds: 10
            periodSeconds: 5
            failureThreshold: 3

          # Stdout logs captured automatically by Kubernetes
          # No log volume needed
```

---

#### Service — expose the deployment internally
```yaml
apiVersion: v1
kind: Service
metadata:
  name: api-gateway-service
  namespace: api-gateway
spec:
  selector:
    app: api-gateway
  ports:
    - protocol: TCP
      port: 80
      targetPort: 80
  type: ClusterIP
```

---

#### Ingress — expose to the internet
Requires an ingress controller (nginx-ingress, Traefik, AWS ALB, etc.).

```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: api-gateway-ingress
  namespace: api-gateway
  annotations:
    nginx.ingress.kubernetes.io/proxy-body-size: "10m"
    nginx.ingress.kubernetes.io/proxy-read-timeout: "30"
    cert-manager.io/cluster-issuer: "letsencrypt-prod"  # TLS via cert-manager
spec:
  ingressClassName: nginx
  tls:
    - hosts:
        - api.example.com
      secretName: api-gateway-tls
  rules:
    - host: api.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: api-gateway-service
                port:
                  number: 80
```

---

#### HorizontalPodAutoscaler — auto-scale on CPU
```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: api-gateway-hpa
  namespace: api-gateway
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: api-gateway
  minReplicas: 2
  maxReplicas: 10
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
```

---

#### CronJob — daily token blacklist purge
Replaces the server cron. Runs `php artisan tokens:purge` daily at 2am.

```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: purge-expired-tokens
  namespace: api-gateway
spec:
  schedule: "0 2 * * *"  # daily at 2am UTC
  jobTemplate:
    spec:
      template:
        spec:
          restartPolicy: OnFailure
          containers:
            - name: purge
              image: your-registry/api-gateway:latest
              command: ["php", "artisan", "tokens:purge"]
              envFrom:
                - configMapRef:
                    name: api-gateway-config
                - secretRef:
                    name: api-gateway-secret
```

---

#### Fluent Bit sidecar — forward logs to Loki/CloudWatch
Add this sidecar container to the Deployment spec to ship stdout logs.

```yaml
        - name: fluent-bit
          image: fluent/fluent-bit:latest
          volumeMounts:
            - name: fluent-bit-config
              mountPath: /fluent-bit/etc/
      volumes:
        - name: fluent-bit-config
          configMap:
            name: fluent-bit-config
```

Fluent Bit ConfigMap:
```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: fluent-bit-config
  namespace: api-gateway
data:
  fluent-bit.conf: |
    [INPUT]
        Name              tail
        Path              /var/log/containers/api-gateway*.log
        Parser            docker
        Tag               api-gateway.*

    [FILTER]
        Name              parser
        Match             api-gateway.*
        Key_Name          log
        Parser            json

    [OUTPUT]
        Name              loki
        Match             *
        Host              loki.monitoring.svc.cluster.local
        Port              3100
        Labels            app=api-gateway
```

---

#### Apply all manifests
```bash
# Apply in order
kubectl apply -f namespace.yaml
kubectl apply -f secret.yaml
kubectl apply -f configmap.yaml
kubectl apply -f deployment.yaml
kubectl apply -f service.yaml
kubectl apply -f ingress.yaml
kubectl apply -f hpa.yaml
kubectl apply -f cronjob.yaml

# Run migrations (one-time job)
kubectl run migrations \
  --image=your-registry/api-gateway:latest \
  --restart=Never \
  --namespace=api-gateway \
  --env-from=configmap/api-gateway-config \
  --env-from=secret/api-gateway-secret \
  -- php artisan migrate --force

# Check deployment status
kubectl rollout status deployment/api-gateway -n api-gateway

# View logs
kubectl logs -f deployment/api-gateway -n api-gateway
```

---

#### Key notes
- Microservice URLs use Kubernetes internal DNS: `http://{service}.{namespace}.svc.cluster.local:{port}`
- Secrets are base64-encoded by Kubernetes — never commit them to git
- The `/up` health endpoint is built into Laravel 12 — no extra code needed
- HPA requires the metrics-server to be installed in the cluster
- TLS is handled by the Ingress + cert-manager — the app itself runs on HTTP internally

### Production checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `JWT_SECRET` set to a strong random string (min 32 chars)
- [ ] `CORS_ALLOWED_ORIGINS` restricted to your frontend domains
- [ ] Database credentials set
- [ ] Laravel scheduler cron configured (`tokens:purge` and `otps:purge` run daily)
- [ ] `SMS_DRIVER` set to real provider if using V2 (`twilio`, `vonage`, or `aws_sns`)
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

# Purge expired OTPs manually
php artisan otps:purge

# Run migrations
php artisan migrate

# Check scheduled commands
php artisan schedule:list
```

---

*Built with Laravel 12 — Custom JWT, no external auth packages.*
