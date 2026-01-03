# Інтеграція з Платіжними Системами (Шаблон)

## Огляд

Шаблон для майбутньої інтеграції з платіжними системами. Підтримка гривні (UAH), оплати через POS-термінал, касу та онлайн-платежів (ймовірно LiqPay). Наразі тільки документація та структура без реальної інтеграції.

## Валюта

**Основна валюта:** Гривня (UAH)

- Всі суми зберігаються в копійках (найменша одиниця)
- Відображення: `amount / 100` для показу в гривнях
- Формат: `12345` (копійок) = `123.45` UAH

## Методи оплати

### 1. POS-термінал

**Опис:**
- Оплата карткою через POS-термінал в клініці
- Миттєва обробка
- Підтвердження через чек

**Потік:**
1. Створення рахунку
2. Оплата через термінал
3. Підтвердження оплати (вручну або через API терміналу)
4. Оновлення статусу рахунку

### 2. Каса (готівка)

**Опис:**
- Оплата готівкою в касі клініки
- Підтвердження через касовий апарат (якщо є)

**Потік:**
1. Створення рахунку
2. Оплата готівкою
3. Видача чеку
4. Підтвердження оплати в системі

### 3. Онлайн-оплата (LiqPay - шаблон)

**Опис:**
- Оплата через інтернет (LiqPay або інший провайдер)
- Redirect на платіжну сторінку
- Webhook для підтвердження оплати

**Потік:**
1. Створення рахунку
2. Генерація платежної сесії
3. Redirect на LiqPay
4. Оплата користувачем
5. Webhook з підтвердженням
6. Оновлення статусу рахунку

## Модель даних

### Таблиця: invoices (рахунки)

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('clinic_id')->constrained();
    $table->foreignId('patient_id')->constrained();
    $table->foreignId('appointment_id')->nullable();
    
    $table->string('invoice_number')->unique(); // INV-2024-001
    $table->unsignedBigInteger('amount'); // В копійках
    $table->string('currency', 3)->default('UAH');
    $table->enum('status', [
        'draft',      // Чернетка
        'pending',    // Очікує оплати
        'paid',       // Оплачено
        'partially_paid', // Частково оплачено
        'cancelled', // Скасовано
        'refunded'   // Повернено
    ])->default('draft');
    
    $table->enum('payment_method', [
        'cash',      // Готівка
        'card',      // Картка (POS)
        'online',    // Онлайн
        'transfer'   // Банківський переказ
    ])->nullable();
    
    $table->text('description')->nullable();
    $table->json('items')->nullable(); // Деталі рахунку
    
    $table->timestamp('issued_at')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
});
```

### Таблиця: payments (платежі)

```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained();
    
    $table->unsignedBigInteger('amount'); // В копійках
    $table->string('currency', 3)->default('UAH');
    $table->enum('method', ['cash', 'card', 'online', 'transfer']);
    $table->enum('status', ['pending', 'completed', 'failed', 'refunded']);
    
    // Для онлайн-платежів
    $table->string('payment_provider')->nullable(); // liqpay, portmone, etc
    $table->string('transaction_id')->nullable()->unique();
    $table->string('idempotency_key')->nullable()->unique();
    
    $table->json('provider_response')->nullable(); // Відповідь від провайдера
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();
});
```

### Таблиця: payment_refunds (повернення)

```php
Schema::create('payment_refunds', function (Blueprint $table) {
    $table->id();
    $table->foreignId('payment_id')->constrained();
    $table->foreignId('invoice_id')->constrained();
    
    $table->unsignedBigInteger('amount'); // В копійках
    $table->enum('status', ['pending', 'completed', 'failed']);
    $table->text('reason')->nullable();
    
    $table->string('refund_transaction_id')->nullable();
    $table->json('provider_response')->nullable();
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();
});
```

## Онлайн-платежі (LiqPay - шаблон)

### Конфігурація

**.env:**
```
LIQPAY_PUBLIC_KEY=your_public_key
LIQPAY_PRIVATE_KEY=your_private_key
LIQPAY_SANDBOX=true
```

### Створення платежу

```php
// Service: LiqPayService (шаблон)
class LiqPayService
{
    public function createPayment(Invoice $invoice): array
    {
        $data = [
            'action' => 'pay',
            'amount' => $invoice->amount / 100, // Конвертація в гривні
            'currency' => 'UAH',
            'description' => "Оплата рахунку {$invoice->invoice_number}",
            'order_id' => $invoice->id,
            'version' => '3',
            'server_url' => route('payments.webhook'),
            'result_url' => route('payments.success'),
        ];
        
        $signature = $this->generateSignature($data);
        $data['signature'] = $signature;
        
        return [
            'checkout_url' => 'https://www.liqpay.ua/api/3/checkout',
            'data' => base64_encode(json_encode($data)),
        ];
    }
}
```

### Webhook обробка

```php
// Route: POST /api/payments/webhook
Route::post('/payments/webhook', [PaymentController::class, 'webhook'])
    ->withoutMiddleware([VerifyCsrfToken::class]);

