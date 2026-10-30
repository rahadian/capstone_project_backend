.PHONY: help up up-build down restart build ps logs shell artisan composer migrate migrate-fresh migrate-fresh-seed seed cache-clear composer-install npm-install npm-build

COMPOSE = docker compose -f docker-compose.yml
APP_SERVICE = nusantara_booking-app
DB_SERVICE = nusantara_booking-db

help:
	@echo "Available commands:"
	@echo "  make up                Start all containers without rebuilding"
	@echo "  make up-build          Start all containers with --build"
	@echo "  make down              Stop and remove containers"
	@echo "  make restart           Restart containers"
	@echo "  make build             Rebuild containers"
	@echo "  make ps                Show container status"
	@echo "  make logs              Show logs for all services"
	@echo "  make shell             Open shell in the app container"
	@echo "  make shell-worker      Open shell in the worker container"
	@echo "  make logs-worker       Show logs for the worker container"
	@echo "  make artisan ARGS='migrate' Run an artisan command"
	@echo "  make composer ARGS='install' Run a composer command"
	@echo "  make migrate           Run Laravel migrations"
	@echo "  make migrate-fresh     Drop all tables and re-run migrations"
	@echo "  make migrate-fresh-seed Drop all tables, re-run migrations and seed"
	@echo "  make seed              Run database seeders"
	@echo "  make seed-uji          Run database uji deployment seeders"
	@echo "  make cache-clear       Clear Laravel caches"
	@echo "  make composer-install  Install PHP dependencies"
	@echo "  make npm-install       Install Node dependencies"
	@echo "  make npm-build         Build frontend assets"

up:
	$(COMPOSE) up -d --remove-orphans

up-build:
	$(COMPOSE) up -d --build --remove-orphans

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) restart

build:
	$(COMPOSE) build --no-cache

ps:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs -f

logs-worker:
	$(COMPOSE) logs -f appdesa-worker

shell:
	$(COMPOSE) exec $(APP_SERVICE) bash

shell-worker:
	$(COMPOSE) exec appdesa-worker bash

artisan:
	$(COMPOSE) exec -T $(APP_SERVICE) php artisan $(ARGS)

composer:
	$(COMPOSE) exec -T $(APP_SERVICE) composer $(ARGS)

migrate:
	$(COMPOSE) exec -T $(APP_SERVICE) php artisan migrate --force

migrate-fresh:
	$(COMPOSE) exec -T $(APP_SERVICE) php artisan migrate:fresh --force

migrate-fresh-seed:
	$(COMPOSE) exec -T $(APP_SERVICE) php artisan migrate:fresh --seed --force

seed:
	$(COMPOSE) exec -T $(APP_SERVICE) php artisan db:seed --force

seed-uji:
	$(COMPOSE) exec -T $(APP_SERVICE) php artisan db:seed --class=UjiDeploySeeder

cache-clear:
	$(COMPOSE) exec -T $(APP_SERVICE) php artisan cache:clear && $(COMPOSE) exec -T $(APP_SERVICE) php artisan config:clear && $(COMPOSE) exec -T $(APP_SERVICE) php artisan route:clear && $(COMPOSE) exec -T $(APP_SERVICE) php artisan view:clear

composer-install:
	$(COMPOSE) exec -T $(APP_SERVICE) composer install

composer-remove:
	$(COMPOSE) exec -T $(APP_SERVICE) composer remove $(ARGS)

composer-require:
	$(COMPOSE) exec -T $(APP_SERVICE) composer require $(ARGS)

composer-dump:
	$(COMPOSE) exec -T $(APP_SERVICE) composer dump-autoload

npm-install:
	$(COMPOSE) exec -T $(APP_SERVICE) npm install

npm-build:
	$(COMPOSE) exec -T $(APP_SERVICE) npm run build

npm-build-watch:
	$(COMPOSE) exec -T $(APP_SERVICE) npm run build -- --watch