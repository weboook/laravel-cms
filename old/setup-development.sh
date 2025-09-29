#!/bin/bash

# Laravel Package Development Setup Script
# Sets up local development linking between package and test application

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PACKAGE_DIR="../laravel-cms"
TEST_APP_DIR="./cms-test-app"
PACKAGE_NAME="webook/laravel-cms"

# Helper functions
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_step() {
    echo -e "\n${BLUE}→${NC} $1"
}

# Check if directories exist
check_directories() {
    print_step "Checking directory structure..."

    if [ ! -d "$PACKAGE_DIR" ]; then
        print_error "Package directory '$PACKAGE_DIR' not found!"
        exit 1
    fi
    print_success "Package directory found: $PACKAGE_DIR"

    if [ ! -f "$PACKAGE_DIR/composer.json" ]; then
        print_error "Package composer.json not found in $PACKAGE_DIR"
        exit 1
    fi
    print_success "Package composer.json found"
}

# Create test application if it doesn't exist
setup_test_app() {
    print_step "Setting up test application..."

    if [ ! -d "$TEST_APP_DIR" ]; then
        print_info "Creating new Laravel application: $TEST_APP_DIR"
        composer create-project laravel/laravel "$TEST_APP_DIR" --prefer-dist --no-interaction
        print_success "Laravel application created"
    else
        print_success "Test application directory already exists"
    fi

    cd "$TEST_APP_DIR"

    if [ ! -f "composer.json" ]; then
        print_error "Test app composer.json not found!"
        exit 1
    fi
}

# Add path repository to composer.json
add_path_repository() {
    print_step "Adding path repository to composer.json..."

    # Check if repositories section exists and contains our path
    if grep -q '"repositories"' composer.json && grep -q "$PACKAGE_DIR" composer.json; then
        print_warning "Path repository already exists in composer.json"
        return 0
    fi

    # Backup original composer.json
    cp composer.json composer.json.backup
    print_info "Created backup: composer.json.backup"

    # Add repository using PHP to properly handle JSON
    php -r "
    \$composerPath = 'composer.json';
    \$composer = json_decode(file_get_contents(\$composerPath), true);

    if (!isset(\$composer['repositories'])) {
        \$composer['repositories'] = [];
    }

    // Check if path repository already exists
    \$pathExists = false;
    foreach (\$composer['repositories'] as \$repo) {
        if (isset(\$repo['url']) && \$repo['url'] === '$PACKAGE_DIR') {
            \$pathExists = true;
            break;
        }
    }

    if (!\$pathExists) {
        \$composer['repositories'][] = [
            'type' => 'path',
            'url' => '$PACKAGE_DIR',
            'options' => [
                'symlink' => true
            ]
        ];

        file_put_contents(\$composerPath, json_encode(\$composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo 'Repository added successfully';
    } else {
        echo 'Repository already exists';
    }
    "

    print_success "Path repository configuration updated"
}

# Require the local package
require_package() {
    print_step "Requiring local package..."

    # Check if package is already required
    if grep -q "\"$PACKAGE_NAME\"" composer.json; then
        print_warning "Package already required, updating..."
        composer update "$PACKAGE_NAME" --no-interaction
    else
        print_info "Requiring package: $PACKAGE_NAME"
        composer require "$PACKAGE_NAME:*@dev" --no-interaction
    fi

    print_success "Package required successfully"
}

# Publish package assets
publish_assets() {
    print_step "Publishing package assets..."

    # Check if artisan exists and app is properly set up
    if [ ! -f "artisan" ]; then
        print_error "Laravel artisan not found!"
        exit 1
    fi

    # Copy .env.example to .env if it doesn't exist
    if [ ! -f ".env" ]; then
        cp .env.example .env
        print_info "Created .env file from .env.example"
    fi

    # Generate app key if needed
    if ! grep -q "APP_KEY=base64:" .env; then
        php artisan key:generate --no-interaction
        print_info "Generated application key"
    fi

    # Publish package assets (if any)
    print_info "Publishing package assets and configurations..."
    php artisan vendor:publish --provider="Webook\\LaravelCMS\\CMSServiceProvider" --force || true

    print_success "Assets published"
}

# Create development symlinks
create_symlinks() {
    print_step "Creating development symlinks..."

    # Create storage link if it doesn't exist
    if [ ! -L "public/storage" ]; then
        php artisan storage:link
        print_info "Created storage symlink"
    fi

    print_success "Symlinks created"
}

# Setup file watchers
setup_watchers() {
    print_step "Setting up file watchers..."

    # Create a simple watcher script
    cat > watch-package.sh << 'EOF'
#!/bin/bash

# File watcher for package development
# Watches for changes in the package and triggers refresh

PACKAGE_DIR="../laravel-cms"
WATCH_DIRS="$PACKAGE_DIR/src $PACKAGE_DIR/config $PACKAGE_DIR/resources"

echo "🔍 Watching package files for changes..."
echo "📁 Monitoring: $WATCH_DIRS"
echo "⏹  Press Ctrl+C to stop"

if command -v inotifywait &> /dev/null; then
    # Use inotifywait if available (Linux)
    inotifywait -m -r -e modify,create,delete,move $WATCH_DIRS --format '%w%f %e' |
    while read file event; do
        echo "🔄 Changed: $file ($event)"
        echo "♻️  Refreshing autoloader..."
        composer dump-autoload
    done
elif command -v fswatch &> /dev/null; then
    # Use fswatch if available (macOS)
    fswatch -o $WATCH_DIRS |
    while read num; do
        echo "🔄 Package files changed"
        echo "♻️  Refreshing autoloader..."
        composer dump-autoload
    done
else
    echo "⚠️  File watcher not available. Install inotifywait (Linux) or fswatch (macOS)"
    echo "📖 Manual refresh: composer dump-autoload"
fi
EOF

    chmod +x watch-package.sh
    print_success "File watcher script created: watch-package.sh"
}

# Main execution
main() {
    echo -e "${BLUE}🚀 Laravel Package Development Setup${NC}"
    echo -e "${BLUE}======================================${NC}"

    check_directories
    setup_test_app
    add_path_repository
    require_package
    publish_assets
    create_symlinks
    setup_watchers

    echo -e "\n${GREEN}🎉 Setup completed successfully!${NC}"
    echo -e "\n${BLUE}Next steps:${NC}"
    echo -e "  1. ${YELLOW}cd $TEST_APP_DIR${NC}"
    echo -e "  2. ${YELLOW}make setup${NC} (if using Makefile)"
    echo -e "  3. ${YELLOW}./watch-package.sh${NC} (to watch for changes)"
    echo -e "  4. ${YELLOW}php artisan serve${NC} (to start development server)"

    echo -e "\n${BLUE}Development commands:${NC}"
    echo -e "  • ${YELLOW}composer dump-autoload${NC} - Refresh package autoloader"
    echo -e "  • ${YELLOW}php artisan vendor:publish --force${NC} - Republish assets"
    echo -e "  • ${YELLOW}php artisan config:clear${NC} - Clear config cache"
}

# Run main function
main "$@"