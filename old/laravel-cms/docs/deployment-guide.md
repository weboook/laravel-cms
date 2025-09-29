# Laravel CMS Deployment Guide

This guide provides comprehensive instructions for deploying Laravel CMS in various environments.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Production Deployment](#production-deployment)
3. [Environment Configuration](#environment-configuration)
4. [Web Server Configuration](#web-server-configuration)
5. [Security Considerations](#security-considerations)
6. [Performance Optimization](#performance-optimization)
7. [Monitoring and Maintenance](#monitoring-and-maintenance)
8. [Troubleshooting](#troubleshooting)

## Prerequisites

### System Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 10.0 or higher
- **Node.js**: 16.0 or higher
- **NPM/Yarn**: Latest stable version
- **Database**: MySQL 8.0+, PostgreSQL 13+, or SQLite 3.8+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: Minimum 512MB RAM (2GB+ recommended)
- **Storage**: Minimum 1GB free space

### Required PHP Extensions

```bash
php -m | grep -E "(gd|imagick|zip|xml|mbstring|json|openssl|pdo|tokenizer|ctype|fileinfo|bcmath)"
```

Required extensions:
- gd or imagick (for image processing)
- zip (for asset compression)
- xml, mbstring, json (for Laravel)
- openssl, pdo, tokenizer, ctype, fileinfo, bcmath

## Production Deployment

### Step 1: Server Setup

#### Ubuntu/Debian
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install PHP and required extensions
sudo apt install php8.1 php8.1-cli php8.1-fpm php8.1-mysql php8.1-xml php8.1-mbstring php8.1-gd php8.1-zip php8.1-curl php8.1-bcmath

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js and NPM
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install nodejs

# Install MySQL/PostgreSQL
sudo apt install mysql-server
# OR
sudo apt install postgresql postgresql-contrib
```

#### CentOS/RHEL
```bash
# Enable EPEL and Remi repositories
sudo yum install epel-release
sudo yum install https://rpms.remirepo.net/enterprise/remi-release-8.rpm

# Install PHP
sudo yum install php81 php81-php-cli php81-php-fpm php81-php-mysql php81-php-xml php81-php-mbstring php81-php-gd php81-php-zip

# Follow similar steps for Composer, Node.js, and database
```

### Step 2: Application Deployment

#### Clone and Setup
```bash
# Clone your application
git clone https://github.com/yourusername/your-laravel-app.git /var/www/html/your-app
cd /var/www/html/your-app

# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install CMS package
composer require webook/laravel-cms

# Install and build frontend assets
npm ci --production
npm run production

# Set proper permissions
sudo chown -R www-data:www-data /var/www/html/your-app
sudo chmod -R 755 /var/www/html/your-app
sudo chmod -R 775 /var/www/html/your-app/storage
sudo chmod -R 775 /var/www/html/your-app/bootstrap/cache
```

#### Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure environment variables (see next section)
nano .env
```

#### Database Setup
```bash
# Run migrations
php artisan migrate --force

# Install CMS
php artisan cms:install --no-npm --no-build

# Seed database (optional)
php artisan db:seed --class=CMSSeeder

# Create storage link
php artisan storage:link
```

### Step 3: Environment Configuration

#### Production .env Configuration
```bash
APP_NAME="Your Laravel CMS App"
APP_ENV=production
APP_KEY=base64:your-generated-app-key
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Cache Configuration
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# CMS Configuration
CMS_ENABLED=true
CMS_AUTH_REQUIRED=true
CMS_AUTH_GUARD=web
CMS_ROUTE_PREFIX=admin
CMS_ASSETS_DISK=s3
CMS_ASSETS_PATH=cms-assets
CMS_UPLOAD_MAX_SIZE=10485760
CMS_GENERATE_THUMBNAILS=true
CMS_IMAGE_QUALITY=85
CMS_DB_DETECTION_ENABLED=true
CMS_DB_AUTO_SAVE=true
CMS_DB_VERSIONING=true
CMS_DB_CACHE_ENABLED=true
CMS_CDN_ENABLED=true
CMS_CDN_URL=https://cdn.yourdomain.com

# AWS S3 Configuration (for assets)
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
AWS_USE_PATH_STYLE_ENDPOINT=false
```

## Web Server Configuration

### Nginx Configuration

#### Main Site Configuration
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/html/your-app/public;

    # SSL Configuration
    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    index index.php;

    charset utf-8;

    # CMS Assets Caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|webp)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Vary Accept-Encoding;
        gzip_static on;
    }

    # CMS Upload Directory
    location ^~ /storage/cms-assets/ {
        expires 1M;
        add_header Cache-Control "public";
        add_header X-Content-Type-Options nosniff;

        # Security for uploads
        location ~* \.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$ {
            deny all;
        }
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;

        # Increase timeouts for large uploads
        fastcgi_read_timeout 300;
        client_max_body_size 10M;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Rate limiting for CMS endpoints
    location /admin {
        limit_req zone=cms burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
}

# Rate limiting configuration (add to http block)
http {
    limit_req_zone $binary_remote_addr zone=cms:10m rate=1r/s;
}
```

### Apache Configuration

#### Virtual Host Configuration
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/html/your-app/public

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/your/certificate.crt
    SSLCertificateKeyFile /path/to/your/private.key
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384

    # Security Headers
    Header always set X-Frame-Options SAMEORIGIN
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"

    # Asset Caching
    <LocationMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg|webp)$">
        ExpiresActive On
        ExpiresDefault "access plus 1 year"
        Header append Cache-Control "public"
    </LocationMatch>

    # CMS Upload Security
    <Directory "/var/www/html/your-app/storage/app/public/cms-assets">
        <FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
            Require all denied
        </FilesMatch>
    </Directory>

    # Rate Limiting (requires mod_qos)
    <Location "/admin">
        # QS_SrvMaxConnClose 3
        # QS_SrvMaxConnPerIP 5
    </Location>

    ErrorLog ${APACHE_LOG_DIR}/your-app_error.log
    CustomLog ${APACHE_LOG_DIR}/your-app_access.log combined
</VirtualHost>
```

## Security Considerations

### 1. File Permissions
```bash
# Set secure file permissions
find /var/www/html/your-app -type f -exec chmod 644 {} \;
find /var/www/html/your-app -type d -exec chmod 755 {} \;
chmod -R 775 /var/www/html/your-app/storage
chmod -R 775 /var/www/html/your-app/bootstrap/cache
```

### 2. Environment Security
```bash
# Secure .env file
chmod 600 /var/www/html/your-app/.env
chown www-data:www-data /var/www/html/your-app/.env
```

### 3. Database Security
```sql
-- Create dedicated CMS database user
CREATE USER 'cms_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON cms_database.* TO 'cms_user'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Firewall Configuration
```bash
# UFW Configuration
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# Fail2ban for additional security
sudo apt install fail2ban
```

### 5. CMS Security Configuration
```php
// config/cms.php - Production Security Settings
return [
    'security' => [
        'csrf_protection' => true,
        'content_security_policy' => true,
        'sanitize_content' => true,
        'rate_limiting' => [
            'enabled' => true,
            'requests_per_minute' => 60,
        ],
    ],
    'uploads' => [
        'scan_for_viruses' => true,
        'check_file_content' => true,
        'block_executable_files' => true,
    ],
];
```

## Performance Optimization

### 1. Laravel Optimizations
```bash
# Cache configuration and routes
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize Composer autoloader
composer install --optimize-autoloader --no-dev

# Cache CMS configurations
php artisan cms:cache-config
```

### 2. Database Optimizations
```sql
-- Add indexes for CMS tables
CREATE INDEX idx_cms_assets_folder_id ON cms_assets(folder_id);
CREATE INDEX idx_cms_assets_mime_type ON cms_assets(mime_type);
CREATE INDEX idx_cms_assets_created_at ON cms_assets(created_at);

-- For content detection
CREATE INDEX idx_posts_status ON posts(status);
CREATE INDEX idx_posts_published_at ON posts(published_at);
```

### 3. Redis Configuration
```bash
# /etc/redis/redis.conf
maxmemory 256mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### 4. PHP-FPM Tuning
```ini
; /etc/php/8.1/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500

; Increase limits for CMS uploads
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 60
memory_limit = 256M
```

## Monitoring and Maintenance

### 1. Log Monitoring
```bash
# Monitor Laravel logs
tail -f /var/www/html/your-app/storage/logs/laravel.log

# Monitor web server logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log
```

### 2. Health Checks
```bash
# Create health check script
#!/bin/bash
# /usr/local/bin/cms-health-check.sh

cd /var/www/html/your-app

# Check if application is responding
curl -f http://localhost/health > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "Application not responding"
    exit 1
fi

# Check database connection
php artisan migrate:status > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "Database connection failed"
    exit 1
fi

# Check storage permissions
if [ ! -w storage/logs ]; then
    echo "Storage not writable"
    exit 1
fi

echo "Health check passed"
```

### 3. Automated Backups
```bash
# Create backup script
#!/bin/bash
# /usr/local/bin/cms-backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/cms"
APP_DIR="/var/www/html/your-app"

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u cms_user -p your_database > $BACKUP_DIR/database_$DATE.sql

# Files backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz -C $APP_DIR storage/app/public/cms-assets

# Cleanup old backups (keep 7 days)
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
```

### 4. Cron Jobs
```bash
# Add to crontab
crontab -e

# Laravel scheduler
* * * * * cd /var/www/html/your-app && php artisan schedule:run >> /dev/null 2>&1

# CMS optimizations
0 2 * * * cd /var/www/html/your-app && php artisan cms:cleanup-temp-files
0 3 * * * cd /var/www/html/your-app && php artisan cms:optimize-images
0 4 * * * /usr/local/bin/cms-backup.sh
```

## Troubleshooting

### Common Issues

#### 1. Permission Issues
```bash
# Fix common permission problems
sudo chown -R www-data:www-data /var/www/html/your-app
sudo chmod -R 755 /var/www/html/your-app
sudo chmod -R 775 /var/www/html/your-app/storage
sudo chmod -R 775 /var/www/html/your-app/bootstrap/cache
```

#### 2. Asset Upload Issues
```bash
# Check PHP upload limits
php -i | grep -E "(upload_max_filesize|post_max_size|max_execution_time)"

# Check disk space
df -h

# Check CMS asset directory permissions
ls -la storage/app/public/cms-assets
```

#### 3. Performance Issues
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm
sudo systemctl restart redis
```

#### 4. Database Connection Issues
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check MySQL service
sudo systemctl status mysql
```

### Debug Mode (Development Only)
```bash
# Enable debug mode temporarily
sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env
php artisan config:clear

# Don't forget to disable it
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env
php artisan config:cache
```

### Log Analysis
```bash
# Find PHP errors
grep -i "fatal\|error" /var/www/html/your-app/storage/logs/laravel.log

# Find CMS specific errors
grep -i "cms" /var/www/html/your-app/storage/logs/laravel.log

# Monitor real-time errors
tail -f /var/www/html/your-app/storage/logs/laravel.log | grep -i error
```

## Deployment Checklist

### Pre-Deployment
- [ ] Server requirements met
- [ ] SSL certificate configured
- [ ] Database created and configured
- [ ] Environment variables set
- [ ] Backup strategy implemented

### Deployment
- [ ] Code deployed and dependencies installed
- [ ] Database migrations run
- [ ] CMS installed and configured
- [ ] Assets built and optimized
- [ ] File permissions set correctly

### Post-Deployment
- [ ] Health checks passing
- [ ] Performance optimization applied
- [ ] Monitoring configured
- [ ] Security hardening completed
- [ ] Backup tested and working

### Go-Live
- [ ] DNS updated
- [ ] SSL verified
- [ ] All functionality tested
- [ ] Error monitoring active
- [ ] Support team notified

---

This deployment guide provides a comprehensive approach to deploying Laravel CMS in production. Always test deployments in a staging environment before going live, and ensure you have proper backup and rollback procedures in place.