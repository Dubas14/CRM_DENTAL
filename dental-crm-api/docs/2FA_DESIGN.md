# 2FA для Адміністраторів

## Огляд

Двофакторна аутентифікація (2FA) для адміністраторів системи. Використовується TOTP (Time-based One-Time Password) через Google Authenticator або подібні додатки, з резервними кодами для відновлення.

## Методи аутентифікації

### 1. TOTP (Основний метод)

**Як працює:**
- Користувач сканує QR-код в додатку (Google Authenticator, Authy)
- Додаток генерує 6-значний код кожні 30 секунд
- Користувач вводить код при логіні

**Бібліотека:**
```bash
composer require pragmarx/google2fa-laravel
```

### 2. Резервні коди (Backup codes)

**Генерація:**
- 10 одноразових кодів при увімкненні 2FA
- Кожен код можна використати тільки один раз
- Формат: `XXXX-XXXX-XXXX` (12 символів)

**Зберігання:**
- Хешовані в базі даних (bcrypt)
- Показуються тільки один раз при створенні

### 3. SMS/Email (Опційно, fallback)

**Використання:**
- Тільки якщо TOTP недоступний
- Одноразовий код відправляється через SMS/Email
- Термін дії: 5 хвилин

## UX Flow

### Увімкнення 2FA

1. **Користувач переходить в налаштування:**
   ```
   GET /api/user/settings/2fa
   ```

2. **Сервер генерує секрет:**
   ```json
   {
     "qr_code": "data:image/png;base64,...",
     "secret": "JBSWY3DPEHPK3PXP", // Для ручного введення
     "backup_codes": [
       "1234-5678-9012",
       "3456-7890-1234",
       // ... 10 кодів
     ]
   }
   ```

3. **Користувач сканує QR-код:**
   - Відкриває Google Authenticator
   - Сканує QR-код
   - Бачить код в додатку

4. **Підтвердження:**
   ```
   POST /api/user/settings/2fa/enable
   {
     "code": "123456"
   }
   ```

5. **Збереження резервних кодів:**
   - Користувач зберігає backup codes в безпечному місці
   - Після підтвердження коди більше не показуються

### Відключення 2FA

1. **Підтвердження через TOTP або backup code:**
   ```
   POST /api/user/settings/2fa/disable
   {
     "code": "123456" // або backup code
   }
   ```

2. **Або через супер-адміна:**
   ```
   POST /api/admin/users/{id}/2fa/disable
   ```
   - Тільки для super_admin
   - Логується в audit log

### Логін з 2FA

1. **Звичайний логін:**
   ```
   POST /api/login
   {
     "email": "admin@example.com",
     "password": "password"
   }
   ```

2. **Якщо 2FA увімкнено:**
   ```json
   {
     "requires_2fa": true,
     "token": "temp_token_for_2fa"
   }
   ```

3. **Введення TOTP коду:**
   ```
   POST /api/login/2fa
   {
     "temp_token": "temp_token_for_2fa",
     "code": "123456"
   }
   ```

4. **Успішна аутентифікація:**
   ```json
   {
     "token": "final_auth_token",
     "user": { ... }
   }
   ```

### Відновлення доступу

**Якщо втрачено доступ до TOTP:**

1. **Використання backup code:**
   - Ввести один з резервних кодів
   - Код стає недійсним після використання

2. **Через супер-адміна:**
   - Супер-адмін може тимчасово відключити 2FA
   - Користувач повинен увімкнути 2FA знову

3. **Генерація нових backup codes:**
   ```
   POST /api/user/settings/2fa/regenerate-backup-codes
   {
     "code": "123456" // TOTP код для підтвердження
   }
   ```

## Ліміти спроб

### Захист від brute-force

- Максимум 5 невдалих спроб на 15 хвилин
- Після 5 спроб - блокування на 15 хвилин
- Логування всіх спроб

### Rate limiting

```php
RateLimiter::for('2fa', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});
```

## Зберігання секретів

### База даних

**Таблиця: user_2fa**

```php
Schema::create('user_2fa', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->unique();
    $table->text('secret'); // Зашифрований секрет
    $table->boolean('enabled')->default(false);
    $table->timestamp('enabled_at')->nullable();
    $table->timestamps();
});
```

**Таблиця: user_2fa_backup_codes**

```php
Schema::create('user_2fa_backup_codes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('code_hash'); // Хешований код
    $table->boolean('used')->default(false);
    $table->timestamp('used_at')->nullable();
    $table->timestamps();
});
```

### Шифрування

```php
// Використовуємо Laravel Encryption
$encryptedSecret = encrypt($secret);
$decryptedSecret = decrypt($encryptedSecret);
```

## API Endpoints

### Налаштування 2FA

```
GET    /api/user/settings/2fa          # Статус 2FA
POST   /api/user/settings/2fa/enable    # Увімкнути 2FA
POST   /api/user/settings/2fa/disable   # Вимкнути 2FA
POST   /api/user/settings/2fa/verify    # Перевірити код
POST   /api/user/settings/2fa/regenerate-backup-codes
```

### Адміністративні

```
POST   /api/admin/users/{id}/2fa/disable  # Вимкнути 2FA (super_admin)
GET    /api/admin/users/{id}/2fa/status   # Статус 2FA користувача
```

## Безпека

### Обов'язковість 2FA

- Тільки для ролей: `super_admin`, `clinic_admin`
- Опційно для інших ролей
- Налаштовується через конфігурацію

### Audit Logging

Всі дії з 2FA логуються:

```php
AuditLog::create([
    'user_id' => $user->id,
    'action' => '2fa_enabled',
    'details' => [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ],
]);
```

## Приклад використання

### Увімкнення 2FA

```javascript
// 1. Отримати QR-код
const response = await api.get('/api/user/settings/2fa');
const { qr_code, backup_codes } = response.data;

// 2. Показати QR-код користувачу
// 3. Підтвердити кодом
await api.post('/api/user/settings/2fa/enable', {
  code: '123456'
});

// 4. Зберегти backup codes
localStorage.setItem('backup_codes', JSON.stringify(backup_codes));
```

### Логін з 2FA

```javascript
// 1. Звичайний логін
const loginResponse = await api.post('/api/login', {
  email: 'admin@example.com',
  password: 'password'
});

if (loginResponse.data.requires_2fa) {
  // 2. Ввести TOTP код
  const finalResponse = await api.post('/api/login/2fa', {
    temp_token: loginResponse.data.token,
    code: '123456' // З Google Authenticator
  });
  
  // 3. Зберегти фінальний токен
  localStorage.setItem('auth_token', finalResponse.data.token);
}
```

## Майбутні покращення

- Підтримка WebAuthn (FIDO2)
- Push-нотифікації для підтвердження
- Біометрична аутентифікація (мобільний додаток)

