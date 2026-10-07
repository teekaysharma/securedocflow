FROM php:8.2.29-apache
MAINTAINER Logical Arts, LLC <info@logicalarts.net>

ENV APACHE_DOCUMENT_ROOT /var/www/html/public

# Double-quoted -e argument: this must expand $APACHE_DOCUMENT_ROOT to its real
# value (/var/www/html/public) at build time, baking the literal path into the
# config. A single-quoted argument (the previous version of this line) leaves
# the literal, unexpanded text "${APACHE_DOCUMENT_ROOT}" in DocumentRoot --
# Apache can't resolve that itself at startup, DocumentRoot ends up empty, and
# apache2 refuses to start at all ("DocumentRoot takes one argument"). Verified
# by building both variants in isolation.
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf

# Install packages
RUN apt-get update \
  && apt-get install --no-install-recommends -y apt-utils vim git openssl ssl-cert default-mysql-client msmtp msmtp-mta \
  && docker-php-ext-install pdo_mysql pdo \
  && rm -rf /var/lib/apt/lists/*

# Copy php configs
#COPY src/main/resources/docker-php-pecl-install /usr/local/bin/
#RUN docker-php-pecl-install xdebug-2.3.3
#COPY src/main/resources/xdebug.ini ${PHP_INI_DIR}/conf.d/docker-php-pecl-xdebug.ini
COPY src/main/resources/php.ini /usr/local/etc/php/conf.d

# Install mod_rewrite
RUN a2enmod rewrite
RUN a2ensite default-ssl
RUN a2enmod ssl

# Copy application files
COPY . /var/www/html

# Change file permissions
RUN usermod -u 1000 www-data

# Create and set proper ownership and permissions for application directories.
# templates_c is Smarty's compiled-template cache -- correctly gitignored (it's
# runtime-generated, not source), but that means a fresh clone has no such
# directory for COPY . to bring in, so it must be created here explicitly like
# the other two, not just chown'd/chmod'd as if it already existed.
RUN mkdir -p /var/www/document_repository \
    && mkdir -p /var/www/html/application/configs/docker-configs \
    && mkdir -p /var/www/html/application/templates_c \
    && chown -R www-data:www-data /var/www/html/application/templates_c \
    && chown -R www-data:www-data /var/www/html/application/configs \
    && chown -R www-data:www-data /var/www/document_repository \
    && chmod -R 755 /var/www/html/application/templates_c \
    && chmod -R 755 /var/www/html/application/configs \
    && chmod -R 755 /var/www/document_repository

# Copy startup command and mail configuration scripts
COPY src/main/resources/*.sh /
RUN chmod 755 /*.sh





EXPOSE 80 443

# By default, simply start apache.
ENTRYPOINT ["/start.sh"]
