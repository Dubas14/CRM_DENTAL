# Audit Logging

## Overview

The audit logging system tracks all changes to important models in the application. It records who made the change, what was changed, when it happened, and from which IP address.

## Setup

### 1. Run Migration

```bash
php artisan migrate
```

This will create the `audit_logs` table.

### 2. Add Auditable Trait to Models

To enable audit logging for a model, add the `Auditable` trait:

```php
<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use Auditable;
    
    // ... rest of model code
}
```

### Recommended Models for Auditing

- `Appointment` - Track all booking changes
- `Patient` - Track patient data modifications
- `MedicalRecord` - Track medical history changes
- `Schedule` - Track doctor schedule changes
- `ScheduleException` - Track schedule exceptions

## Usage

### Automatic Logging

Once the trait is added, all create, update, and delete operations are automatically logged:

```php
// This will automatically create an audit log entry
$appointment = Appointment::create([
    'doctor_id' => 1,
    'patient_id' => 2,
    'start_at' => now(),
    // ...
]);

// This will also be logged
$appointment->update(['status' => 'confirmed']);

// And this too
$appointment->delete();
```

### Manual Logging

For custom actions, you can manually create audit logs:

```php
use App\Models\AuditLog;

AuditLog::log(
    action: 'cancelled',
    model: $appointment,
    oldValues: $appointment->getOriginal(),
    newValues: $appointment->getAttributes(),
    description: 'Appointment cancelled by patient request'
);
```

### Retrieving Audit Logs

Get all audit logs for a model:

```php
$logs = $appointment->auditLogs()->get();
```

Get recent audit logs:

```php
$recentLogs = $appointment->recentAuditLogs(limit: 5);
```

Query audit logs:

```php
$logs = AuditLog::where('model_type', Appointment::class)
    ->where('action', 'updated')
    ->whereBetween('created_at', [$startDate, $endDate])
    ->with('user')
    ->get();
```

## Audit Log Structure

Each audit log entry contains:

- `user_id` - Who made the change
- `action` - What action was performed (created, updated, deleted, etc.)
- `model_type` - The model class name
- `model_id` - The ID of the affected record
- `old_values` - Complete state before change (JSON)
- `new_values` - Complete state after change (JSON)
- `changes` - Only the fields that changed (JSON)
- `ip_address` - IP address of the user
- `user_agent` - Browser/client information
- `description` - Optional description
- `created_at` - When the change occurred

## Example Audit Log

```json
{
  "id": 123,
  "user_id": 5,
  "action": "updated",
  "model_type": "App\\Models\\Appointment",
  "model_id": 42,
  "old_values": {
    "status": "planned",
    "comment": null
  },
  "new_values": {
    "status": "confirmed",
    "comment": "Patient confirmed via phone"
  },
  "changes": {
    "status": {
      "old": "planned",
      "new": "confirmed"
    },
    "comment": {
      "old": null,
      "new": "Patient confirmed via phone"
    }
  },
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "description": null,
  "created_at": "2025-01-15T10:30:00.000000Z"
}
```

## API Endpoints

### Get Audit Logs for a Model

```http
GET /api/appointments/{id}/audit-logs
Authorization: Bearer {token}
```

### Get All Audit Logs (Admin Only)

```http
GET /api/audit-logs
Authorization: Bearer {token}
```

Query parameters:
- `model_type` - Filter by model type
- `action` - Filter by action
- `user_id` - Filter by user
- `from_date` - Filter from date
- `to_date` - Filter to date
- `per_page` - Pagination (default: 25)

## Security Considerations

1. **Sensitive Data**: Be careful not to log sensitive information like passwords or payment details
2. **Access Control**: Only administrators should have access to audit logs
3. **Retention**: Consider implementing a retention policy to delete old logs
4. **Performance**: Audit logging adds overhead; use indexes for better performance

## Performance Tips

The `audit_logs` table has indexes on:
- `model_type` + `model_id` (composite)
- `user_id`
- `action`
- `created_at`

For better performance with large datasets:

1. Archive old logs periodically
2. Use queue jobs for heavy audit operations
3. Consider using a separate database for audit logs

## Cleanup Old Logs

Create a scheduled command to clean up old audit logs:

```php
// In app/Console/Kernel.php
$schedule->command('audit:cleanup --days=365')->monthly();
```

```bash
php artisan make:command AuditCleanup
```

```php
// In the command
AuditLog::where('created_at', '<', now()->subDays($days))->delete();
```

