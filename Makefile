DC  = docker compose
PHP = $(DC) exec app php
.DEFAULT_GOAL := help

help: ## Show available commands
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*## "}{printf "  \033[36m%-10s\033[0m %s\n",$$1,$$2}'

init: ## Build, start and prepare everything (first run)
	$(DC) up -d --build --wait app
	$(DC) exec app composer install --no-interaction
	$(MAKE) db
	$(DC) up -d worker
	@echo "\n  App:      http://localhost:8080\n  RabbitMQ: http://localhost:15672 (guest/guest)\n"

up: ## Start containers
	$(DC) up -d

down: ## Stop containers
	$(DC) down

db: ## Create database and run migrations (dev + test)
	$(PHP) bin/console doctrine:database:create --if-not-exists
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(PHP) bin/console doctrine:database:create --if-not-exists --env=test
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test

test: ## Run the whole test suite
	$(PHP) bin/phpunit

test-unit: ## Run unit tests only
	$(PHP) bin/phpunit --testsuite=unit

test-integration: ## Run integration tests only
	$(PHP) bin/phpunit --testsuite=integration

logs: ## Tail the async worker logs
	$(DC) logs -f worker

sh: ## Shell into the app container
	$(DC) exec app sh

.PHONY: help init up down db test test-unit test-integration logs sh
