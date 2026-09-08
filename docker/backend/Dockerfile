# Runtime PHP + serveur web reunis dans un seul binaire : FrankenPHP.
# L'image sert automatiquement l'application Symfony depuis /app/public.
FROM dunglas/frankenphp:1.12-php8.4-bookworm

# Extensions PHP requises par Symfony et par PostgreSQL.
RUN install-php-extensions \
    pdo_pgsql \
    intl \
    opcache \
    zip

# Composer, repris tel quel depuis l'image officielle.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# En developpement, le dossier ./backend de l'hote est monte sur /app
# (voir compose.yaml) : le code modifie est pris en compte sans rebuild.
# FrankenPHP demarre le serveur automatiquement (port defini par SERVER_NAME).
