# CRM Dental - Система управління стоматологічною клінікою

Повнофункціональна CRM система для управління стоматологічними клініками з розширеним календарем, записами пацієнтів, медичними картками та аналітикою.

## 🏗️ Архітектура проекту

```
CRM_DENTAL/
├── dental-crm-api/          # Backend (Laravel 12)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/  # API контролери
│   │   │   ├── Requests/     # Form Request класи
│   │   │   └── Resources/    # API Resources
│   │   ├── Models/           # Eloquent моделі
│   │   ├── Services/         # Бізнес-логіка
│   │   │   ├── Calendar/     # Календар та слоти
│   │   │   ├── Finance/      # Рахунки та платежі
│   │   │   ├── Access/       # Контроль доступу
│   │   │   └── Notifications/# Нотифікації
│   │   ├── Exceptions/       # Кастомні винятки
│   │   └── Traits/           # Reusable traits
│   ├── database/
│   │   ├── migrations/       # Міграції БД
│   │   ├── factories/        # Фабрики для тестів
│   │   └── seeders/          # Сідери
│   ├── tests/
│   │   ├── Feature/          # Інтеграційні тести
│   │   └── Unit/             # Юніт тести
│   └── docs/                 # Документація
│
└── dental-crm-frontend/      # Frontend (Vue 3 + TypeScript)
    ├── src/
    │   ├── components/       # Vue компоненти
    │   ├── features/         # Feature modules (finance, etc.)
    │   ├── views/            # Сторінки
    │   ├── services/         # API клієнти
    │   ├── composables/      # Vue composables
    │   ├── stores/           # Pinia stores
    │   ├── ui/               # UI kit (абстракція для заміни)
    │   └── router/           # Vue Router
    └── public/
```

## 🚀 Технології

### Backend
- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: PostgreSQL 15
- **Cache**: Redis
- **Search**: Meilisearch
- **Analytics**: ClickHouse
- **Authentication**: Laravel Sanctum
- **Authorization**: Spatie Permission
- **Queue**: Redis

### Frontend
- **Framework**: Vue 3 (Composition API)
- **Language**: TypeScript
- **Build Tool**: Vite (Rolldown)
- **Styling**: Tailwind CSS
- **State Management**: Pinia
- **HTTP Client**: Axios
- **UI Components**: Кастомні компоненти + кастомний календар
- **Table Library**: TanStack Table (headless)
- **UI Kit**: Custom abstraction layer (готовий до заміни на Ant Design Vue / Naive UI)

## 📋 Основні можливості

### 👥 Управління пацієнтами
- Реєстрація та профілі пацієнтів
- Медичні картки з історією візитів
- Зубна формула (dental map)
- Нотатки та коментарі
- Пошук та фільтрація

### 📅 Календар та записи
- Інтелектуальний календар з доступними слотами
- Автоматична перевірка конфліктів
- Багаторівневий розклад (лікар/кабінет/обладнання)
- Перенесення та скасування записів
- Серійні записи (багатоетапні процедури)
- Список очікування з автоматичними пропозиціями

### 🏥 Управління клінікою
- Багато клінік в одній системі
- Управління лікарями та асистентами
- Кабінети та обладнання
- Процедури з етапами
- Робочі години та винятки

### 💰 Фінанси та рахунки
- Створення та редагування рахунків
- Прив'язка послуг до рахунків
- Оплата рахунків (готівка, картка, банківський переказ)
- Знижки (відсоткові та фіксовані)
- Повернення коштів з аудитом
- Терміни оплати та контроль прострочених платежів
- Фінансова статистика по клініці
- Doctor Scope (лікарі бачать тільки свої рахунки)

### 🔐 Безпека та права доступу
- Ролі: super_admin, clinic_admin, doctor, registrar
- Детальний контроль доступу
- Audit logging всіх змін
- Rate limiting API

### 📊 Аналітика
- Завантаженість лікарів
- No-show rate
- Статистика по процедурах
- Фінансова звітність (борги, оплати, топ боржників)

## 🛠️ Встановлення

