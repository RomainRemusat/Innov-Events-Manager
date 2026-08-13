# =========================================================================
# CONFIGURATION DE L'ENVIRONNEMENT - LOCAL (ACTIF)
# =========================================================================

# Application
APP_ENV=local
APP_NAME="Innov'Events Manager"
BASE_URL=http://localhost:8081

# Base de données Relationnelle (MySQL)
DB_HOST=db
DB_PORT=3306
DB_NAME=innovevents_db
DB_USER=root
DB_PASS=root_password

# Base de données NoSQL (MongoDB)
MONGO_URI=mongodb://mongodb:27017

# Service de messagerie (MailHog)
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=no-reply@innovevents.fr
MAIL_FROM_NAME="L'équipe Innov'Events"