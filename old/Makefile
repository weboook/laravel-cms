# Laravel Package Development Makefile
# Provides common development tasks for package and test app management

# Configuration
PACKAGE_DIR = ../laravel-cms
TEST_APP_DIR = ./cms-test-app
PACKAGE_NAME = webook/laravel-cms

# Colors for output
RED = \033[0;31m
GREEN = \033[0;32m
YELLOW = \033[1;33m
BLUE = \033[0;34m
NC = \033[0m

# Default target
.DEFAULT_GOAL := help

# Helper function to check if we're in test app directory
define check_test_app
	@if [ ! -f "$(TEST_APP_DIR)/composer.json" ]; then \
		echo "$(RED)✗$(NC) Test app not found. Run 'make setup' first."; \
		exit 1; \
	fi
endef

define check_package
	@if [ ! -f "$(PACKAGE_DIR)/composer.json" ]; then \
		echo "$(RED)✗$(NC) Package not found at $(PACKAGE_DIR)"; \
		exit 1; \
	fi
endef

.PHONY: help
help: ## Show this help message
	@echo "$(BLUE)Laravel Package Development Commands$(NC)"
	@echo "$(BLUE)===================================$(NC)"
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*##/ { printf "  $(YELLOW)%-15s$(NC) %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@echo ""
	@echo "$(BLUE)Package Directory:$(NC) $(PACKAGE_DIR)"
	@echo "$(BLUE)Test App Directory:$(NC) $(TEST_APP_DIR)"

.PHONY: setup
setup: ## Initial development setup (creates test app and links package)
	@echo "$(BLUE)🚀 Setting up development environment...$(NC)"
	@chmod +x setup-development.sh
	@./setup-development.sh
	@echo "$(GREEN)✓ Setup completed!$(NC)"

.PHONY: refresh
refresh: ## Refresh package autoload and clear caches
	$(call check_test_app)
	@echo "$(BLUE)♻️  Refreshing package autoload...$(NC)"
	@cd $(TEST_APP_DIR) && composer dump-autoload
	@cd $(TEST_APP_DIR) && php artisan config:clear
	@cd $(TEST_APP_DIR) && php artisan cache:clear
	@cd $(TEST_APP_DIR) && php artisan view:clear
	@echo "$(GREEN)✓ Autoload and caches refreshed$(NC)"

.PHONY: test
test: ## Run package tests
	$(call check_package)
	@echo "$(BLUE)🧪 Running package tests...$(NC)"
	@cd $(PACKAGE_DIR) && composer test
	@echo "$(GREEN)✓ Package tests completed$(NC)"

.PHONY: test-coverage
test-coverage: ## Run package tests with coverage
	$(call check_package)
	@echo "$(BLUE)🧪 Running package tests with coverage...$(NC)"
	@cd $(PACKAGE_DIR) && composer test-coverage
	@echo "$(GREEN)✓ Coverage report generated$(NC)"

.PHONY: format
format: ## Format package code using Laravel Pint
	$(call check_package)
	@echo "$(BLUE)🎨 Formatting package code...$(NC)"
	@cd $(PACKAGE_DIR) && composer format
	@echo "$(GREEN)✓ Code formatted$(NC)"

.PHONY: analyse
analyse: ## Analyse package code style
	$(call check_package)
	@echo "$(BLUE)🔍 Analysing package code style...$(NC)"
	@cd $(PACKAGE_DIR) && composer analyse
	@echo "$(GREEN)✓ Code analysis completed$(NC)"

.PHONY: watch
watch: ## Watch for package changes and auto-refresh
	$(call check_test_app)
	@echo "$(BLUE)👀 Starting file watcher...$(NC)"
	@cd $(TEST_APP_DIR) && ./watch-package.sh

.PHONY: publish
publish: ## Publish package assets and configurations
	$(call check_test_app)
	@echo "$(BLUE)📤 Publishing package assets...$(NC)"
	@cd $(TEST_APP_DIR) && php artisan vendor:publish --provider="Webook\\LaravelCMS\\CMSServiceProvider" --force
	@echo "$(GREEN)✓ Assets published$(NC)"

.PHONY: serve
serve: ## Start Laravel development server
	$(call check_test_app)
	@echo "$(BLUE)🌐 Starting Laravel development server...$(NC)"
	@cd $(TEST_APP_DIR) && php artisan serve

.PHONY: install
install: ## Install package dependencies
	$(call check_package)
	@echo "$(BLUE)📦 Installing package dependencies...$(NC)"
	@cd $(PACKAGE_DIR) && composer install
	@echo "$(GREEN)✓ Dependencies installed$(NC)"

.PHONY: update
update: ## Update package in test app
	$(call check_test_app)
	@echo "$(BLUE)⬆️  Updating package in test app...$(NC)"
	@cd $(TEST_APP_DIR) && composer update $(PACKAGE_NAME)
	@echo "$(GREEN)✓ Package updated$(NC)"

.PHONY: link-check
link-check: ## Check symlink status
	$(call check_test_app)
	@echo "$(BLUE)🔗 Checking symlink status...$(NC)"
	@cd $(TEST_APP_DIR)/vendor/webook && \
	if [ -L "laravel-cms" ]; then \
		echo "$(GREEN)✓ Package is symlinked$(NC)"; \
		echo "$(BLUE)Target:$(NC) $$(readlink laravel-cms)"; \
	else \
		echo "$(YELLOW)⚠ Package is copied, not symlinked$(NC)"; \
		echo "$(YELLOW)Run 'make refresh' to restore symlink$(NC)"; \
	fi

.PHONY: logs
logs: ## Show Laravel logs
	$(call check_test_app)
	@echo "$(BLUE)📋 Showing Laravel logs...$(NC)"
	@cd $(TEST_APP_DIR) && tail -f storage/logs/laravel.log

.PHONY: migrate
migrate: ## Run database migrations
	$(call check_test_app)
	@echo "$(BLUE)🗄️  Running database migrations...$(NC)"
	@cd $(TEST_APP_DIR) && php artisan migrate
	@echo "$(GREEN)✓ Migrations completed$(NC)"

.PHONY: seed
seed: ## Run database seeders
	$(call check_test_app)
	@echo "$(BLUE)🌱 Running database seeders...$(NC)"
	@cd $(TEST_APP_DIR) && php artisan db:seed
	@echo "$(GREEN)✓ Seeding completed$(NC)"

.PHONY: fresh
fresh: ## Fresh migration with seed
	$(call check_test_app)
	@echo "$(BLUE)🔄 Running fresh migration with seed...$(NC)"
	@cd $(TEST_APP_DIR) && php artisan migrate:fresh --seed
	@echo "$(GREEN)✓ Fresh migration completed$(NC)"

.PHONY: clean
clean: ## Clean caches and compiled files
	$(call check_test_app)
	@echo "$(BLUE)🧹 Cleaning caches and compiled files...$(NC)"
	@cd $(TEST_APP_DIR) && php artisan config:clear
	@cd $(TEST_APP_DIR) && php artisan cache:clear
	@cd $(TEST_APP_DIR) && php artisan view:clear
	@cd $(TEST_APP_DIR) && php artisan route:clear
	@cd $(TEST_APP_DIR) && composer dump-autoload
	@echo "$(GREEN)✓ Cleanup completed$(NC)"

.PHONY: status
status: ## Show development environment status
	@echo "$(BLUE)📊 Development Environment Status$(NC)"
	@echo "$(BLUE)================================$(NC)"
	@echo ""
	@echo "$(BLUE)Package Status:$(NC)"
	@if [ -d "$(PACKAGE_DIR)" ]; then \
		echo "$(GREEN)✓ Package directory exists$(NC)"; \
		cd $(PACKAGE_DIR) && echo "$(BLUE)  Version:$(NC) $$(composer config version 2>/dev/null || echo 'dev')"; \
	else \
		echo "$(RED)✗ Package directory not found$(NC)"; \
	fi
	@echo ""
	@echo "$(BLUE)Test App Status:$(NC)"
	@if [ -d "$(TEST_APP_DIR)" ]; then \
		echo "$(GREEN)✓ Test app directory exists$(NC)"; \
		if [ -f "$(TEST_APP_DIR)/composer.json" ]; then \
			cd $(TEST_APP_DIR) && echo "$(BLUE)  Laravel:$(NC) $$(php artisan --version 2>/dev/null || echo 'Unknown')"; \
		fi; \
	else \
		echo "$(RED)✗ Test app directory not found$(NC)"; \
	fi
	@echo ""
	$(MAKE) link-check 2>/dev/null || true

.PHONY: reset
reset: ## Reset development environment (removes test app)
	@echo "$(YELLOW)⚠️  This will remove the test application directory!$(NC)"
	@read -p "Are you sure? (y/N): " confirm && [ "$$confirm" = "y" ]
	@if [ -d "$(TEST_APP_DIR)" ]; then \
		echo "$(BLUE)🗑️  Removing test application...$(NC)"; \
		rm -rf $(TEST_APP_DIR); \
		echo "$(GREEN)✓ Test application removed$(NC)"; \
	else \
		echo "$(YELLOW)⚠ Test application directory not found$(NC)"; \
	fi

# Development workflow targets
.PHONY: dev
dev: setup serve ## Setup and start development server

.PHONY: test-all
test-all: format analyse test ## Run all code quality checks and tests

.PHONY: deploy-prep
deploy-prep: test-all ## Prepare for deployment (run all checks)