### Вимоги
- PHP 8.2+
- Composer
- Node.js 18+
- PostgreSQL 15+
- Redis
- Docker (опціонально)

### Backend Setup

```bash
cd dental-crm-api

# Встановити залежності
composer install

# Створити .env файл
cp .env.example .env

# Згенерувати ключ
php artisan key:generate

# Налаштувати БД в .env, потім:
php artisan migrate --seed

# Запустити сервер
php artisan serve
```

### Frontend Setup

```bash
cd dental-crm-frontend

# Встановити залежності
npm install

# Створити .env файл
cp .env.example .env

# Запустити dev сервер
npm run dev
```

### Docker Setup

```bash
cd dental-crm-api

# Запустити всі сервіси
./vendor/bin/sail up -d

# Міграції
./vendor/bin/sail artisan migrate --seed
```

## 🧪 Тестування

### Backend Tests

```bash
cd dental-crm-api

# Запустити всі тести
php artisan test

# Запустити конкретний тест
php artisan test --filter AppointmentApiTest

# З покриттям коду
php artisan test --coverage
```

### Frontend Tests

```bash
cd dental-crm-frontend

# Запустити тести
npm run test

# E2E тести
npm run test:e2e
```

## 📚 Документація

### Основна документація
- [API Documentation](dental-crm-api/docs/API_DOCUMENTATION.md)
- [Calendar Module Design](dental-crm-api/docs/calendar_module_design.md)
- [Audit Logging](dental-crm-api/docs/AUDIT_LOGGING.md)

### Планування та дизайн
- [Telegram Нагадування для Лікарів](dental-crm-api/docs/TELEGRAM_NOTIFICATIONS.md)
- [Аналітика та Dashboard](dental-crm-api/docs/ANALYTICS_DASHBOARD.md)
- [Експорт/Імпорт даних](dental-crm-api/docs/EXPORT_IMPORT.md)
- [API Versioning](dental-crm-api/docs/API_VERSIONING.md)
- [2FA для Адміністраторів](dental-crm-api/docs/2FA_DESIGN.md)
- [Платіжні Системи (Шаблон)](dental-crm-api/docs/PAYMENTS_TEMPLATE.md)
- [Мобільний Додаток (Scope)](dental-crm-api/docs/MOBILE_APP_SCOPE.md)

## 🔑 API Endpoints

### Authentication
```
POST /api/login
POST /api/logout
GET  /api/user
```

### Appointments
```
GET    /api/appointments
POST   /api/appointments
PUT    /api/appointments/{id}
POST   /api/appointments/{id}/cancel
POST   /api/appointments/series
```

### Patients
```
GET    /api/patients
POST   /api/patients
GET    /api/patients/{id}
PUT    /api/patients/{id}
DELETE /api/patients/{id}
POST   /api/patients/{id}/notes
```

### Calendar
```
GET /api/doctors/{id}/slots
GET /api/doctors/{id}/recommended-slots
GET /api/booking-suggestions
GET /api/doctors/{id}/schedule
PUT /api/doctors/{id}/schedule
```

### Finance
```
GET    /api/invoices                    # Список рахунків
POST   /api/invoices                    # Створити рахунок
GET    /api/invoices/{id}               # Деталі рахунку
PUT    /api/invoices/{id}               # Оновити рахунок
POST   /api/invoices/{id}/items         # Додати послуги
PUT    /api/invoices/{id}/items         # Замінити послуги
POST   /api/invoices/{id}/discount      # Застосувати знижку
POST   /api/invoices/{id}/cancel        # Скасувати рахунок
POST   /api/payments                    # Додати оплату
POST   /api/payments/{id}/refund        # Повернення коштів
GET    /api/finance/stats               # Фінансова статистика
```

Повна документація API: [API_DOCUMENTATION.md](dental-crm-api/docs/API_DOCUMENTATION.md)

## 🎯 Roadmap

