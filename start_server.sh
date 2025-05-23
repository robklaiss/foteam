#!/bin/bash

# Kill any existing PHP server on port 8000
pkill -f "php -S localhost:8000"

# Wait a moment for the server to shut down
sleep 1

# Start a new PHP server with custom configuration
echo "Starting PHP server with unlimited upload configuration..."
php -S localhost:8000 -c php-custom.ini

# This script will keep running until you press Ctrl+C
