export type DoctorStatus = 'active' | 'inactive' | 'vacation'

export interface DoctorClinic {
  id: number | string
  name: string
}

export interface Doctor {
  id: number
  full_name: string
  specialization?: string
  clinic?: DoctorClinic
  is_active?: boolean
  avatar_url?: string | null
  email?: string | null
  phone?: string | null
  room?: string | null
  admin_contact?: string | null
  address?: string | null
  city?: string | null
  state?: string | null
  zip?: string | null
  status?: DoctorStatus
}

export interface DoctorProcedure {
  id: number
  name: string
  category?: string
  duration_minutes?: number
  is_assigned?: boolean
  custom_duration_minutes?: number | null
}

