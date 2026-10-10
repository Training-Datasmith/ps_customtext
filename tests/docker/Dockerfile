FROM php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143

RUN docker-php-ext-install pdo_mysql \
    && sed -i 's/deb.debian.org/archive.debian.org/g' /etc/apt/sources.list \
    && sed -i '/stretch-updates/d' /etc/apt/sources.list \
    && sed -i '/security.debian.org/d' /etc/apt/sources.list \
    && apt-get update \
    && apt-get install -y --allow-unauthenticated --no-install-recommends gnupg ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /src
