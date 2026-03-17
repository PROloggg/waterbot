up:
	@docker compose up -d --build --remove-orphans

up-local:
	@docker compose -f docker-compose.yml -f docker-compose.local.yml up -d --build --remove-orphans

down:
	@docker compose down -v

down-local:
	@docker compose -f docker-compose.yml -f docker-compose.local.yml down -v

env:
	@docker compose exec --user=www-data bot bash

env-cron:
	@docker compose exec --user=www-data php-cron bash
