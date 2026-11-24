# ── Stage 1: PHP + Composer dependencies ──────────────────────────────────────
FROM php:8.3-fpm-bookworm AS composer-deps

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl libpq-dev libzip-dev libpng-dev libjpeg-dev \
    libfreetype6-dev libwebp-dev libicu-dev libonig-dev libxml2-dev \
    libssl-dev libcurl4-openssl-dev pkg-config \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo pdo_pgsql pgsql opcache \
        zip mbstring xml curl bcmath intl gd sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/nexvary-cx

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --quiet

# ── Stage 2: Node.js / Vite build ─────────────────────────────────────────────
FROM node:22-bookworm-slim AS node-build

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --quiet

COPY . .
# Copy vendor from previous stage so Vite can reference PHP routes if needed
COPY --from=composer-deps /var/www/nexvary-cx/vendor ./vendor
RUN npm run build

# ── Stage 3: Production image ──────────────────────────────────────────────────
FROM php:8.3-fpm-bookworm AS production

LABEL org.opencontainers.image.title="NEXVARY CX Cloud — Laravel App"
LABEL org.opencontainers.image.vendor="NEXVARY"

# Install runtime libs only — extensions were compiled in composer-deps stage
# and are copied below. No -dev headers needed here.
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq5 libzip4 libpng16-16 libjpeg62-turbo libfreetype6 libwebp7 \
    libicu72 libonig5 libxml2 libssl3 libcurl4 \
    curl gettext-base netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

# Copy compiled PHP extensions from the builder stage
COPY --from=composer-deps /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=composer-deps /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# PHP config
COPY docker/app/conf/php.ini /usr/local/etc/php/conf.d/nexvary.ini
COPY docker/app/conf/php-fpm.conf /usr/local/etc/php-fpm.d/nexvary-cx.conf

RUN useradd --system --uid 1001 --no-create-home --shell /sbin/nologin nexvary \
    # Shared GID 2000 (nexvary-pbx) must match docker/freeswitch/Dockerfile.
    # Membership lets the nexvary user write to directories the FS container
    # sets to mode 2770 root:nexvary-pbx — no world-writable dirs needed.
    && groupadd -g 2000 nexvary-pbx \
    && usermod -aG nexvary-pbx nexvary

WORKDIR /var/www/nexvary-cx

# Copy application source
COPY --chown=nexvary:nexvary . .

# Copy built assets from node stage
COPY --chown=nexvary:nexvary --from=node-build /build/public/build ./public/build

# Copy vendor from composer stage
COPY --chown=nexvary:nexvary --from=composer-deps /var/www/nexvary-cx/vendor ./vendor

# Storage directories (must be writable; actual data lives in named volume)
RUN mkdir -p storage/app/public storage/framework/{cache,sessions,views} \
        storage/logs bootstrap/cache \
    && chown -R nexvary:nexvary storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Shared public assets volume mount point (nginx reads from here via named volume)
RUN mkdir -p /var/www/nexvary-cx-public && chown nexvary:nexvary /var/www/nexvary-cx-public

COPY --chmod=755 docker/app/entrypoint.sh /entrypoint.sh

USER nexvary
EXPOSE 9000
HEALTHCHECK --interval=20s --timeout=5s --start-period=60s --retries=3 \
    CMD nc -z 127.0.0.1 9000 || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm", "--nodaemonize"]
