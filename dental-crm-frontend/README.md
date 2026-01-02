# Dental CRM - Frontend

Сучасний веб-інтерфейс для системи управління стоматологічними клініками.

## 🚀 Технології

- **Vue 3** - прогресивний JavaScript фреймворк
- **TypeScript** - типізована надбудова над JavaScript
- **Vite** - швидкий build tool
- **Vue Router** - маршрутизація
- **Pinia** - state management
- **Axios** - HTTP клієнт
- **Tailwind CSS** - utility-first CSS framework

## 📋 Вимоги

- Node.js >= 18
- npm >= 9

## 🔧 Встановлення

### 1. Клонування та встановлення залежностей

```bash
cd dental-crm-frontend
npm install
```

### 2. Налаштування середовища

Скопіюйте файл з прикладами змінних оточення:

```bash
cp .env.example .env
```

Відредагуйте `.env` файл:

```env
VITE_API_URL=http://localhost:8000
```

### 3. Запуск development сервера

```bash
npm run dev
```

Додаток буде доступний за адресою: `http://localhost:3000`

## 📦 Команди

```bash
# Development сервер
npm run dev

# Production build
npm run build

# Preview production build
npm run preview

# Type checking
npm run type-check

# Linting
npm run lint

# Форматування коду
npm run format
```

## 📁 Структура проекту

```
dental-crm-frontend/
├── src/
│   ├── assets/              # Статичні файли (CSS, зображення)
│   ├── components/          # Vue компоненти
│   │   ├── ui/             # UI компоненти (кнопки, форми)
│   │   └── features/       # Feature-специфічні компоненти
│   ├── views/              # Сторінки/Views
│   │   ├── LoginView.vue
│   │   ├── DashboardView.vue
│   │   ├── patients/       # Модуль пацієнтів
│   │   ├── doctors/        # Модуль лікарів
│   │   ├── appointments/   # Модуль записів
│   │   └── calendar/       # Календар
│   ├── router/             # Vue Router конфігурація
│   │   └── index.ts
│   ├── services/           # API сервіси
│   │   └── apiClient.ts
│   ├── stores/             # Pinia stores
│   │   └── theme.ts
│   ├── types/              # TypeScript типи та інтерфейси
│   ├── utils/              # Утиліти
│   ├── App.vue             # Головний компонент
│   └── main.ts             # Entry point
├── public/                 # Публічні статичні файли
├── index.html             # HTML шаблон
├── vite.config.ts         # Vite конфігурація
├── tsconfig.json          # TypeScript конфігурація
└── tailwind.config.js     # Tailwind конфігурація
```

## 🔐 Аутентифікація

Додаток використовує Bearer token аутентифікацію.

Токен зберігається в `localStorage` та автоматично додається до всіх API запитів через Axios interceptor.

### Автоматичний logout

При отриманні 401 відповіді від API:
1. Токен видаляється з localStorage
2. Користувач перенаправляється на сторінку входу

## 🛣️ Маршрутизація

### Публічні маршрути
- `/login` - Сторінка входу

### Захищені маршрути (потребують аутентифікації)
- `/` - Дашборд
- `/clinics` - Список клінік
- `/doctors` - Список лікарів
- `/patients` - Список пацієнтів
- `/schedule` - Розклад лікарів
- `/calendar` - Календар записів
- `/equipments` - Обладнання
- `/procedures` - Процедури
- `/assistants` - Асистенти
- `/roles` - Управління ролями
- `/clinic-settings` - Налаштування клініки

### Контроль доступу на основі ролей

```typescript
// Приклад з router/index.ts
{
  path: '/clinics',
  meta: { 
    requiresAuth: true,
    roles: ['super_admin'] // Доступ тільки для super_admin
  }
}
```

## 🎨 Theming

Додаток підтримує темну та світлу теми.

Переключення теми зберігається в localStorage і керується через Pinia store:

```typescript
import { useThemeStore } from '@/stores/theme'

const themeStore = useThemeStore()
themeStore.toggleTheme()
```

