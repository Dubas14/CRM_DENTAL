# Експорт/Імпорт даних

## Огляд

Система експорту та імпорту даних для пацієнтів, записів та процедур. Підтримка форматів CSV та JSON з валідацією та обробкою помилок.

## Підтримувані формати

### CSV
- Роздільник: `;` (крапка з комою)
- Кодування: UTF-8
- Заголовки: Обов'язкові в першому рядку

### JSON
- Формат: Масив об'єктів
- Кодування: UTF-8
- Валідація: JSON Schema

## Підтримувані сутності

### 1. Пацієнти (patients)

**Експорт полів:**
- `id` (опційно)
- `full_name` (обов'язково)
- `phone` (опційно)
- `email` (опційно)
- `birth_date` (опційно, формат: YYYY-MM-DD)
- `notes` (опційно)
- `clinic_id` (обов'язково)

**CSV приклад:**
```csv
full_name;phone;email;birth_date;clinic_id
Іван Петренко;+380501234567;ivan@example.com;1990-05-15;1
Марія Коваленко;+380501234568;maria@example.com;1985-08-20;1
```

**JSON приклад:**
```json
[
  {
    "full_name": "Іван Петренко",
    "phone": "+380501234567",
    "email": "ivan@example.com",
    "birth_date": "1990-05-15",
    "clinic_id": 1
  }
]
```

### 2. Записи (appointments)

**Експорт полів:**
- `id` (опційно)
- `patient_id` (обов'язково)
- `doctor_id` (обов'язково)
- `procedure_id` (опційно)
- `room_id` (опційно)
- `start_at` (обов'язково, формат: YYYY-MM-DD HH:mm:ss)
- `end_at` (обов'язково, формат: YYYY-MM-DD HH:mm:ss)
- `status` (обов'язково)
- `comment` (опційно)
- `clinic_id` (обов'язково)

### 3. Процедури (procedures)

**Експорт полів:**
- `id` (опційно)
- `name` (обов'язково)
- `category` (опційно)
- `duration_minutes` (обов'язково)
- `requires_room` (обов'язково, boolean)
- `requires_assistant` (обов'язково, boolean)
- `equipment_id` (опційно)
- `clinic_id` (обов'язково)

## Валідація

### Схема полів

**Обов'язкові поля:**
- Визначаються для кожної сутності окремо
- Помилка: "Поле {field} є обов'язковим"

**Опційні поля:**
- Можуть бути порожніми або відсутніми
- Валідація формату, якщо поле заповнене

### Дедуплікація

**Ключі дедуплікації:**

1. **Пацієнти:**
   - За `email` (якщо вказано)
   - За `phone` (якщо вказано)
   - За `id` (якщо експортовано з id)

2. **Записи:**
   - За `id` (якщо вказано)
   - За комбінацією: `patient_id + doctor_id + start_at`

3. **Процедури:**
   - За `id` (якщо вказано)
   - За `name + clinic_id`

**Стратегії:**
- `skip`: Пропустити дублікат
- `update`: Оновити існуючий запис
- `error`: Повернути помилку

## Обробка великих файлів

### Черги

Використовуємо Laravel Queue для асинхронної обробки:

```php
// Job для імпорту
ImportDataJob::dispatch($filePath, $entityType, $userId)
    ->onQueue('imports');
```

### Статус та прогрес

**Таблиця: import_jobs**

```php
Schema::create('import_jobs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('entity_type'); // patients, appointments, procedures
    $table->string('file_path');
    $table->enum('status', ['pending', 'processing', 'completed', 'failed']);
    $table->integer('total_rows')->default(0);
    $table->integer('processed_rows')->default(0);
    $table->integer('success_rows')->default(0);
    $table->integer('error_rows')->default(0);
    $table->json('errors')->nullable();
    $table->timestamps();
});
```

**API Endpoint:**
```
GET /api/imports/{id}/status
```

Відповідь:
```json
{
  "id": 1,
  "status": "processing",
  "total_rows": 1000,
  "processed_rows": 750,
  "success_rows": 745,
  "error_rows": 5,
  "progress_percent": 75
}
```

### Звіти про помилки

**Формат помилки:**
```json
{
  "row": 5,
  "field": "email",
  "value": "invalid-email",
  "error": "Невірний формат email",
  "line_number": 6
}
```

**Експорт помилок:**
```
GET /api/imports/{id}/errors
```

Повертає CSV/JSON файл з помилками для виправлення.

## Безпека

### Ролі та дозволи

- `super_admin`: Експорт/імпорт всіх даних
- `clinic_admin`: Експорт/імпорт даних своєї клініки
- `doctor`: Тільки експорт (власні записи)
- `registrar`: Експорт/імпорт пацієнтів своєї клініки

### Ліміти

- Максимальний розмір файлу: 10 MB
- Максимальна кількість рядків: 10,000 (для синхронного імпорту)
- Більше 10,000 рядків - автоматично в чергу

### Маскування PII в логах

```php
Log::info('Import started', [
    'user_id' => $userId,
    'entity_type' => $entityType,
    'file_size' => $fileSize,
    // НЕ логуємо file_path з повним шляхом
]);
```

## API Endpoints

### Експорт

```
POST /api/export/patients
POST /api/export/appointments
POST /api/export/procedures
```

**Параметри:**
- `format`: `csv` або `json`
- `fields`: Масив полів для експорту (опційно)
- `filters`: Фільтри (дата, клініка, лікар)

**Відповідь:**
```json
{
  "download_url": "/api/export/download/{token}",
  "expires_at": "2024-01-15T10:00:00Z"
}
```

### Імпорт

```
POST /api/import/patients
POST /api/import/appointments
POST /api/import/procedures
```

**Request:**
- `file`: Файл (multipart/form-data)
- `duplicate_strategy`: `skip`, `update`, `error`
- `validate_only`: `true` (тільки валідація без імпорту)

**Відповідь:**
```json
{
  "import_job_id": 1,
  "status": "pending",
  "total_rows": 100
}
```

## Приклад використання

### Експорт пацієнтів

```bash
curl -X POST "https://api.example.com/api/export/patients" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "format": "csv",
    "filters": {
      "clinic_id": 1
    }
  }'
```

### Імпорт пацієнтів

```bash
curl -X POST "https://api.example.com/api/import/patients" \
  -H "Authorization: Bearer {token}" \
  -F "file=@patients.csv" \
  -F "duplicate_strategy=update"
```

## Майбутні покращення

- Підтримка Excel (.xlsx)
- Шаблони для імпорту
- Масове оновлення через імпорт
- Експорт з фільтрами через UI

