#!/bin/bash
set -e

cd /web/app

if [ -f composer.json ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f param.php ] && [ -f param-dist.php ]; then
    # sed -i échoue sur ce bind mount (rename refusé par Docker Desktop) :
    # on passe par un fichier temporaire hors volume, puis un cp classique.
    sed \
        -e "s/define(\"HOTE_MYSQL\", \"mysql.univ.fr\");/define(\"HOTE_MYSQL\", \"${DATABASE_HOST:-database}\");/" \
        -e "s/define(\"USER_MYSQL\", \"apose\");/define(\"USER_MYSQL\", \"${DATABASE_USER:-root}\");/" \
        -e "s/define(\"PASSWD_MYSQL\", \"A_CHANGER\");/define(\"PASSWD_MYSQL\", \"${DATABASE_PASSWORD:-password}\");/" \
        -e "s/define(\"MYSQL_BASE_DATAS\", \"APOSE\");/define(\"MYSQL_BASE_DATAS\", \"${DATABASE_NAME:-apose}\");/" \
        -e "s|define(\"SERVICE_BASE_URL\", \"\");|define(\"SERVICE_BASE_URL\", \"${APP_BASE_URL:-http://localhost:9075}\");|" \
        param-dist.php > /tmp/param.php
    cp /tmp/param.php param.php
    echo "[entrypoint] param.php généré depuis param-dist.php (connexion MySQL pointée sur le conteneur '${DATABASE_HOST:-database}')."
    echo "[entrypoint] Pensez à compléter CAS / LDAP / Oracle dans param.php si besoin."
fi

exec php -S 0.0.0.0:8000 -t public
