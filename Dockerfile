FROM php:8.2-apache

# Extensiones PHP requeridas
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

# Copiar proyecto
COPY . /var/www/html/landingV2/

# Script de inicio: crea .env desde variables de entorno + arranca Apache
RUN echo '#!/bin/bash\n\
echo "DB_HOST=$DB_HOST" > /var/www/html/landingV2/.env\n\
echo "DB_USER=$DB_USER" >> /var/www/html/landingV2/.env\n\
echo "DB_PASS=$DB_PASS" >> /var/www/html/landingV2/.env\n\
echo "DB_NAME=$DB_NAME" >> /var/www/html/landingV2/.env\n\
echo "DB_PORT=$DB_PORT" >> /var/www/html/landingV2/.env\n\
# Railway usa PORT para el puerto del servidor web\n\
sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf\n\
sed -i "s/:80/:${PORT:-80}/" /etc/apache2/sites-available/000-default.conf\n\
apache2-foreground' > /start.sh && chmod +x /start.sh

# Apache config
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/custom.conf \
    && a2enconf custom

EXPOSE ${PORT:-80}

CMD ["/start.sh"]