// Controller
public function webhook(Request $request)
{
    $data = json_decode(base64_decode($request->data), true);
    $signature = $request->signature;
    
    // Валідація підпису
    if (!$this->verifySignature($data, $signature)) {
        return response()->json(['error' => 'Invalid signature'], 400);
    }
    
    // Idempotency check
    $idempotencyKey = $data['order_id'] . '_' . $data['transaction_id'];
    if (Payment::where('idempotency_key', $idempotencyKey)->exists()) {
        return response()->json(['status' => 'already_processed']);
    }
    
    // Обробка платежу
    $payment = Payment::where('transaction_id', $data['transaction_id'])->first();
    if ($payment) {
        $payment->update([
            'status' => $data['status'] === 'success' ? 'completed' : 'failed',
            'provider_response' => $data,
            'processed_at' => now(),
        ]);
    }
    
    return response()->json(['status' => 'ok']);
}
```

### Idempotency

**Ключі для idempotency:**
- `order_id` (ID рахунку) + `transaction_id` (від провайдера)
- Зберігається в `payments.idempotency_key`
- Перевірка перед обробкою webhook

## API Endpoints (шаблон)

### Рахунки

```
GET    /api/invoices
POST   /api/invoices
GET    /api/invoices/{id}
PUT    /api/invoices/{id}
POST   /api/invoices/{id}/pay
POST   /api/invoices/{id}/cancel
```

### Платежі

```
GET    /api/payments
POST   /api/payments
GET    /api/payments/{id}
POST   /api/payments/{id}/refund
POST   /api/payments/webhook  # Webhook від провайдера
```

## Статуси

### Invoice статуси

- `draft`: Чернетка (можна редагувати)
- `pending`: Очікує оплати
- `paid`: Повністю оплачено
- `partially_paid`: Частково оплачено
- `cancelled`: Скасовано
- `refunded`: Повернено

### Payment статуси

- `pending`: Очікує обробки
- `completed`: Успішно оброблено
- `failed`: Помилка обробки
- `refunded`: Повернено

## Безпека

### Webhook валідація

- Перевірка підпису від провайдера
- Idempotency keys для запобігання дублюванню
- Rate limiting для webhook endpoints

### Обмеження

- Тільки авторизовані користувачі можуть створювати рахунки
- `clinic_admin` може створювати рахунки для своєї клініки
- `super_admin` має доступ до всіх рахунків

## Майбутня інтеграція

### Кроки для реалізації

1. **Вибір провайдера:**
   - LiqPay, Portmone, Fondy, або інший
   - Реєстрація та отримання ключів

2. **Реалізація сервісу:**
   - Створення `PaymentProviderInterface`
   - Реалізація `LiqPayService`
   - Тестування в sandbox режимі

3. **Інтеграція:**
   - Підключення до реального API
   - Налаштування webhook
   - Тестування на production

4. **Моніторинг:**
   - Логування всіх транзакцій
   - Алерти на помилки
   - Статистика платежів

## Приклад використання (шаблон)

```php
// Створення рахунку
$invoice = Invoice::create([
    'clinic_id' => 1,
    'patient_id' => 1,
    'appointment_id' => 1,
    'amount' => 150000, // 1500.00 UAH
    'status' => 'pending',
]);

// Онлайн-оплата
$liqpayService = new LiqPayService();
$paymentData = $liqpayService->createPayment($invoice);

// Redirect на платіжну сторінку
return redirect($paymentData['checkout_url']);
```

