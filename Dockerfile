# Dockerfile
FROM php:8.2-apache

# Labels
LABEL maintainer="tu-email@ejemplo.com"
ARG APACHE_DOCUMENT_ROOT=/var/www/html

# Instalación de dependencias del sistema y extensiones PHP necesarias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
  && docker-php-ext-configure zip \
  && docker-php-ext-install -j$(nproc) pdo_mysql zip gd mbstring soap sockets \
  && a2enmod rewrite headers

# Configure Apache document root (si tu index está en /var/www/html por defecto no hace falta cambiar)
ENV APACHE_DOCUMENT_ROOT=${APACHE_DOCUMENT_ROOT}
RUN sed -ri -e "s!DocumentRoot /var/www/html!DocumentRoot ${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
 && sed -ri -e "s!<Directory /var/www/>!<Directory ${APACHE_DOCUMENT_ROOT}>!g" /etc/apache2/apache2.conf

# Instalar Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Copiar código de la aplicación al container
# Ajusta la ruta si tu app no está en la raíz del repo
WORKDIR /var/www/html
COPY . /var/www/html

# Dar permisos (ajusta según política de tu proyecto)
RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html/storage 2>/dev/null || true

# Instalar dependencias PHP via composer (si composer.json existe)
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader --no-interaction; fi

EXPOSE 80

# Healthcheck (básico)
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]
