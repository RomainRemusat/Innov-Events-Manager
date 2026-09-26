# =========================================================================
# CONFIGURATION DE L'ENVIRONNEMENT - LOCAL (ACTIF)
# =========================================================================

APP_ENV=local
APP_NAME="Innov'Events Manager"
BASE_URL=http://localhost:8081

# Base de données Relationnelle (MySQL 8.0 - 3NF)
DB_HOST=db
DB_PORT=3306
DB_NAME=innovevents_db
DB_USER=root
DB_PASS=root_password

# Base de données NoSQL (MongoDB - Traçabilité & Audit Logs)
MONGO_HOST=mongodb
MONGO_PORT=27017
MONGO_DATABASE=innovevents_nosql
MONGO_INITDB_ROOT_USERNAME=root
MONGO_INITDB_ROOT_PASSWORD=root_password
MONGO_URI=mongodb://mongodb:27017

# Service de messagerie locale (MailHog)
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=no-reply@innovevents.fr
MAIL_FROM_NAME="L'équipe Innov'Events"