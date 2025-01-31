up:
	@docker compose up -d --build --remove-orphans

down:
	@docker compose down -v

env:
	@docker compose exec --user=www-data bot bash

env-cron:
	@docker compose exec --user=www-data php-cron bash