#!/bin/bash
set -e

# Démarre le serveur MariaDB officiel en arrière-plan : il gère l'init du
# datadir, le mot de passe root, etc. comme l'image mariadb standard.
docker-entrypoint.sh mariadbd &
pid="$!"

DB_NAME="${MARIADB_DATABASE:-apose}"

# Pendant l'init, MariaDB redémarre en interne plusieurs fois (serveur temporaire
# sans réseau le temps d'appliquer le mot de passe root) : on boucle sur la vraie
# commande plutôt que sur un simple ping, qui donne de faux positifs pendant ces
# phases transitoires.
until mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;" 2>/dev/null; do
    sleep 1
done

if [ -f /apose.sql ]; then
    mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" "${DB_NAME}" < /apose.sql
    echo "[entrypoint] apose.sql exécuté sur la base '${DB_NAME}'."
fi

wait "$pid"
