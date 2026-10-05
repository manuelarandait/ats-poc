DC   = docker compose
# No TTY on CI runners (GitHub Actions sets CI=true)
EXEC = $(DC) exec $(if $(CI),-T,) app
PHP  = $(EXEC) php
.DEFAULT_GOAL := help

help: ## Show available commands
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*## "}{printf "  \033[36m%-10s\033[0m %s\n",$$1,$$2}'

init: ## Build, start and prepare everything (first run)
	$(DC) up -d --build --wait app
	$(EXEC) composer install --no-interaction
	$(MAKE) db
	$(MAKE) fixtures
	$(MAKE) assets
	$(DC) up -d worker
	@echo "\n  App:        http://localhost:8080\n  RabbitMQ:   http://localhost:15672 (guest / guest)\n  PostgreSQL: localhost:5433, database app (app / app)\n"

up: ## Start containers
	$(DC) up -d

down: ## Stop containers
	$(DC) down

db: ## Create database and run migrations (dev + test)
	$(PHP) bin/console doctrine:database:create --if-not-exists
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(PHP) bin/console doctrine:database:create --if-not-exists --env=test
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test

assets: ## Install JS vendors and build the CSS
	$(PHP) bin/console importmap:install
	$(PHP) bin/console tailwind:build

css-watch: ## Rebuild the CSS on every template change
	$(PHP) bin/console tailwind:build --watch

fixtures: ## Reload demo data (job offers + sample applications)
	$(PHP) bin/console doctrine:fixtures:load --no-interaction

test: ## Run the whole test suite
	$(PHP) bin/phpunit

test-unit: ## Run unit tests only
	$(PHP) bin/phpunit --testsuite=unit

test-integration: ## Run integration tests only
	$(PHP) bin/phpunit --testsuite=integration

cs: ## Check coding standards (dry-run)
	$(EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix coding standards
	$(EXEC) vendor/bin/php-cs-fixer fix

stan: ## Static analysis (PHPStan level max)
	$(PHP) bin/console cache:warmup --env=dev -q
	$(EXEC) vendor/bin/phpstan analyse --memory-limit=1G

deptrac: ## Check architecture layers and context boundaries
	$(EXEC) vendor/bin/deptrac analyse --no-progress

qa: cs stan deptrac ## Run all quality checks

logs: ## Tail the app and worker logs
	$(DC) logs -f --tail=20 app worker

sh: ## Shell into the app container
	$(EXEC) sh

.PHONY: help init up down db assets css-watch fixtures test test-unit test-integration cs cs-fix stan deptrac qa logs sh
