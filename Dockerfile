# Utilise une image PHP officielle avec Apache
FROM php:8.2-apache

# --- CONFIGURATION APACHE : Dossier racine pointant vers "public" ---
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# --- COMPOSER : Récupération de l'exécutable officiel (Multi-stage build) ---
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- SÉCURITÉ & OUTILS : Installation de git et unzip (requis pour Composer) ---
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# --- EXTENSIONS PHP : Téléchargement de l'utilitaire d'installation d'extensions pré-compilées ---
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Rendre l'utilitaire exécutable et installer les extensions requises (PDO, MongoDB et Zip pour Composer)
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo pdo_mysql mongodb zip

# Active le module de réécriture d'Apache (nécessaire pour le routage de l'index.php)
RUN a2enmod rewrite

# Copie l'intégralité du code source dans le conteneur de travail
COPY . /var/www/html/

EXPOSE 80