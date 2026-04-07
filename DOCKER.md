# Docker Setup for API Gateway

## Quick Start

### 1. Run Setup Script (Recommended)

```bash
./docker-setup.sh
```

This will:
- Create `.env` file
- Build Docker containers
- Install dependencies
- Run migrations
- Create admin user

### 2. Manual Setup

```bash
# Copy environment file
cp .env.example .env

# Build and start containers
docker-compose up -d --build

# Install dependencies
docker-compose exec app composer install

# Generate app key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate

# Create admin user
docker-compose exec app php artisan tinker
User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password123'), 'role' => 'admin']);
```

## Services

- **API Gateway**: http://localhost:8000
- **MySQL**: localhost:3307

## Docker Commands

### Start containers
```bash
docker-compose up -d
```

### Stop containers
```bash
docker-compose down
```

### View logs
```bash
docker-compose logs -f
docker-compose logs -f app  # App logs only
```

### Access container shell
```bash
docker-compose exec app bash
```

### Run artisan commands
```bash
docker-compose exec app php artisan migrate
docker-compose exec app php artisan tinker
```

### Rebuild containers
```bash
docker-compose up -d --build
```

## Database Access

### From Host Machine
```bash
mysql -h 127.0.0.1 -P 3307 -u root -psecret api_gateway
```

### From Container
```bash
docker-compose exec db mysql -u root -psecret api_gateway
```

## Troubleshooting

### Permission Issues
```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chmod -R 755 /var/www/html/storage
```

### Clear Cache
```bash
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
```

### Reset Database
```bash
docker-compose exec app php artisan migrate:fresh
```

## Production Deployment

For production, update:
1. Change `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false`
3. Use strong passwords for database
4. Set proper `JWT_SECRET`
5. Configure proper domain in `APP_URL`
