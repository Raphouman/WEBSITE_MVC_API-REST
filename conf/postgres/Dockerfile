# Partir de l'image officielle de PostgreSQL comme base
# https://hub.docker.com/_/postgres
FROM postgres:16
#-alpine

# copie des scripts qui seront exécutés à l'initialisation (création BDD, etc.)
COPY ./conf/postgres/sql/ /docker-entrypoint-initdb.d/

# Corriger les caractères de fin de ligne pour les utilisateurs de Git sous Windows
RUN apt-get update && apt-get install -y dos2unix
RUN find /docker-entrypoint-initdb.d -type f -print0 | xargs -0 dos2unix && \
    apt-get --purge remove -y dos2unix && \
    rm -rf /var/lib/apt/lists/*
