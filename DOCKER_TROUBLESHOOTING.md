# Docker Troubleshooting Process - Learning Guide

## Problem Timeline & Solutions

### Initial Problem: Containers Not Starting

**Symptom:**
```bash
curl -X POST http://localhost:8000/api/auth/login
# Error: Failed to connect to localhost port 8000
```

**Diagnosis:**
```bash
docker ps -a  # No containers running
```

---

## Root Cause Analysis

### Problem 1: PHP Version Mismatch

**The Issue:**
- **Local Machine**: PHP 8.5 (newer)
- **Docker Container**: PHP 8.2 (in Dockerfile)
- **Config File**: Used PHP 8.5 syntax `\Pdo\Mysql::ATTR_SSL_CA`

**Why It Failed:**
```php
// In config/database.php
'options' => extension_loaded('pdo_mysql') ? array_filter([
    \Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),  // ❌ PHP 8.5 only
]) : [],
```

**Error Message:**
```
In database.php line 64:
  Class "Pdo\Mysql" not found
```

**Explanation:**
- `\Pdo\Mysql` class was introduced in PHP 8.5
- PHP 8.2 doesn't have this class
- Docker build failed during `composer install` when Laravel tried to load config

---

## Solution Steps (In Order)

### Step 1: Fixed Local Config File
**File:** `config/database.php`

**Changed:**
```php
// Before (PHP 8.5 syntax)
'options' => extension_loaded('pdo_mysql') ? array_filter([
    \Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
]) : [],

// After (Compatible with both)
'options' => [],
```

**Why:** Removed the problematic SSL CA option (not needed for local dev)

---

### Step 2: Removed Obsolete docker-compose Version

**File:** `docker-compose.yml`

**Changed:**
```yaml
# Before
version: '3.8'
services:
  ...

# After
services:
  ...
```

**Why:** Docker Compose v2 doesn't need version attribute (causes warnings)

---

### Step 3: Built Docker Containers

**Command:**
```bash
docker-compose up -d --build
```

**Result:** ✅ Containers built successfully

---

### Step 4: Fixed Vendor File Inside Container

**Problem:** Laravel's vendor file still had PHP 8.5 syntax

**Command:**
```bash
docker-compose exec app sed -i \
  's/\\Pdo\\Mysql::ATTR_SSL_CA/PDO::MYSQL_ATTR_SSL_CA/g' \
  /var/www/html/vendor/laravel/framework/config/database.php
```

**Explanation:**
- `sed -i` = Edit file in place
- `s/OLD/NEW/g` = Replace OLD with NEW globally
- Changed PHP 8.5 syntax back to PHP 8.2 compatible syntax

---

### Step 5: Ran Database Migrations

**Command:**
```bash
docker-compose exec app php artisan migrate --force
```

**Result:** ✅ Database tables created

---

### Step 6: Created Admin User

**Command:**
```bash
docker-compose exec app php artisan tinker --execute=\
  "User::create(['name' => 'Admin User', 'email' => 'admin@example.com', 'password' => bcrypt('password123'), 'role' => 'admin']);"
```

**Result:** ✅ Admin user created

---

### Step 7: Tested API

**Command:**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}'
```

**Result:** ✅ Success!
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "name": "Admin User",
      "email": "admin@example.com",
      "role": "admin"
    }
  }
}
```

---

## Key Lessons Learned

### 1. PHP Version Compatibility

**Problem:** Different PHP versions have different features

**Solution:** 
- Use syntax compatible with your target PHP version
- Or use version-specific conditionals:
```php
if (PHP_VERSION_ID >= 80500) {
    // PHP 8.5+ code
} else {
    // PHP 8.2 code
}
```

### 2. Docker vs Local Environment

**Problem:** Local and Docker environments can differ

**Best Practice:**
- Match PHP versions between local and Docker
- Or write code compatible with both
- Test in Docker before deploying

### 3. Vendor Files Can Cause Issues

**Problem:** Vendor files (from packages) can have incompatible code

**Solutions:**
- Fix inside container (temporary)
- Report to package maintainer
- Wait for package update
- Use older package version

### 4. Two Places to Check

When you have config issues:
1. **Your config**: `/config/database.php` ✅ You control this
2. **Vendor config**: `/vendor/.../config/database.php` ⚠️ From packages

---

## PHP Version Comparison

| Feature | PHP 8.2 | PHP 8.5 |
|---------|---------|---------|
| `PDO::MYSQL_ATTR_SSL_CA` | ✅ Works | ⚠️ Deprecated |
| `\Pdo\Mysql::ATTR_SSL_CA` | ❌ Not available | ✅ New way |
| Laravel 12 Support | ✅ Fully tested | ⚠️ May have issues |
| Production Ready | ✅ Stable | ✅ Stable (but newer) |

---

## Why We Kept PHP 8.2

**Reasons:**
1. **Laravel 12 Compatibility** - Officially tested with PHP 8.2
2. **Package Compatibility** - All packages work with PHP 8.2
3. **Stability** - PHP 8.2 is battle-tested in production
4. **No Breaking Changes** - Smooth deployment

**When to Upgrade to PHP 8.5:**
- Laravel officially supports it
- All your packages are compatible
- You need PHP 8.5 specific features

---

## Debugging Commands Used

```bash
# Check running containers
docker ps
docker ps -a  # Include stopped containers

# Check container logs
docker-compose logs -f
docker-compose logs app

# Execute commands in container
docker-compose exec app bash
docker-compose exec app php artisan migrate

# Rebuild containers
docker-compose down
docker-compose up -d --build

# Check build errors
docker-compose build 2>&1 | tail -50

# Pull specific PHP version
docker pull php:8.5-fpm
docker pull php:8.2-fpm
```

---

## Final Working Setup

**Dockerfile:** PHP 8.2-FPM
**Config:** Compatible syntax (empty options array)
**Result:** ✅ Working API Gateway in Docker

**Access:**
- API: http://localhost:8000
- MySQL: localhost:3307
- Credentials: admin@example.com / password123

---

## Summary

**Problem:** PHP version mismatch between local (8.5) and Docker (8.2)
**Root Cause:** Used PHP 8.5 syntax in config files
**Solution:** Removed incompatible syntax, kept PHP 8.2
**Lesson:** Always match environments or use compatible code

**Time to Fix:** ~15 minutes
**Containers Running:** ✅ 2/2 (app + database)
**API Status:** ✅ Working perfectly
