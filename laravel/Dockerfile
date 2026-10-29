FROM php:8.3-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpq-dev libicu-dev libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_pgsql intl zip mbstring bcmath pcntl dom xml xmlwriter \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
EXPOSE 8000 8080
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
