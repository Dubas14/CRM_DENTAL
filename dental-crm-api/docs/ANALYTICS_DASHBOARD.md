# Аналітика та Dashboard

## Огляд

Система аналітики з використанням ClickHouse для зберігання агрегованих даних та PostgreSQL для операційних даних. Мінімальна конфігурація ClickHouse для економії ресурсів.

## Мінімальні метрики

### 1. Завантаженість лікарів
- Кількість записів на день/тиждень/місяць
- Середня тривалість запису
- Відсоток зайнятості (занятий час / робочий час)

### 2. No-show rate
- Кількість записів зі статусом `no_show`
- Відсоток no-show по лікарях
- Тренд no-show по місяцях

### 3. ТОП процедур
- Найпопулярніші процедури (за кількістю)
- Середня тривалість процедур
- Виручка по процедурах

### 4. Виручка
- Виручка за день/тиждень/місяць
- Виручка по лікарях
- Виручка по клініках

## ClickHouse - Мінімальна конфігурація

### Встановлення (мінімальний профіль)

**docker-compose.yml** (вже налаштовано):
```yaml
clickhouse:
  image: 'clickhouse/clickhouse-server:latest'
  ports:
    - '${FORWARD_CLICKHOUSE_PORT:-8123}:8123'
    - '${FORWARD_CLICKHOUSE_NATIVE_PORT:-9000}:9000'
  volumes:
    - 'sail-clickhouse:/var/lib/clickhouse'
```

### Мінімальні пакети

Для PHP клієнта використовуємо:
```bash
composer require smi2/phpclickhouse
```

Або через HTTP API (без додаткових пакетів):
- Використовуємо `guzzlehttp/guzzle` (вже встановлено)
- Прямі HTTP запити до ClickHouse

### Схема даних

**Таблиця: appointments_analytics**

```sql
CREATE TABLE appointments_analytics (
    date Date,
    doctor_id UInt32,
    clinic_id UInt32,
    procedure_id Nullable(UInt32),
    status String,
    duration_minutes UInt16,
    revenue Nullable(Decimal(10, 2)),
    created_at DateTime
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (date, doctor_id, clinic_id);
```

**Таблиця: daily_summary**

```sql
CREATE TABLE daily_summary (
    date Date,
    clinic_id UInt32,
    total_appointments UInt32,
    completed_appointments UInt32,
    no_show_count UInt32,
    total_revenue Decimal(10, 2),
    updated_at DateTime
) ENGINE = ReplacingMergeTree(updated_at)
PARTITION BY toYYYYMM(date)
ORDER BY (date, clinic_id);
```

## ETL процес

### Варіант 1: Матеріалізовані в'ю (PostgreSQL)

Для невеликих обсягів даних використовуємо матеріалізовані в'ю в PostgreSQL:

```php
// Migration
DB::statement('
    CREATE MATERIALIZED VIEW appointments_daily_summary AS
    SELECT 
        DATE(start_at) as date,
        clinic_id,
        doctor_id,
        COUNT(*) as total_appointments,
        COUNT(*) FILTER (WHERE status = \'done\') as completed,
        COUNT(*) FILTER (WHERE status = \'no_show\') as no_show
    FROM appointments
    GROUP BY DATE(start_at), clinic_id, doctor_id
');
```

### Варіант 2: Періодичний завантажувач (ClickHouse)

Для великих обсягів - періодичне завантаження в ClickHouse:

```php
// Console Command: LoadAnalyticsToClickHouse
php artisan analytics:load-clickhouse
```

## API Endpoints

### GET /api/analytics/dashboard

Повертає дані для dashboard:

```json
{
  "today": {
    "appointments": 15,
    "completed": 12,
    "no_show": 1,
    "revenue": 45000.00
  },
  "this_week": {
    "appointments": 85,
    "completed": 78,
    "no_show": 5,
    "revenue": 250000.00
  },
  "top_procedures": [
    {
      "id": 1,
      "name": "Чистка зубів",
      "count": 25,
      "revenue": 12500.00
    }
  ],
  "doctor_workload": [
    {
      "doctor_id": 1,
      "doctor_name": "Іван Іванов",
      "appointments_today": 5,
      "utilization_percent": 75
    }
  ]
}
```

### GET /api/analytics/no-show-rate

Статистика no-show:

```json
{
  "overall_rate": 5.2,
  "by_doctor": [
    {
      "doctor_id": 1,
      "rate": 3.1
    }
  ],
  "trend": [
    {
      "month": "2024-01",
      "rate": 4.5
    }
  ]
}
```

## Кешування

- Dashboard дані: кеш на 5 хвилин (Redis)
- Агреговані метрики: кеш на 1 годину
- Тренди: кеш на 24 години

```php
Cache::remember('dashboard_stats', 300, function () {
    return $this->calculateDashboardStats();
});
```

## Frontend Dashboard

### Компоненти

- **StatsCards**: Загальна статистика (сьогодні, тиждень)
- **TopProceduresTable**: ТОП процедур
- **DoctorWorkloadChart**: Графік завантаженості лікарів
- **NoShowRateChart**: Графік no-show rate

### Оновлення даних

- Автоматичне оновлення кожні 5 хвилин
- Кнопка ручного оновлення

## Оптимізація ClickHouse

### Налаштування для економії ресурсів

**clickhouse-config.xml:**
```xml
<clickhouse>
    <max_server_memory_usage_to_ram_ratio>0.5</max_server_memory_usage_to_ram_ratio>
    <max_concurrent_queries>10</max_concurrent_queries>
    <max_memory_usage>2000000000</max_memory_usage>
</clickhouse>
```

### Retention Policy

- Зберігати дані за останні 2 роки
- Автоматичне видалення старих даних:

```sql
ALTER TABLE appointments_analytics DELETE WHERE date < today() - INTERVAL 2 YEAR;
```

## Майбутні покращення

- Експорт звітів (PDF/Excel)
- Детальна аналітика по пацієнтах
- Прогнози завантаженості
- Порівняння періодів

