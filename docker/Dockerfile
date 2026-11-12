# PHP 8.4 image used to run the checks and the test suite, with every
# extension the framework and its modules can use, and Composer.
#
# docker/compose.yml builds it and adds the database and cache servers:
#
#   docker compose -f docker/compose.yml run --rm php docker/check.sh
#
# On its own (tests that need a server are skipped):
#
#   docker build -t kohana-test:8.4 docker/
#   docker run --rm -v "$PWD":/app -w /app kohana-test:8.4 vendor/bin/phpunit
FROM php:8.4-cli

# Composer installs PHPUnit, PHP_CodeSniffer and PHPStan into vendor/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Libraries for the extensions, plus the en_US locale the tests expect.
# The two libheif plugins let ImageMagick write AVIF and HEIC, not only read them.
RUN apt-get update -q \
 && apt-get install -y -q --no-install-recommends locales libicu-dev \
    libpng-dev libjpeg-dev libwebp-dev libavif-dev libfreetype6-dev \
    libmagickwand-dev libheif-plugin-aomenc libheif-plugin-x265 \
    libmemcached-dev zlib1g-dev libssl-dev libpq-dev libzip-dev unzip \
 && echo 'en_US.UTF-8 UTF-8' > /etc/locale.gen && locale-gen \
 && rm -rf /var/lib/apt/lists/*

# Extensions that come with PHP. exif is needed by Image::auto_orient().
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-avif --with-freetype \
 && (docker-php-ext-configure ftp --with-ftp-ssl || docker-php-ext-configure ftp --with-openssl-dir=/usr) \
 && docker-php-ext-install -j4 intl gd exif mysqli pdo_mysql pdo_pgsql zip ftp

# Extensions from PECL: the cache drivers, and PCOV to measure which lines
# the tests reach (phpunit --coverage-text). APCu is switched on for the
# command line, where the tests run.
#
# The Imagick image driver is built by hand. Its own build wants to make
# some files again and downloads a tool from GitHub for that; "touch"
# tells it that the files it ships are current, so nothing is downloaded.
RUN pecl install apcu redis memcached pcov </dev/null \
 && cd /tmp && pecl download imagick && tar xzf imagick-*.tgz && cd imagick-*/ \
 && phpize && ./configure > /dev/null && touch *_arginfo.h && make -j4 > /dev/null && make install \
 && cd / && rm -rf /tmp/imagick-* \
 && docker-php-ext-enable apcu redis memcached imagick pcov \
 && echo 'apc.enable_cli=1' > /usr/local/etc/php/conf.d/apcu-cli.ini \
 && printf 'pcov.directory=.\npcov.exclude="~/vendor/~"\n' > /usr/local/etc/php/conf.d/pcov.ini \
 && rm -rf /tmp/pear
