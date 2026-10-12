# Docker for MIRÉ Film (Laravel + MySQL)

Place these at your Laravel project root:
Dockerfile, docker/, docker-compose.yml, docker-compose.prod.yml, .dockerignore, .env.docker.example

## Dev
    cp .env.example .env     # set DB_HOST=db, DB_DATABASE/USERNAME/PASSWORD to match compose
    docker compose up -d --build
    docker compose exec app php artisan migrate
    open http://localhost:8080

## Prod
    cp .env.docker.example .env.prod   # fill secrets + APP_KEY (php artisan key:generate --show)
    docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build

The prod entrypoint caches config/routes/views and runs migrations when RUN_MIGRATIONS=true.
Put a TLS reverse proxy (Caddy, Traefik, or your host's load balancer) in front of the web container.
Requires composer.lock to exist (run `composer install` once locally and commit it).
