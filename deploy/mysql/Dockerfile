FROM mysql:8.0

COPY scripts/schema.sql /docker-entrypoint-initdb.d/10-schema.sql
COPY scripts/initialise.sql /docker-entrypoint-initdb.d/20-initialise.sql