### ✅ Completed
- [x] Базова структура проекту
- [x] Автентифікація та авторизація
- [x] CRUD для всіх основних сутностей
- [x] Календар з доступними слотами
- [x] Перевірка конфліктів
- [x] Список очікування
- [x] Медичні картки
- [x] Form Request класи
- [x] API Resources
- [x] Тести (Feature + Unit)
- [x] Audit logging
- [x] Rate limiting
- [x] Database indexes
- [x] Нагадування для лікарів (Telegram) - [Документація](dental-crm-api/docs/TELEGRAM_NOTIFICATIONS.md)
- [x] **Модуль фінансів** - рахунки, оплати, статистика
  - [x] CRUD рахунків з валідацією
  - [x] Система оплат (готівка, картка, переказ)
  - [x] Знижки (відсоткові та фіксовані)
  - [x] Повернення коштів з аудитом
  - [x] Doctor Scope (обмеження доступу для лікарів)
  - [x] Фінансова статистика з кешуванням (Redis)
  - [x] UI: таблиця рахунків з фільтрами та діями
  - [x] UI: модальні вікна створення/редагування
  - [x] UI abstraction layer для майбутньої заміни UI kit

### 🚧 In Progress
- [ ] Аналітика та звіти - [Документація](dental-crm-api/docs/ANALYTICS_DASHBOARD.md)
- [ ] Dashboard з статистикою - [Документація](dental-crm-api/docs/ANALYTICS_DASHBOARD.md)
- [ ] Інтеграція фінансів з прийомами (автоматичне створення рахунків)

### 📝 Planned
- [x] Експорт/імпорт даних - [Документація](dental-crm-api/docs/EXPORT_IMPORT.md)
- [x] API versioning - [Документація](dental-crm-api/docs/API_VERSIONING.md)
- [ ] Мультимовність (i18n) - Відкладено, враховується в нових текстах
- [x] 2FA для адміністраторів - [Документація](dental-crm-api/docs/2FA_DESIGN.md)
- [ ] Інтеграція з платіжними системами (LiqPay, Monobank) - [Шаблон](dental-crm-api/docs/PAYMENTS_TEMPLATE.md)
- [ ] Друк рахунків та чеків
- [ ] Звіти по фінансах (експорт в Excel/PDF)
- [ ] Мобільний додаток - [Scope](dental-crm-api/docs/MOBILE_APP_SCOPE.md) (на паузі)

## 🤝 Contributing

1. Fork проект
2. Створіть feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit зміни (`git commit -m 'Add some AmazingFeature'`)
4. Push до branch (`git push origin feature/AmazingFeature`)
5. Відкрийте Pull Request

### Code Style

Backend:
```bash
# Форматування коду
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse
```

Frontend:
```bash
# Lint
npm run lint

# Format
npm run format
```

## 📄 License

MIT License

## 👨‍💻 Автори

Розроблено командою CRM Dental

## 📞 Підтримка

Для питань та підтримки:
- Email: support@crmdental.com
- Issues: [GitHub Issues](https://github.com/your-repo/issues)

## 🔒 Безпека

### Важливо: Не комітьте секрети!

- **Ніколи не комітьте** `.env` файли або файли з реальними секретами
- Використовуйте `.env.example` як шаблон
- Після клонування репозиторію:
  1. Створіть `.env` з `.env.example`
  2. Згенеруйте новий `APP_KEY`: `php artisan key:generate`
  3. Змініть всі паролі та API ключі

### Очищення репозиторію

Якщо ви випадково закомітили артефакти (vendor, node_modules, storage, логи), видаліть їх з git індексу:

```bash
# Видалити з git (не з файлової системи)
git rm -r --cached dental-crm-api/vendor
git rm -r --cached dental-crm-api/node_modules
git rm -r --cached dental-crm-frontend/node_modules
git rm -r --cached dental-crm-api/storage/logs

# Закомітити зміни
git commit -m "chore: remove tracked artifacts from git"
```

Детальніше про безпеку: [SECURITY.md](SECURITY.md)

---

**Примітка**: Це production-ready система. Перед розгортанням на production переконайтеся, що:
- Налаштовано HTTPS
- Змінено всі секретні ключі (особливо `APP_KEY`)
- Налаштовано backup БД
- Увімкнено логування помилок
- Налаштовано моніторинг
- Перевірено `.gitignore` та не закомічені секрети

