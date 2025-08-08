# Używamy oficjalnego obrazu PHP 8.4 jako bazy
FROM php:8.4-fpm-alpine

# Ustawiamy katalog roboczy wewnątrz kontenera
WORKDIR /var/www/html

# Krok 1: Instalujemy Node.js i poprawną wersję npm
RUN apk add --no-cache nodejs npm && \
    npm install -g npm@10

# Krok 2: Instalujemy pakiety systemowe potrzebne do kompilacji rozszerzeń PHP
# Usunięto 'imap-dev' z tej listy
RUN apk add --no-cache \
    $PHPIZE_DEPS \
    libzip-dev \
    libpng-dev \
    jpeg-dev \
    freetype-dev \
    libwebp-dev \
    postgresql-dev \
    libxml2-dev \
    icu-dev \
    libxslt-dev \
    tidyhtml-dev

# Krok 3: Konfigurujemy i instalujemy rozszerzenia PHP
# Usunięto 'imap' z tej listy
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    gd \
    intl \
    zip \
    soap \
    xsl \
    pcntl \
    tidy \
    exif

# Krok 4: Kopiujemy nasz niestandardowy plik konfiguracyjny PHP
COPY docker/php/oro.ini /usr/local/etc/php/conf.d/oro-custom.ini

# Krok 5: Instalujemy Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Krok 6: Dodajemy dedykowanego użytkownika 'www'
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S www -G www

# Przełączamy się na nowo utworzonego użytkownika
USER www