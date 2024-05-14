linter-autofix:
	docker compose run --rm php-fpm ./vendor/bin/php-cs-fixer fix -v --using-cache=no
	docker compose run --rm php-fpm ./vendor/bin/rector

analyze:
	docker compose run --rm php-fpm ./vendor/bin/phplint
	docker compose run --rm php-fpm ./vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no
	docker compose run --rm php-fpm ./vendor/bin/rector --dry-run
	docker compose run --rm php-fpm ./vendor/bin/phpstan --memory-limit=-1
	docker compose run --rm php-fpm ./vendor/bin/psalm --no-cache $(ARGS)
	docker compose run --rm php-fpm ./vendor/bin/phpunit

build: docker-build
up: docker-up
down: docker-down

phpmetrics:
	docker compose run --rm php-fpm./vendor/bin/phpmetrics --report-html=var/myreport ./src

linter-autofix:
	PHP_CS_FIXER_IGNORE_ENV=1 docker compose run --rm php-fpm ./vendor/bin/php-cs-fixer fix -v --using-cache=no

analyze:
	docker compose run --rm php-fpm./vendor/bin/phplint
	docker compose run --rm php-fpm./vendor/bin/phpstan --memory-limit=-1
	docker compose run --rm php-fpm./vendor/bin/psalm --no-cache $(ARGS)
	PHP_CS_FIXER_IGNORE_ENV=1 docker compose run --rm php-fpm ./vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no

composer-install:
	docker compose run --rm php-fpm composer install

composer-dump:
	docker compose run --rm php-fpm composer dump-autoload

composer-update:
	docker compose run --rm php-fpm composer update

composer-outdated:
	docker compose run --rm php-fpm composer outdated

composer-dry-run:
	docker compose run --rm php-fpm composer update --dry-run

docker-up:
	docker compose up -d

docker-rebuild:
	docker compose down -v --remove-orphans
	docker compose up -d --build

docker-down:
	docker compose down --remove-orphans

docker-down-clear:
	docker compose down -v --remove-orphans

docker-pull:
	docker compose pull

docker-build:
	docker compose build
