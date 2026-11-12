FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libpq-dev \
    && docker-php-ext-install curl fileinfo pdo_pgsql pgsql \
    && a2enmod headers rewrite \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' \
        'display_errors=Off' \
        'log_errors=On' \
        'error_log=/proc/self/fd/2' \
        'upload_max_filesize=50M' \
        'post_max_size=80M' \
        'max_file_uploads=6' \
        'max_execution_time=180' \
        'max_input_time=180' \
        > "$PHP_INI_DIR/conf.d/zz-railway.ini" \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .
COPY assets/images/events/ /opt/skalinga-event-seeds/
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY scripts/railway-entrypoint.sh /usr/local/bin/railway-entrypoint
RUN chmod 0755 /usr/local/bin/railway-entrypoint

ENTRYPOINT ["railway-entrypoint"]
CMD ["apache2-foreground"]