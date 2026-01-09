---
name: CI/CD Setup для CRM Dental
overview: Налаштування повного CI/CD pipeline з GitHub Actions для автоматичного тестування, білду та деплою Laravel backend + Vue.js frontend на production сервер через Docker та SSH.
todos:
  - id: create-github-actions-deploy
    content: "Створити .github/workflows/deploy.yml з повним pipeline: test → build → deploy"
    status: pending
  - id: create-github-actions-test
    content: Створити .github/workflows/test.yml для тестування PR без деплою
    status: pending
  - id: create-docker-compose-prod
    content: Створити docker-compose.prod.yml для production середовища (опціонально, якщо потрібні відмінності від dev)
    status: pending
  - id: create-deploy-script
    content: Створити scripts/deploy.sh з логікою SSH деплою та перезапуску Docker
    status: pending
  - id: create-backup-script
    content: Створити scripts/backup-db.sh для backup бази перед міграціями
    status: pending
  - id: create-health-check
    content: Створити scripts/health-check.sh для перевірки після деплою
    status: pending
  - id: create-deployment-docs
    content: Створити docs/DEPLOYMENT.md з детальними інструкціями налаштування
    status: pending
  - id: update-readme-cicd
    content: Оновити README.md з інформацією про CI/CD та деплой
    status: pending
  - id: create-env-template
    content: Створити .github/workflows/.env.example з документацією GitHub Secrets
    status: pending
---

# CI/CD Setup для CRM_DENTAL

## Архітектура

```
GitHub Repository (main branch)
  ↓ (push/merge)
GitHub Actions Workflow
  ↓
1. Test (PHPUnit + ESLint)
  ↓
2. Build (Composer install + NPM build)
  ↓
3. Deploy (SSH + Docker Compose)
  ↓
Production Server
```

## Компоненти що будуть створені

### 1. GitHub Actions Workflows

#### `.github/workflows/deploy.yml`

- Тригериться на push до `main` branch
- Запускає тести backend (PHPUnit)
- Запускає lint frontend (ESLint)
- Білдить frontend (npm run build)
- Деплоїть на сервер через SSH
- Перезапускає Docker контейнери

#### `.github/workflows/test.yml`

- Тригериться на pull requests
- Запускає тести без деплою
- Перевіряє code style

### 2. Docker Production Configuration

#### `docker-compose.prod.yml`

- Production версія docker-compose
- Без dev залежностей
- Правильні volumes для production
- Network налаштування

#### `Dockerfile.backend` (опціонально)

- Production образ для Laravel
- Multi-stage build для оптимізації

#### `Dockerfile.frontend` (опціонально)

- Production образ для Nginx з Vue build
- Static файли

### 3. Deployment Scripts

#### `scripts/deploy.sh`

- Скрипт для деплою на сервер (виконується через SSH)
- Виконує `git pull` в існуючому репозиторії
- Перевіряє чи є нові міграції
- Робить backup БД (опціонально)
- Запускає міграції через Docker
- Очищує кеш Laravel
- Перезапускає Docker контейнери

#### `scripts/backup-db.sh`

- Backup бази даних перед деплоєм

### 4. Configuration Files

#### `.github/workflows/.env.example`

- Шаблон змінних середовища
- Документація що потрібно налаштувати

## Потрібні GitHub Secrets

Користувач повинен налаштувати в GitHub Settings → Secrets:

- `DEPLOY_HOST` - IP або домен production сервера
- `DEPLOY_USER` - SSH username (наприклад, `root` або `deploy`)
- `DEPLOY_SSH_KEY` - Приватний SSH ключ для доступу до сервера
- `DEPLOY_PATH` - Шлях на сервері де **вже знаходиться** проект (наприклад, `/var/www/crm-dental` або `/home/deploy/CRM_DENTAL`) - **треба перевірити на сервері**
- `DEPLOY_PORT` - SSH порт (зазвичай 22)

## Структура файлів що будуть створені

```
.github/
  workflows/
    deploy.yml          # Main deployment workflow
    test.yml            # Test workflow for PRs
    .env.example        # Environment variables template

docker-compose.prod.yml # Production Docker Compose
scripts/
  deploy.sh             # Deployment script
  backup-db.sh          # Database backup script
  health-check.sh       # Health check after deploy

docs/
  DEPLOYMENT.md         # Deployment documentation
```

