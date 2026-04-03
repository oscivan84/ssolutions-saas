FROM php:8.2-apache

# Extensiones PHP requeridas
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

# Copiar proyecto a la raíz web de Apache (no en subdirectorio)
COPY . /var/www/html/

# Script de inicio: configura puerto dinámico + .env + arranca Apache
RUN printf '#!/bin/bash\n\
# Crear .env desde variables de entorno\n\
cat > /var/www/html/.env << ENVEOF\n\
DB_HOST=${DB_HOST:-localhost}\n\
DB_USER=${DB_USER:-root}\n\
DB_PASS=${DB_PASS:-}\n\
DB_NAME=${DB_NAME:-railway}\n\
DB_PORT=${DB_PORT:-3306}\n\
ENVEOF\n\
\n\
# Railway asigna PORT dinámicamente\n\
LISTEN_PORT=${PORT:-80}\n\
sed -i "s/Listen 80/Listen $LISTEN_PORT/" /etc/apache2/ports.conf\n\
sed -i "s/:80/:$LISTEN_PORT/" /etc/apache2/sites-available/000-default.conf\n\
\n\
exec apache2-foreground\n' > /start.sh && chmod +x /start.sh

# Apache: permitir .htaccess y acceso
RUN printf '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' > /etc/apache2/conf-available/custom.conf \
    && a2enconf custom

CMD ["/start.sh"]
