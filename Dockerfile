FROM php:8.2-fpm-bullseye

RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    libzip-dev \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    nano \
    libpq-dev \
    libreoffice \
    qpdf

# Generate Indonesian locale
RUN sed -i '/id_ID.UTF-8/s/^# //g' /etc/locale.gen && locale-gen id_ID.UTF-8 && update-locale LANG=id_ID.UTF-8

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql zip exif pcntl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm cache clean --force

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Change user for laravel application
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

COPY fonts/bookman /usr/local/share/fonts/truetype/bookman
COPY code /var/www/html

# Copy existing application directory permissions
COPY --chown=www:www code /var/www/html

# Change current user to www
USER www

WORKDIR /var/www/html

#RUN composer dump-autoload --optimize

#RUN composer install --no-interaction

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]

