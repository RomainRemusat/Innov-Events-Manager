# Utilise une image PHP avec Apache
FROM php:8.2-apache

# --- NOUVEAU : On dit à Apache que le dossier racine est "public" ---
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
# ------------------------------------------------------------------

# Téléchargement de l'utilitaire d'installation d'extensions PHP pré-compilées
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Rendre l'utilitaire exécutable et installer PDO MySQL et MongoDB proprement
#install-php-extensions pdo pdo_mysql -> mongodb <- PECL non dispo actuellement 26/05/2026

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo pdo_mysql mongodb

# Active le module de réécriture d'Apache
RUN a2enmod rewrite

# Copie le code source dans le conteneur
COPY . /var/www/html/

EXPOSE 80