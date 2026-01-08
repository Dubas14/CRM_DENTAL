// API Response Types

export interface ApiResponse<T> {
  data: T
  message?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number | null
    last_page: number
    path: string
    per_page: number
    to: number | null
    total: number
  }
}

export interface ValidationError {
  message: string
  errors: Record<string, string[]>
}

export interface ApiError {
  message: string
  error?: string
  errors?: Record<string, string[]>
}

// User & Auth Types
export interface User {
  id: number
  name: string
  first_name?: string
  last_name?: string
  email: string
  global_role: 'super_admin' | 'clinic_admin' | 'doctor' | 'registrar' | 'user'
  roles?: string[]
  doctor?: Doctor
  created_at: string
  updated_at: string
}

export interface LoginRequest {
  email: string
  password: string
}

export interface LoginResponse {
  token: string
  user: User
}

// Clinic Types
export interface Clinic {
  id: number
  name: string
  legal_name?: string
  address?: string
  city?: string
  phone?: string
  email?: string
  website?: string
  logo_url?: string
  phone_main?: string
  email_public?: string
  address_street?: string
  address_building?: string
  postal_code?: string
  slogan?: string
  currency_code?: string
  requisites?: {
    legal_name?: string
    tax_id?: string
    iban?: string
    bank_name?: string
    mfo?: string
  }
  created_at: string
  updated_at: string
}

// Doctor Types
export interface Doctor {
  id: number
  clinic_id: number
  user_id?: number
  full_name: string
  specialization?: string
  phone?: string
  email?: string
  color?: string
  is_active: boolean
  clinic?: Clinic
  created_at: string
  updated_at: string
}

// Patient Types
export interface Patient {
  id: number
  clinic_id: number
  full_name: string
  phone?: string
  email?: string
  birth_date?: string
  address?: string
  note?: string
  clinic?: Clinic
  created_at: string
  updated_at: string
}

// Procedure Types
export interface Procedure {
  id: number
  clinic_id: number
  name: string
  category?: string
  duration_minutes: number
  requires_room: boolean
  requires_assistant: boolean
  default_room_id?: number
  equipment_id?: number
  metadata?: Record<string, any>
  steps?: ProcedureStep[]
  rooms?: Room[]
  created_at: string
  updated_at: string
}

export interface ProcedureStep {
  id: number
  procedure_id: number
  name: string
  duration_minutes: number
  order: number
  description?: string
  created_at: string
  updated_at: string
}

// Room & Equipment Types
export interface Room {
  id: number
  clinic_id: number
  name: string
  type?: string
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Equipment {
  id: number
  clinic_id: number
  name: string
  type?: string
  is_active: boolean
  created_at: string
  updated_at: string
}

// Appointment Types
export type AppointmentStatus =
  | 'planned'
  | 'confirmed'
  | 'reminded'
  | 'waiting'
  | 'done'
  | 'cancelled'
  | 'no_show'

export interface Appointment {
  id: number
  clinic_id: number
  doctor_id: number
  patient_id?: number
  procedure_id?: number
  procedure_step_id?: number
  room_id?: number
  equipment_id?: number
  assistant_id?: number
  start_at: string
  end_at: string
  status: AppointmentStatus
  source?: string
  comment?: string
  is_follow_up: boolean
  patient_name?: string
  doctor?: Doctor
  patient?: Patient
  procedure?: Procedure
  procedure_step?: ProcedureStep
  room?: Room
  equipment?: Equipment
  assistant?: User
  clinic?: Clinic
  created_at: string
  updated_at: string
}

export interface CreateAppointmentRequest {
  doctor_id: number
  date: string
  time: string
  procedure_id?: number
  procedure_step_id?: number
  room_id?: number
  equipment_id?: number
  assistant_id?: number
  patient_id?: number
  is_follow_up?: boolean
  source?: string
  comment?: string
  waitlist_entry_id?: number
  allow_soft_conflicts?: boolean
}

export interface UpdateAppointmentRequest {
  doctor_id?: number
  date?: string
  time?: string
  start_at?: string
  end_at?: string
  procedure_id?: number
  procedure_step_id?: number
  room_id?: number
  equipment_id?: number
  assistant_id?: number
  patient_id?: number
  is_follow_up?: boolean
  status?: AppointmentStatus
  comment?: string
  allow_soft_conflicts?: boolean
}

// Schedule Types
export interface Schedule {
  id: number
  doctor_id: number
  weekday: number
  start_time: string
  end_time: string
  break_start?: string
  break_end?: string
  slot_duration_minutes: number
  created_at: string
  updated_at: string
}

export interface TimeSlot {
  start: string
  end: string
}

export interface SlotsResponse {
  slots: TimeSlot[]
  reason?: string
  duration_minutes?: number
}

// Waitlist Types
export interface WaitlistEntry {
  id: number
  clinic_id: number
  patient_id: number
  doctor_id?: number
  procedure_id?: number
  preferred_date?: string
  preferred_time_start?: string
  preferred_time_end?: string
  priority?: number
  status: 'pending' | 'booked' | 'cancelled'
  comment?: string
  patient?: Patient
  doctor?: Doctor
  procedure?: Procedure
  created_at: string
  updated_at: string
}

// Medical Record Types
export interface MedicalRecord {
  id: number
  patient_id: number
  doctor_id?: number
  appointment_id?: number
  tooth_number?: number
  diagnosis: string
  treatment: string
  complaints?: string
  created_at: string
  updated_at: string
}

// Audit Log Types
export interface AuditLog {
  id: number
  user_id?: number
  action: string
  model_type: string
  model_id: number
  old_values?: Record<string, any>
  new_values?: Record<string, any>
  changes?: Record<string, { old: any; new: any }>
  ip_address?: string
  user_agent?: string
  description?: string
  user?: User
  created_at: string
  updated_at: string
}