## Кроки виконання

1. **Створити GitHub Actions workflows**

   - `.github/workflows/deploy.yml` - основний workflow
   - `.github/workflows/test.yml` - тести для PR

2. **Створити production Docker конфігурацію**

   - `docker-compose.prod.yml` для production
   - Опціональні Dockerfile для оптимізації

3. **Створити deployment scripts**

   - `scripts/deploy.sh` - автоматичний деплой
   - `scripts/backup-db.sh` - backup перед деплоєм

4. **Налаштувати GitHub Secrets** (інструкції в документації)

5. **Створити документацію**

   - `docs/DEPLOYMENT.md` - детальні інструкції
   - Оновити `README.md` з інформацією про CI/CD

## Важливі примітки про існуючий сервер

**На сервері вже розгорнуто:**

- ✅ Docker Compose (як на локальній машині)
- ✅ Проект працює
- ✅ Оновлення зараз вручну через `git pull`
- ⚠️ Немає останніх оновлень з сьогодні

**Що робити:**

- Автоматизувати процес `git pull` + перезапуск контейнерів
- Додати автоматичні міграції після pull
- Не перестворювати проект з нуля, використати існуючий

## Особливості реалізації

### Backend Deployment

- **Використовує існуючий `docker-compose.yml`** на сервері
- Підключається через SSH до існуючого проекту
- Виконує `git pull origin main` в директорії проекту
- Запускає міграції (якщо є нові): `docker compose exec laravel.test php artisan migrate --force`
- Очищує кеш: `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`
- Перезапускає контейнери: `docker compose up -d --build` (якщо потрібно)

### Frontend Deployment

- Білдиться локально в GitHub Actions: `npm run build`
- Збілджені файли можуть копіюватися на сервер, або
- Білдиться безпосередньо в Docker контейнері на сервері (якщо є volume)
- Залежить від налаштування Nginx в Docker

### Database

- Backup перед міграціями (опціонально, через `scripts/backup-db.sh`)
- Міграції запускаються автоматично тільки якщо є нові файли
- Seeders НЕ запускаються автоматично (безпека)

### Процес деплою (спрощений під існуючий сервер)

1. GitHub Actions підключається через SSH
2. Переходить в директорію проекту (`DEPLOY_PATH`)
3. Виконує `git fetch && git pull origin main`
4. Перевіряє чи є нові міграції
5. Якщо є - робить backup БД
6. Запускає міграції через Docker
7. Очищує кеш Laravel
8. Перезапускає контейнери (якщо змінився код)
9. Health check для перевірки

### Zero-Downtime Deployment

- Перевірка health check перед перемиканням
- Blue-green deployment (опціонально, для майбутнього)

## Безпека

- Всі секрети в GitHub Secrets, не в коді
- SSH ключі з обмеженими правами
- `.env` файл не комітиться
- Health checks для перевірки після деплою

## Моніторинг після деплою

- Health check endpoint (`/api/health`)
- Автоматичне повідомлення про успішний/невдалий деплой
- Логування в GitHub Actions

## Наступні кроки після створення

1. **Перевірити на сервері:**

   - Шлях до проекту (виконати `pwd` в директорії проекту)
   - Чи працює `docker compose` (перевірити `docker compose ps`)
   - SSH доступ з GitHub Actions (перевірити ключ)

2. **Налаштувати GitHub Secrets:**

   - `DEPLOY_HOST` - IP або домен сервера
   - `DEPLOY_USER` - SSH username
   - `DEPLOY_SSH_KEY` - Приватний SSH ключ
   - `DEPLOY_PATH` - Шлях до проекту (після перевірки на сервері)
   - `DEPLOY_PORT` - SSH порт (зазвичай 22)

3. **Перший тестовий деплой:**

   - Створити тестовий commit
   - Push до `main` branch
   - Перевірити логі в GitHub Actions
   - Перевірити що проект працює на сервері

4. **Перевірити оновлення:**

   - Після успішного деплою перевірити що всі зміни з сьогодні з'явилися на сервері