# Use an official PHP image with Apache
FROM php:8.1-apache

# Install system dependencies required for PostgreSQL PHP extensions
# libpq-dev provides the necessary libraries for pgsql and pdo_pgsql
RUN apt-get update && apt-get install -y libpq-dev \
    # Clean up apt cache to reduce image size
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required for PostgreSQL.
# pdo_pgsql is the PDO driver for PostgreSQL.
# pgsql is the non-PDO PostgreSQL extension (often installed alongside pdo_pgsql).
# Keep mysqli and pdo_mysql if your application also connects to MySQL,
# otherwise, remove them if PostgreSQL is your only database.
RUN docker-php-ext-install mysqli pdo pdo_mysql pdo_pgsql pgsql \
    && docker-php-ext-enable mysqli pdo_mysql pdo_pgsql pgsql

# Enable Apache's mod_rewrite (needed for clean URLs if you use .htaccess)
RUN a2enmod rewrite

# Copy your application code into the Apache web root
# Your project root (Movieticketbooking) goes into /var/www/html/
COPY . /var/www/html/

# Configure Apache to serve index.php first and to respect .htaccess
# This might already be default, but ensures consistency
# Assuming apache-config.conf correctly sets up your virtual host
COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf

# Expose port 80 (Apache's default port)
EXPOSE 80

# Command to run Apache web server
CMD ["apache2-foreground"]