FROM php:8.2-apache

# Extensiones PHP requeridas
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

# Copiar proyecto
COPY . /var/www/html/landingV2/

# Crear .env desde variables de entorno (se setean en Railway/Render)
RUN echo '#!/bin/bash\n\
echo "DB_HOST=$DB_HOST" > /var/www/html/landingV2/.env\n\
echo "DB_USER=$DB_USER" >> /var/www/html/landingV2/.env\n\
echo "DB_PASS=$DB_PASS" >> /var/www/html/landingV2/.env\n\
echo "DB_NAME=$DB_NAME" >> /var/www/html/landingV2/.env\n\
apache2-foreground' > /start.sh && chmod +x /start.sh

# Apache config
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/custom.conf \
    && a2enconf custom

EXPOSE 80

CMD ["/start.sh"]