## 🔌 API Integration

### API Client

Axios instance налаштований в `src/services/apiClient.ts`:

```typescript
import apiClient from '@/services/apiClient'

// GET запит
const response = await apiClient.get('/patients')

// POST запит
const response = await apiClient.post('/appointments', data)
```

### Перехоплювачі (Interceptors)

- **Request Interceptor**: Додає Bearer token до всіх запитів
- **Response Interceptor**: 
  - Обробляє 401 помилки (logout)
  - Логує помилки API

## 🎨 Стилізація

### Tailwind CSS

Проект використовує Tailwind CSS для стилізації:

```vue
<template>
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
      Hello World
    </h1>
  </div>
</template>
```

### Кастомні кольори

Налаштовані в `tailwind.config.js`:

```javascript
theme: {
  extend: {
    colors: {
      primary: { /* ... */ },
      secondary: { /* ... */ }
    }
  }
}
```

## 📱 Адаптивність

Додаток повністю адаптивний та працює на:
- Desktop (1920px+)
- Laptop (1280px - 1919px)
- Tablet (768px - 1279px)
- Mobile (< 768px)

## ⚡ Оптимізація

### Code Splitting

Маршрути завантажуються лениво (lazy loading):

```typescript
{
  path: '/patients',
  component: () => import('@/views/patients/PatientListView.vue')
}
```

### Tree Shaking

Vite автоматично видаляє невикористаний код при build.

### Asset Optimization

- Зображення оптимізуються через Vite
- CSS мінімізується
- JavaScript мінімізується та обфусковується

## 🧪 Тестування

(Додати інформацію про тести коли буде налаштовано)

```bash
# Unit тести
npm run test:unit

# E2E тести
npm run test:e2e
```

## 🔍 Лінтинг та форматування

### ESLint

```bash
npm run lint
```

### Prettier

```bash
npm run format
```

## 🏗️ Production Build

### Build

```bash
npm run build
```

Збірка буде створена в папці `dist/`

### Preview

Перевірка production build локально:

```bash
npm run preview
```

### Deployment

#### Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api {
        proxy_pass http://backend-api:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

#### Docker

```dockerfile
FROM node:18-alpine as build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
```

## 🔄 State Management (Pinia)

Створення нового store:

```typescript
// stores/patients.ts
import { defineStore } from 'pinia'

export const usePatientsStore = defineStore('patients', {
  state: () => ({
    patients: []
  }),
  actions: {
    async fetchPatients() {
      // ...
    }
  },
  getters: {
    patientCount: (state) => state.patients.length
  }
})
```

Використання в компоненті:

```vue
<script setup lang="ts">
import { usePatientsStore } from '@/stores/patients'

const patientsStore = usePatientsStore()
</script>
```

## 🌐 Інтернаціоналізація (i18n)

(Планується додати в майбутньому)

## 🎯 Best Practices

1. **TypeScript** - використовуйте типи для всіх змінних та функцій
2. **Composition API** - використовуйте `<script setup>` для нових компонентів
3. **Reactivity** - використовуйте `ref()` та `reactive()` правильно
4. **Компоненти** - розбивайте на маленькі, переісковувані компоненти
5. **Іменування** - PascalCase для компонентів, camelCase для функцій
6. **Props** - завжди визначайте типи props
7. **Emits** - завжди визначайте типи emits

## 📝 Contributing

1. Створіть feature branch
2. Дотримуйтесь code style (ESLint + Prettier)
3. Додайте type definitions
4. Перевірте що build проходить
5. Створіть Pull Request

## 🆘 Troubleshooting

### CORS помилки
Переконайтеся що backend налаштований для прийому запитів з frontend домену.

### 401 Unauthorized
Перевірте що токен валідний та не прострочений.

### Vite не запускається
```bash
rm -rf node_modules package-lock.json
npm install
```

## 📄 Ліцензія

[MIT License](LICENSE)

## 👨‍💻 Автори

- Your Name - Initial work
