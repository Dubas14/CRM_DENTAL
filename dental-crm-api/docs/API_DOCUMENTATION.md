# API Documentation - Dental CRM

## Base URL

```
http://localhost/api
```

## Authentication

All API endpoints (except login) require authentication using Laravel Sanctum Bearer token.

Add the token to the Authorization header:

```
Authorization: Bearer YOUR_TOKEN_HERE
```

---

## Authentication Endpoints

### POST /login

Authenticate a user and receive an access token.

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "global_role": "super_admin",
    "doctor": null
  }
}
```

**Errors:**
- `422 Unprocessable Entity` - Invalid credentials

### POST /logout

Logout the authenticated user and revoke the current token.

**Response (204 No Content)**

### GET /user

Get the authenticated user's information.

**Response (200 OK):**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "user@example.com",
  "global_role": "super_admin",
  "roles": ["super_admin"],
  "doctor": null
}
```

---

## Patients Endpoints

### GET /patients

Get a list of patients with pagination.

**Query Parameters:**
- `clinic_id` (optional) - Filter by clinic ID
- `search` (optional) - Search by full name, phone, or email
- `per_page` (optional, default: 15) - Number of results per page

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "clinic_id": 1,
      "full_name": "Іван Петренко",
      "phone": "+380501234567",
      "email": "ivan@example.com",
      "birth_date": "1990-01-15",
      "address": "Київ, вул. Хрещатик, 1",
      "clinic": {
        "id": 1,
        "name": "Стоматологія №1"
      }
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 100
}
```

### POST /patients

Create a new patient.

**Request Body:**
```json
{
  "clinic_id": 1,
  "full_name": "Іван Петренко",
  "phone": "+380501234567",
  "email": "ivan@example.com",
  "birth_date": "1990-01-15",
  "address": "Київ, вул. Хрещатик, 1",
  "note": "Алергія на анестезію"
}
```

**Response (201 Created):**
```json
{
  "id": 1,
  "clinic_id": 1,
  "full_name": "Іван Петренко",
  "phone": "+380501234567",
  "email": "ivan@example.com",
  "created_at": "2025-01-01T10:00:00.000000Z"
}
```

### GET /patients/{id}

Get a specific patient by ID.

**Response (200 OK):**
```json
{
  "id": 1,
  "clinic_id": 1,
  "full_name": "Іван Петренко",
  "phone": "+380501234567",
  "email": "ivan@example.com",
  "birth_date": "1990-01-15",
  "appointments": []
}
```

### PUT /patients/{id}

Update a patient.

**Request Body:** (all fields optional)
```json
{
  "full_name": "Іван Іванович Петренко",
  "phone": "+380501234567"
}
```

**Response (200 OK):** Updated patient object

### DELETE /patients/{id}

Delete a patient.

**Response (200 OK):**
```json
{
  "message": "Patient deleted successfully"
}
```

---

## Appointments Endpoints

### GET /appointments

Get a list of appointments.

**Query Parameters:**
- `date` (optional) - Filter by specific date (YYYY-MM-DD)
- `from_date` (optional) - Filter from date
- `to_date` (optional) - Filter to date
- `doctor_id` (optional) - Filter by doctor ID
- `clinic_id` (optional) - Filter by clinic ID

**Response (200 OK):**
```json
[
  {
    "id": 1,
    "doctor_id": 1,
    "patient_id": 1,
    "start_at": "2025-01-06T10:00:00.000000Z",
    "end_at": "2025-01-06T11:00:00.000000Z",
    "status": "confirmed",
    "doctor": {
      "id": 1,
      "full_name": "Др. Іванов"
    },
    "patient": {
      "id": 1,
      "full_name": "Іван Петренко"
    },
    "procedure": {
      "id": 1,
      "name": "Огляд"
    }
  }
]
```

### POST /appointments

Create a new appointment.

**Request Body:**
```json
{
  "doctor_id": 1,
  "patient_id": 1,
  "date": "2025-01-06",
  "time": "10:00",
  "procedure_id": 1,
  "room_id": 1,
  "comment": "Перший візит"
}
```

**Response (201 Created):** Appointment object

### PUT /appointments/{id}

Update an appointment.

**Request Body:** (all fields optional)
```json
{
  "status": "confirmed",
  "comment": "Підтверджено"
}
```

**Response (200 OK):** Updated appointment object

### POST /appointments/{id}/cancel

Cancel an appointment.

**Request Body:**
```json
{
  "reason": "Пацієнт не може прийти"
}
```

**Response (200 OK):**
```json
{
  "message": "Appointment cancelled successfully"
}
```

### GET /doctors/{doctor_id}/appointments

Get appointments for a specific doctor.

**Query Parameters:**
- `date` (optional) - Filter by specific date

**Response (200 OK):** Array of appointments

---

## Calendar & Scheduling Endpoints

### GET /doctors/{doctor_id}/schedule

Get the weekly schedule for a doctor.

**Response (200 OK):**
```json
{
  "schedules": [
    {
      "weekday": 1,
      "start_time": "09:00",
      "end_time": "17:00",
      "break_start": "12:00",
      "break_end": "13:00",
      "slot_duration_minutes": 30
    }
  ],
  "exceptions": []
}
```

### GET /doctors/{doctor_id}/slots

Get available time slots for a doctor.

**Query Parameters:**
- `date` (required) - Date to check (YYYY-MM-DD)
- `procedure_id` (optional) - Filter slots compatible with procedure
- `duration` (optional) - Required duration in minutes

**Response (200 OK):**
```json
{
  "slots": [
    {
      "start": "2025-01-06T09:00:00.000000Z",
      "end": "2025-01-06T09:30:00.000000Z"
    },
    {
      "start": "2025-01-06T09:30:00.000000Z",
      "end": "2025-01-06T10:00:00.000000Z"
    }
  ],
  "date": "2025-01-06"
}
```

### GET /doctors/{doctor_id}/recommended-slots

Get recommended available slots for booking.

**Query Parameters:**
- `procedure_id` (optional)
- `from_date` (optional, default: today)
- `limit` (optional, default: 5)

**Response (200 OK):**
```json
{
  "slots": [
    {
      "date": "2025-01-06",
      "start": "10:00",
      "end": "11:00",
      "doctor_id": 1
    }
  ]
}
```

---

## Doctors Endpoints

### GET /doctors

Get a list of doctors.

**Query Parameters:**
- `clinic_id` (optional) - Filter by clinic ID

**Response (200 OK):**
```json
[
  {
    "id": 1,
    "full_name": "Др. Іванов",
    "specialization": "Терапевт",
    "phone": "+380501111111",
    "email": "doctor@clinic.com",
    "is_active": true,
    "clinic": {
      "id": 1,
      "name": "Стоматологія №1"
    }
  }
]
```

### POST /doctors

Create a new doctor.

**Request Body:**
```json
{
  "clinic_id": 1,
  "full_name": "Др. Іванов Іван Іванович",
  "specialization": "Терапевт",
  "phone": "+380501111111",
  "email": "doctor@clinic.com",
  "color": "#FF5733",
  "create_user": true,
  "password": "securepassword"
}
```

**Response (201 Created):** Doctor object

---

## Procedures Endpoints

### GET /procedures

Get a list of procedures.

**Query Parameters:**
- `clinic_id` (optional) - Filter by clinic ID

**Response (200 OK):**
```json
[
  {
    "id": 1,
    "name": "Огляд",
    "category": "Діагностика",
    "duration_minutes": 30,
    "requires_room": true,
    "requires_assistant": false
  }
]
```

### POST /procedures

Create a new procedure.

**Request Body:**
```json
{
  "clinic_id": 1,
  "name": "Огляд",
  "category": "Діагностика",
  "duration_minutes": 30,
  "requires_room": true,
  "requires_assistant": false
}
```

**Response (201 Created):** Procedure object

---

## Waitlist Endpoints

### GET /waitlist

Get waitlist entries.

**Query Parameters:**
- `clinic_id` (optional)

**Response (200 OK):** Paginated list of waitlist entries

### POST /waitlist

Add a patient to the waitlist.

**Request Body:**
```json
{
  "clinic_id": 1,
  "patient_id": 1,
  "doctor_id": 1,
  "procedure_id": 1,
  "preferred_date": "2025-01-10",
  "priority": 5
}
```

**Response (201 Created):** Waitlist entry object

### GET /waitlist/candidates

Get waitlist candidates for a freed slot.

**Query Parameters:**
- `clinic_id` (required)
- `doctor_id` (optional)
- `procedure_id` (optional)
- `date` (optional)

**Response (200 OK):** Array of matching waitlist entries

---

## Status Codes

- `200 OK` - Request successful
- `201 Created` - Resource created successfully
- `204 No Content` - Request successful, no content to return
- `400 Bad Request` - Invalid request
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Access denied
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation errors
- `500 Internal Server Error` - Server error

---

## Error Response Format

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "Error message"
    ]
  }
}
```
