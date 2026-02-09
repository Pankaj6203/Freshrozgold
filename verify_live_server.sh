#!/bin/bash

# Live Server Verification Script
# Run this on your LIVE server after uploading files

echo "=== Rozgold Live Server Verification ==="
echo "Server: $(hostname)"
echo "Date: $(date)"
echo ""

# Check if files exist
echo "1. Checking Controller Files:"
files=(
    "app/Http/Controllers/Api/PaymentController.php"
    "app/Http/Controllers/Api/KycController.php"
    "app/Http/Controllers/Api/OtpController.php"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✅ $file - EXISTS"
    else
        echo "   ❌ $file - MISSING"
    fi
done

echo ""
echo "2. Checking Config Files:"
config_files=(
    "config/services.php"
    "routes/api.php"
    "app/Models/PaymentOrder.php"
)

for file in "${config_files[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✅ $file - EXISTS"
    else
        echo "   ❌ $file - MISSING"
    fi
done

echo ""
echo "3. Laravel Commands:"
echo "   Running: composer dump-autoload"
composer dump-autoload 2>/dev/null && echo "   ✅ Composer autoload updated" || echo "   ❌ Composer failed"

echo "   Running: php artisan config:clear"
php artisan config:clear 2>/dev/null && echo "   ✅ Config cache cleared" || echo "   ❌ Config clear failed"

echo "   Running: php artisan cache:clear"
php artisan cache:clear 2>/dev/null && echo "   ✅ Application cache cleared" || echo "   ❌ Cache clear failed"

echo ""
echo "4. Database Migration:"
echo "   Running: php artisan migrate --force"
php artisan migrate --force 2>/dev/null && echo "   ✅ Database migrated" || echo "   ❌ Migration failed"

echo ""
echo "5. API Test:"
echo "   Testing: https://rozgold.techotd.in/api/payment/order-status?order_id=test"
response=$(curl -s -o /dev/null -w "%{http_code}" "https://rozgold.techotd.in/api/payment/order-status?order_id=test" 2>/dev/null)

if [ "$response" = "200" ]; then
    echo "   ✅ API responding (HTTP 200)"
elif [ "$response" = "500" ]; then
    echo "   ❌ API error (HTTP 500) - Check controller files"
else
    echo "   ⚠️  API status: HTTP $response"
fi

echo ""
echo "=== VERIFICATION COMPLETE ==="
echo ""
echo "If all checks are ✅, your API is ready!"
echo "If any ❌, re-upload the missing files."