# Utilise une image PHP avec Apache
FROM php:8.2-apache

# Installe les extensions nécessaires pour MySQL et MongoDB
RUN apt-get update && apt-get install -y \
    libssl-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# Active le module de réécriture d'Apache
RUN a2enmod rewrite

# Copie le code source dans le conteneur
COPY . /var/www/html/

EXPOSE 80