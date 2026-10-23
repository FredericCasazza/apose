FROM php:8.2-cli

# =========================================================
# SYSTEM LIBRARIES
# =========================================================
RUN apt-get update && apt-get install -y --no-install-recommends \
    apt-transport-https \
    apt-utils \
    ca-certificates \
    gcc \
    g++ \
    make \
    autoconf \
    git \
    wget \
    unzip \
    acl \
    locales \
    lsb-release \
    nodejs \
    libicu-dev \
    libldap2-dev \
    libmagickwand-dev \
    libonig-dev \
    libpng-dev \
    libpq-dev \
    libxml2-dev \
    libxslt-dev \
    libzip-dev \
    libaio1t64 \
 && ln -s /usr/lib/x86_64-linux-gnu/libaio.so.1t64 /usr/lib/x86_64-linux-gnu/libaio.so.1 \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

# =========================================================
# LOCALES
# =========================================================
RUN echo "en_US.UTF-8 UTF-8" > /etc/locale.gen \
 && echo "fr_FR.UTF-8 UTF-8" >> /etc/locale.gen \
 && locale-gen

# =========================================================
# COMPOSER
# =========================================================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# =========================================================
# ORACLE INSTANT CLIENT
# =========================================================
WORKDIR /opt/oracle

RUN wget https://download.oracle.com/otn_software/linux/instantclient/216000/instantclient-basic-linux.x64-21.6.0.0.0dbru.zip \
 && wget https://download.oracle.com/otn_software/linux/instantclient/216000/instantclient-sdk-linux.x64-21.6.0.0.0dbru.zip \
 && unzip instantclient-basic-linux.x64-21.6.0.0.0dbru.zip \
 && unzip instantclient-sdk-linux.x64-21.6.0.0.0dbru.zip \
 && rm -f *.zip \
 && mv instantclient_21_6 instantclient

ENV ORACLE_HOME=/opt/oracle/instantclient
ENV LD_LIBRARY_PATH=/opt/oracle/instantclient

RUN echo "/opt/oracle/instantclient" > /etc/ld.so.conf.d/oracle-instantclient.conf \
 && ldconfig

# =========================================================
# PHP EXTENSIONS (CORE)
# =========================================================
RUN docker-php-ext-install \
    calendar \
    dom \
    exif \
    ldap \
    mbstring \
    mysqli \
    pdo \
    pdo_mysql \
    opcache \
    xml \
    xsl \
    zip

# =========================================================
# OCI8
# =========================================================
RUN printf "instantclient,/opt/oracle/instantclient\n" | pecl install oci8 \
 && docker-php-ext-enable oci8

# =========================================================
# APCU + IMAGICK
# =========================================================
RUN pecl install apcu \
 && docker-php-ext-enable apcu

# =========================================================
# WORKDIR
# =========================================================
WORKDIR /web/app

# =========================================================
# ENTRYPOINT
# =========================================================
COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/bin/bash", "/entrypoint.sh"]