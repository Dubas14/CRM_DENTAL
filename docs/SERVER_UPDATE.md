# Інструкція з оновлення на сервері

Ця інструкція описує як оновити проект на production сервері після `git pull`.

## Швидке оновлення (автоматичний скрипт)

```bash
# З кореневої директорії проекту
chmod +x scripts/update-server.sh
./scripts/update-server.sh
```

## Ручне оновлення (покроково)

### 1. Backend (Laravel в Docker)

```bash
cd dental-crm-api

# Перевірити що контейнери запущені
docker compose ps

# Якщо не запущені - запустити
docker compose up -d

# Встановити залежності Composer
docker compose exec laravel.test composer install --no-interaction --prefer-dist --optimize-autoloader

# Запустити міграції (якщо є нові)
docker compose exec laravel.test php artisan migrate --force

# Очистити кеш
docker compose exec laravel.test php artisan config:clear
docker compose exec laravel.test php artisan cache:clear
docker compose exec laravel.test php artisan route:clear
docker compose exec laravel.test php artisan view:clear

# Створити оптимізований кеш для production
docker compose exec laravel.test php artisan config:cache
docker compose exec laravel.test php artisan route:cache
docker compose exec laravel.test php artisan view:cache

# Перевірити symlink для storage (для завантаження файлів)
docker compose exec laravel.test php artisan storage:link

# Перезапустити контейнери
docker compose restart laravel.test
```

### 2. Frontend (Vue.js)

```bash
cd dental-crm-frontend

# Встановити залежності (npm ci - чиста установка з package-lock.json)
npm ci

# Або якщо немає package-lock.json
npm install

# Збілдити production версію
npm run build

# Збілджені файли будуть в директорії dist/
# Їх потрібно налаштувати в Nginx або вашому веб-сервері
```

### 3. Перевірка після оновлення

```bash
# Перевірити логи контейнерів
cd dental-crm-api
docker compose logs -f laravel.test

# Перевірити що API працює
curl http://localhost/api/health

# Перевірити помилки
docker compose exec laravel.test php artisan about
```

## Важливі зауваження

### Backup перед міграціями

Якщо є важливі зміни в міграціях, зробіть backup БД:

```bash
cd dental-crm-api

# Backup PostgreSQL
docker compose exec pgsql pg_dump -U ${DB_USERNAME} ${DB_DATABASE} > backup_$(date +%Y%m%d_%H%M%S).sql

# Або через docker exec безпосередньо
docker compose exec -T pgsql pg_dump -U postgres crm_dental > backup.sql
```

### Якщо змінився .env файл

Якщо в `dental-crm-api/.env` були зміни:

1. Оновіть `.env` файл на сервері
2. Очистіть кеш конфігурації:
   ```bash
   docker compose exec laravel.test php artisan config:clear
   docker compose exec laravel.test php artisan config:cache
   ```

### Якщо змінилися Docker образы

Якщо були зміни в `docker-compose.yml` або залежностях:

```bash
cd dental-crm-api

# Перебілдити образи
docker compose build --no-cache

# Перезапустити контейнери
docker compose up -d
```

### Розміщення Frontend файлів

Після `npm run build`, файли з `dental-crm-frontend/dist/` потрібно налаштувати в Nginx:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/CRM_DENTAL/dental-crm-frontend/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # API проксі
    location /api {
        proxy_pass http://localhost:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

## Troubleshooting

### Помилка "Container not found"

```bash
cd dental-crm-api
docker compose up -d
```

### Помилка з правами доступу

```bash
# Налаштувати права для storage
docker compose exec laravel.test chown -R www-data:www-data storage bootstrap/cache
docker compose exec laravel.test chmod -R 775 storage bootstrap/cache
```

### Помилка "Migration already ran"

Це нормально, міграції запускаються тільки якщо є нові. Використовуйте `--force` для обходу підтвердження.

### Frontend не оновлюється

Перевірте:
1. Чи правильно налаштований Nginx на `dist/` директорію
2. Очистіть кеш браузера (Ctrl+Shift+R)
3. Перевірте що `npm run build` завершився без помилок
