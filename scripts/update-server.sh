#!/bin/bash

# Скрипт для оновлення проекту на сервері
# Використання: ./scripts/update-server.sh

set -e  # Зупинитися при помилці

echo "🚀 Початок оновлення проекту..."

# Кольори для виводу
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Перевірка чи знаходимось в правильній директорії
if [ ! -f "dental-crm-api/docker-compose.yml" ]; then
    echo -e "${RED}❌ Помилка: Не знайдено docker-compose.yml${NC}"
    echo "Переконайтеся що ви в кореневій директорії проекту"
    exit 1
fi

# ============================================
# BACKEND ОНОВЛЕННЯ (Docker)
# ============================================
echo -e "\n${YELLOW}📦 Оновлення Backend (Laravel в Docker)...${NC}"

cd dental-crm-api

# Перевірка чи запущені контейнери
if ! docker compose ps | grep -q "laravel.test.*Up"; then
    echo -e "${YELLOW}⚠️  Контейнери не запущені, запускаю...${NC}"
    docker compose up -d
    sleep 5  # Дати час контейнерам запуститися
fi

# Встановлення залежностей Composer
echo "📥 Встановлення Composer залежностей..."
docker compose exec -T laravel.test composer install --no-interaction --prefer-dist --optimize-autoloader

# Запуск міграцій (якщо є нові)
echo "🔄 Перевірка та запуск міграцій..."
docker compose exec -T laravel.test php artisan migrate --force

# Очищення кешу Laravel
echo "🧹 Очищення кешу..."
docker compose exec -T laravel.test php artisan config:clear
docker compose exec -T laravel.test php artisan cache:clear
docker compose exec -T laravel.test php artisan route:clear
docker compose exec -T laravel.test php artisan view:clear

# Створення оптимізованого кешу для production
echo "⚡ Створення оптимізованого кешу..."
docker compose exec -T laravel.test php artisan config:cache
docker compose exec -T laravel.test php artisan route:cache
docker compose exec -T laravel.test php artisan view:cache

# Створення symlink для storage (якщо не існує)
echo "🔗 Перевірка symlink для storage..."
docker compose exec -T laravel.test php artisan storage:link || echo "Symlink вже існує"

# Перезапуск контейнерів для застосування змін
echo "♻️  Перезапуск контейнерів..."
docker compose restart laravel.test

# Перевірка PHP налаштувань для завантаження файлів (для діагностики)
echo "📋 Перевірка PHP налаштувань завантаження файлів..."
docker compose exec -T laravel.test php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL; echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;" || echo "⚠️  Не вдалося перевірити PHP налаштування"

cd ..

# ============================================
# FRONTEND ОНОВЛЕННЯ
# ============================================
echo -e "\n${YELLOW}🎨 Оновлення Frontend (Vue.js)...${NC}"

cd dental-crm-frontend

# Встановлення залежностей
echo "📥 Встановлення npm залежностей..."
npm ci --production=false  # npm ci для чистої установки

# Білд production версії
echo "🏗️  Білд production версії..."
npm run build

echo -e "${GREEN}✅ Frontend збілджено успішно!${NC}"

cd ..

# ============================================
# ПІДСУМОК
# ============================================
echo -e "\n${GREEN}✨ Оновлення завершено успішно!${NC}"
echo -e "\n📋 Перевірте:"
echo "  - Backend доступний: http://your-server"
echo "  - Frontend файли в: dental-crm-frontend/dist/"
echo "  - Логи: docker compose logs -f (в dental-crm-api/)"
