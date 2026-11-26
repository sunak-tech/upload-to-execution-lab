FROM php:8.3-apache

COPY apache/ports.conf /etc/apache2/ports.conf
COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY apache/lab-php.ini /usr/local/etc/php/conf.d/zz-lab-php.ini

COPY src/ /var/www/html/
COPY storage/ /var/lab-storage/uploads/

RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2 /var/www/html/uploads /var/lab-storage/uploads \
    && chown -R root:root /var/www/html \
    && chmod -R u=rwX,go=rX /var/www/html \
    && chmod 0644 /var/www/html/uploads/infra-guard-check.phtml \
    && chown -R www-data:www-data /var/lab-storage/uploads /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
    && chown root:root /var/lab-storage/uploads/.gitkeep \
    && chmod 0750 /var/lab-storage/uploads \
    && chmod 0644 /var/lab-storage/uploads/.gitkeep

EXPOSE 8080
USER www-data
