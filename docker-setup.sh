#!/bin/bash

echo "🚀 Setting up API Gateway with Docker..."

# Copy .env file if it doesn't exist
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
fi

# Build and start containers
echo "🐳 Building Docker containers..."
docker-compose up -d --build

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
sleep 10

# Install dependencies
echo "📦 Installing Composer dependencies..."
docker-compose exec app composer install

# Generate application key
echo "🔑 Generating application key..."
docker-compose exec app php artisan key:generate

# Run migrations
echo "🗄️  Running database migrations..."
docker-compose exec app php artisan migrate --force

# Create admin user
echo "👤 Creating admin user..."
docker-compose exec app php artisan tinker --execute="User::create(['name' => 'Admin User', 'email' => 'admin@example.com', 'password' => bcrypt('password123'), 'role' => 'admin']);"

echo "✅ Setup complete!"
echo ""
echo "🌐 API Gateway is running at: http://localhost:8000"
echo "📊 MySQL is running at: localhost:3307"
echo ""
echo "📝 Test credentials:"
echo "   Email: admin@example.com"
echo "   Password: password123"
echo ""
echo "🛠️  Useful commands:"
echo "   docker-compose logs -f        # View logs"
echo "   docker-compose down           # Stop containers"
echo "   docker-compose exec app bash  # Access container shell"
