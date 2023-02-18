FROM composer:lts as builder

WORKDIR /app

# Update aptitude with new repo
RUN apk update

# Install software
RUN apk add git

RUN git clone https://github.com/decima/Linker.git .

RUN composer install

FROM php:8.2-fpm-alpine

RUN apk update && apk add nginx && apk add supervisor

RUN mkdir -p /var/log/supervisor

COPY ./supervisord.conf /etc/supervisor/supervisord.conf

COPY ./default.conf /etc/nginx/http.d/default.conf

COPY --from=builder /app /var/www

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]