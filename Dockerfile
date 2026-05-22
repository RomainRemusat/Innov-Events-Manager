# Utilise une image PHP avec Apache
FROM php:8.2-apache

# Téléchargement de l'utilitaire d'installation d'extensions PHP pré-compilées
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Rendre l'utilitaire exécutable et installer PDO MySQL et MongoDB proprement
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo pdo_mysql mongodb

# Active le module de réécriture d'Apache
RUN a2enmod rewrite

# Copie le code source dans le conteneur
COPY . /var/www/html/

EXPOSE 80