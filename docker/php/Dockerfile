# docker/php/Dockerfile
FROM php:8.3-fpm

# System deps
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		git \
		unzip \
		libzip-dev \
		libpng-dev \
		libjpeg62-turbo-dev \
		libfreetype6-dev; \
	docker-php-ext-configure gd --with-freetype --with-jpeg; \
	docker-php-ext-install -j"$(nproc)" pdo pdo_mysql zip gd; \
	rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Optional: nur wenn composer.json vorhanden
RUN if [ -f composer.json ]; then composer install --no-interaction; fi

EXPOSE 9000
CMD ["php-fpm"]