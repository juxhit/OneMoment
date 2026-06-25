FROM php:8.2-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_sqlite zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-onemoment.conf /etc/apache2/conf-available/onemoment.conf
RUN a2enconf onemoment

WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html

RUN mkdir -p data uploads thumbs exports \
    && chown -R www-data:www-data data uploads thumbs exports

COPY docker/entrypoint.sh /usr/local/bin/onemoment-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/onemoment-entrypoint.sh \
    && chmod +x /usr/local/bin/onemoment-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/onemoment-entrypoint.sh"]
CMD ["apache2-foreground"]