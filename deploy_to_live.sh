#!/bin/bash

# Rozgold API Deployment Script
# Run this on your LOCAL machine to prepare files for upload

echo "=== Rozgold API Deployment Preparation ==="
echo ""

# Create deployment directory
DEPLOY_DIR="rozgold_deployment_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$DEPLOY_DIR"

echo "Creating deployment package in: $DEPLOY_DIR"
echo ""

# Copy all necessary files
echo "Copying controller files..."
mkdir -p "$DEPLOY_DIR/app/Http/Controllers/Api"
cp "app/Http/Controllers/Api/PaymentController.php" "$DEPLOY_DIR/app/Http/Controllers/Api/"
cp "app/Http/Controllers/Api/KycController.php" "$DEPLOY_DIR/app/Http/Controllers/Api/"
cp "app/Http/Controllers/Api/OtpController.php" "$DEPLOY_DIR/app/Http/Controllers/Api/"
cp "app/Http/Controllers/Api/GoldController.php" "$DEPLOY_DIR/app/Http/Controllers/Api/"
cp "app/Http/Controllers/Api/UserController.php" "$DEPLOY_DIR/app/Http/Controllers/Api/"

echo "Copying request files..."
mkdir -p "$DEPLOY_DIR/app/Http/Requests"
cp "app/Http/Requests/SellGoldRequest.php" "$DEPLOY_DIR/app/Http/Requests/"

echo "Copying config files..."
mkdir -p "$DEPLOY_DIR/config"
cp "config/services.php" "$DEPLOY_DIR/config/"

echo "Copying routes..."
mkdir -p "$DEPLOY_DIR/routes"
cp "routes/api.php" "$DEPLOY_DIR/routes/"

echo "Copying models..."
mkdir -p "$DEPLOY_DIR/app/Models"
cp "app/Models/PaymentOrder.php" "$DEPLOY_DIR/app/Models/"

echo "Copying migrations..."
mkdir -p "$DEPLOY_DIR/database/migrations"
cp "database/migrations/2025_12_28_191818_create_payment_orders_table.php" "$DEPLOY_DIR/database/migrations/"

echo "Copying .env template..."
cp ".env" "$DEPLOY_DIR/.env.production"

echo ""
echo "=== DEPLOYMENT FILES READY ==="
echo ""
echo "Files prepared in: $DEPLOY_DIR"
echo ""
echo "Directory structure:"
find "$DEPLOY_DIR" -type f | sort
echo ""
echo "=== UPLOAD INSTRUCTIONS ==="
echo ""
echo "1. Upload all files from '$DEPLOY_DIR' to your live server"
echo "2. Maintain the same directory structure on live server"
echo "3. Update .env.production with your live API keys"
echo "4. Run these commands on live server:"
echo ""
echo "   cd /home/techotdc/rozgold.techotd.in/"
echo "   composer dump-autoload"
echo "   php artisan config:clear"
echo "   php artisan cache:clear"
echo "   php artisan migrate"
echo ""
echo "5. Test the API:"
echo "   curl -X GET 'https://rozgold.techotd.in/api/payment/order-status?order_id=test'"
echo ""
echo "=== IMPORTANT NOTES ==="
echo "- Make sure .env file has correct database credentials"
echo "- Update API keys in .env for production"
echo "- Check file permissions: chmod -R 755 ."
echo "- Check file ownership: chown -R www-data:www-data ."