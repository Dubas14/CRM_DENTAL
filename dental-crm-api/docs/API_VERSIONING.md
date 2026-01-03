# API Versioning

## Огляд

Підготовка до впровадження версіонування API для забезпечення сумісності з майбутніми змінами та інтеграціями (мобільний додаток, платіжні системи).

## Схема версіонування

### URI-based versioning

Використовуємо префікс версії в URL:

```
/api/v1/patients
/api/v1/appointments
/api/v2/patients  # Майбутня версія
```

### Структура роутів

**routes/api.php:**
```php
// Версія 1 (поточна)
Route::prefix('v1')->group(function () {
    Route::apiResource('patients', PatientController::class);
    Route::apiResource('appointments', AppointmentController::class);
    // ...
});

// Версія 2 (майбутня)
Route::prefix('v2')->group(function () {
    Route::apiResource('patients', V2\PatientController::class);
    // ...
});
```

## Контракт v1 API

### Базовий URL

```
https://api.example.com/api/v1
```

### Загальні принципи

1. **Формат відповіді:**
   - Успіх: HTTP 200/201/204
   - Помилка: HTTP 4xx/5xx з JSON body

2. **Пагінація:**
   ```
   GET /api/v1/patients?page=1&per_page=20
   ```

3. **Фільтрація:**
   ```
   GET /api/v1/appointments?doctor_id=1&start_date=2024-01-01
   ```

4. **Сортування:**
   ```
   GET /api/v1/patients?sort=name&order=asc
   ```

### Endpoints

#### Authentication
```
POST   /api/v1/login
POST   /api/v1/logout
GET    /api/v1/user
POST   /api/v1/refresh-token
```

#### Patients
```
GET    /api/v1/patients
POST   /api/v1/patients
GET    /api/v1/patients/{id}
PUT    /api/v1/patients/{id}
DELETE /api/v1/patients/{id}
```

#### Appointments
```
GET    /api/v1/appointments
POST   /api/v1/appointments
GET    /api/v1/appointments/{id}
PUT    /api/v1/appointments/{id}
DELETE /api/v1/appointments/{id}
POST   /api/v1/appointments/{id}/cancel
```

#### Calendar
```
GET    /api/v1/doctors/{id}/slots
GET    /api/v1/doctors/{id}/schedule
PUT    /api/v1/doctors/{id}/schedule
```

## Політика депрекейту

### Життєвий цикл версії

1. **Active** (Активна): Повна підтримка, нові фічі
2. **Deprecated** (Застаріла): Підтримка, але без нових фіч, попередження в заголовках
3. **Sunset** (Припинена): Тільки критичні виправлення безпеки
4. **Retired** (Видалена): Версія більше не доступна

### Заголовки відповіді

**Deprecated версія:**
```
HTTP/1.1 200 OK
Deprecation: true
Sunset: Sat, 31 Dec 2024 23:59:59 GMT
Link: <https://api.example.com/api/v2>; rel="successor-version"
```

### Терміни

- Мінімальна підтримка версії: 12 місяців
- Попередження про депрекейт: за 6 місяців
- Sunset період: 3 місяці

## Моніторинг використання

### Логування

```php
Log::info('API version usage', [
    'version' => $request->route('version'),
    'endpoint' => $request->path(),
    'user_id' => $user->id,
]);
```

### Метрики

- Кількість запитів по версіях
- Найбільш використовувані endpoints
- Користувачі на deprecated версіях

### Dashboard

```
GET /api/admin/analytics/api-usage
```

Відповідь:
```json
{
  "versions": {
    "v1": {
      "requests_count": 15000,
      "unique_users": 50,
      "status": "active"
    }
  },
  "deprecated_warnings": 5
}
```

## Міграція на v2

### План міграції

1. **Підготовка v2:**
   - Створення нових контролерів
   - Тестування сумісності
   - Документація змін

2. **Анонс:**
   - Оголошення про v2
   - Документація міграції
   - Підтримка обох версій

3. **Перехідний період:**
   - Моніторинг використання v1
   - Нагадування про депрекейт
   - Допомога з міграцією

4. **Sunset v1:**
   - Останні виправлення безпеки
   - Повідомлення про припинення
   - Видалення v1

## Приклад використання

### Клієнт на v1

```javascript
const apiClient = axios.create({
  baseURL: 'https://api.example.com/api/v1',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json',
  }
});
```

### Перевірка версії

```javascript
// Перевірка заголовків на deprecated
axios.get('/api/v1/patients')
  .then(response => {
    if (response.headers['deprecation'] === 'true') {
      console.warn('API version is deprecated');
      console.log('Sunset date:', response.headers['sunset']);
    }
  });
```

## Документація

### OpenAPI/Swagger

Генерація документації для кожної версії:

```
/api/v1/docs
/api/v2/docs
```

### Changelog

Ведення changelog для кожної версії:

```
CHANGELOG.md
├── v1.0.0
├── v1.1.0
└── v2.0.0
```

## Майбутні покращення

- GraphQL API як альтернатива REST
- Версіонування через заголовки (Accept: application/vnd.api+json;version=1)
- Автоматичне перенаправлення на новішу версію

