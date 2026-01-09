# Виправлення помилки 413 (Request Entity Too Large)

Помилка `413 Request Entity Too Large` виникає коли файл занадто великий для завантаження. Це може бути обмеження в Nginx або PHP.

## Рішення для Docker (Laravel Sail)

### 1. Створені файли конфігурації

Був створений файл:
- `dental-crm-api/docker/php/local.ini` - конфігурація PHP з `upload_max_filesize = 10M` та `post_max_size = 10M`

Файл автоматично підключається через volume в `docker-compose.yml`.

**Примітка:** Laravel Sail використовує вбудований Nginx з обмеженням 1MB. Для зміни обмеження Nginx потрібно створити кастомний Dockerfile або використати альтернативний спосіб (див. нижче).

### 2. Застосування змін

Після додавання цих файлів, перезапустіть Docker контейнери:

```bash
cd dental-crm-api

# Перебілдити контейнери щоб застосувати нові volumes
docker compose down
docker compose up -d --build

# Або просто перезапустити
docker compose restart
```

### 3. Виправлення обмеження Nginx в Laravel Sail

Laravel Sail має обмеження Nginx в 1MB. Є два варіанти:

#### Варіант А: Кастомний Dockerfile (рекомендовано)

Створіть `dental-crm-api/Dockerfile`:

```dockerfile
FROM ubuntu:22.04

LABEL maintainer="Taylor Otwell"

ARG WWWGROUP
ARG NODE_VERSION=20
ARG POSTGRES_VERSION=15

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14AA40EC0831756756D7F66C4F4EA0AAE5267A6C' | gpg --dearmor | tee /usr/share/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/usr/share/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu jammy main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y php8.3-cli php8.3-dev php8.3-pgsql php8.3-sqlite3 php8.3-gd php8.3-imagick php8.3-memcached php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-soap php8.3-intl php8.3-readline php8.3-ldap php8.3-msgpack php8.3-igbinary php8.3-redis php8.3-swoole php8.3-redis php8.3-xdebug \
    && php -r "readfile('https://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -sLS https://get.arkade.dev | sh \
    && mv /root/.arkade/bin/arkade /usr/local/bin/arkade \
    && ln -sf /usr/share/zoneinfo/UTC /etc/localtime \
    && apt-get install -y mysql-client \
    && apt-get install -y postgresql-client-$POSTGRES_VERSION \
    && apt-get -y autoremove \
    && apt-get -y clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN apt-get update \
    && apt-get install -y nginx \
    && apt-get -y autoremove \
    && apt-get -y clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Налаштування Nginx з збільшеним client_max_body_size
RUN echo "client_max_body_size 10M;" >> /etc/nginx/nginx.conf

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.3

RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.3/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80

ENTRYPOINT ["start-container"]
```

І скопіюйте потрібні файли з `vendor/laravel/sail/runtimes/8.3/`.

#### Варіант Б: Змінити безпосередньо в контейнері (тимчасове рішення)

```bash
# Ввійти в контейнер
docker compose exec laravel.test bash

# Редагувати nginx.conf
sed -i 's/client_max_body_size 1m;/client_max_body_size 10M;/' /etc/nginx/nginx.conf

# Перезапустити Nginx
service nginx reload

# Вийти
exit
```

**Увага:** Зміни зникнуть після перебілду контейнера!

### 4. Перевірка налаштувань

Перевірте що налаштування застосувалися:

```bash
# Перевірити PHP налаштування
docker compose exec laravel.test php -i | grep upload_max_filesize
docker compose exec laravel.test php -i | grep post_max_size

# Перевірити Nginx (якщо доступно)
docker compose exec laravel.test nginx -t 2>/dev/null || echo "Nginx config check не доступний"
```

Очікуваний результат:
- `upload_max_filesize => 10M`
- `post_max_size => 10M`

## Якщо використовується власний Nginx (не в Docker)

Якщо на сервері використовується окремий Nginx, додайте в конфігурацію:

```nginx
server {
    # ... інші налаштування ...
    
    # Збільшити максимальний розмір завантаження
    client_max_body_size 10M;
    
    location ~ \.php$ {
        # ... налаштування PHP-FPM ...
        
        # Додати для великих завантажень
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }
}
```

І перезапустити Nginx:
```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Налаштування PHP на сервері (якщо не Docker)

Якщо використовується традиційна установка PHP-FPM, відредагуйте `php.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
```

Знайти `php.ini`:
```bash
php --ini
```

Після змін перезапустити PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm  # або ваша версія PHP
```

## Налаштування в Laravel

В `UserAvatarController.php` вже встановлено валідацію `max:4096` (4MB). Якщо потрібно збільшити:

```php
'avatar' => ['nullable', 'image', 'max:10240'], // 10MB в KB
```

## Перевірка після виправлення

1. Перезапустіть контейнери
2. Спробуйте завантажити аватар знову
3. Перевірте логи якщо все ще є помилки:

```bash
# Логи Laravel
docker compose logs laravel.test

# Логи Nginx (якщо доступні)
docker compose exec laravel.test tail -f /var/log/nginx/error.log
```

## Додаткові налаштування (опціонально)

Якщо потрібно завантажувати ще більші файли, змініть значення в обох файлах:
- `docker/nginx/default.conf`: `client_max_body_size 20M;`
- `docker/php/php.ini`: `upload_max_filesize = 20M` та `post_max_size = 20M`

Пам'ятайте: `post_max_size` повинен бути >= `upload_max_filesize`.
