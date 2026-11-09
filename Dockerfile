FROM node:22-alpine AS panel
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npx vite build --outDir /build --emptyOutDir

FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql && rm -rf /var/lib/apt/lists/*
RUN a2enmod setenvif headers
COPY ops/apache.conf /etc/apache2/sites-available/000-default.conf
COPY backend/ /var/www/soportix/
COPY --from=panel /build/ /var/www/soportix/public/
RUN rm -rf /var/www/soportix/tests /var/www/soportix/bin/seed-demo.php /var/www/soportix/data
COPY ops/entrypoint.sh /usr/local/bin/soportix-entrypoint
RUN chmod +x /usr/local/bin/soportix-entrypoint
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s CMD curl -fs http://localhost/api/health || exit 1
CMD ["soportix-entrypoint"]
