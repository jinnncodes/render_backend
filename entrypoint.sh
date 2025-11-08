#!/bin/bash

# Run migrations safely
php artisan migrate --force

# Run seeders (optional)
php artisan db:seed --force

# Start Laravel server
php artisan serve --host=0.0.0.0 --port=10000
