// Adapter interfaces to ease future migration to Ant/Naive.

export interface UIButtonProps {
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  block?: boolean
  loading?: boolean
  disabled?: boolean
}

export interface UIDrawerProps {
  modelValue: boolean
  title?: string
  width?: string
  closable?: boolean
  closeOnEsc?: boolean
  closeOnOutside?: boolean
}

export interface UITabItem {
  id: string
  label: string
  badge?: string | number
}

export interface UITabsProps {
  modelValue: string
  tabs: UITabItem[]
}

export interface UIDropdownItem {
  id: string
  label: string
  icon?: string
}

export interface UIDropdownProps {
  items: UIDropdownItem[]
  placement?: 'bottom-start' | 'bottom-end'
}

export interface UIAvatarProps {
  src?: string
  alt?: string
  size?: 'sm' | 'md' | 'lg' | number
  fallbackText?: string
}

export interface UIBadgeProps {
  variant?: 'success' | 'info' | 'warning' | 'danger' | 'neutral'
  small?: boolean
}

