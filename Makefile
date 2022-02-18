all: nuxt-install strapi-npm-install up

nuxt-install: nuxt-npm-install nuxt-build

nuxt-npm-install:
	@docker-compose run --rm nuxt npm i

nuxt-restart:
	@docker-compose restart nuxt

nuxt-build:
	@docker-compose run --rm nuxt npm run build

nuxt-build-restart: nuxt-build nuxt-restart

strapi-npm-install:
	@docker-compose run --rm strapi npm i

up:
	@docker-compose up -d --build --remove-orphans

down:
	@docker-compose down -v

dump:
	@docker-compose exec mongo mongodump

restore:
	@docker-compose exec mongo mongorestore


