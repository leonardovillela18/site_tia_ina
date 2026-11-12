FROM php:8.3-apache

# Modulos do Apache usados pelo site
RUN a2enmod rewrite headers \
    && sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf \
    && sed -ri 's/^#?ServerName .*/ServerName localhost/' /etc/apache2/apache2.conf || true

# GD com suporte a WebP (gera as variantes responsivas no upload)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd \
    && rm -rf /var/lib/apt/lists/*

# Configuracoes de PHP, limites do Apache e bloqueios de seguranca
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-tia-ina.ini
COPY docker/apache-mpm.conf /etc/apache2/conf-available/mpm-limits.conf
COPY docker/apache-security.conf /etc/apache2/conf-available/app-security.conf
RUN a2enconf mpm-limits app-security

# Codigo da aplicacao
COPY . /var/www/html/

RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/img

EXPOSE 80
