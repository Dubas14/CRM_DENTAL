// Tailwind-first design tokens to keep UI styling centralized.
// Can be remapped to Ant/Naive later via adapters.

export const colorTokens = {
  primary: 'bg-emerald-600 hover:bg-emerald-500 text-white',
  secondary: 'bg-card text-text border border-border hover:bg-card/80',
  ghost: 'text-text/80 hover:text-text hover:bg-card/70',
  danger: 'bg-rose-600 hover:bg-rose-500 text-white',
  muted: 'text-text/70',
  badge: {
    success: 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/20',
    info: 'bg-sky-500/15 text-sky-300 border border-sky-500/20',
    warning: 'bg-amber-500/15 text-amber-300 border border-amber-500/20',
    danger: 'bg-rose-500/15 text-rose-300 border border-rose-500/20',
    neutral: 'bg-card/70 text-text/80 border border-border/60'
  },
  overlay: 'bg-black/50 dark:bg-black/60'
}

export const sizeTokens = {
  sm: 'text-xs px-3 py-1.5 rounded-md',
  md: 'text-sm px-4 py-2 rounded-lg',
  lg: 'text-base px-5 py-2.5 rounded-xl'
}

export const radiusTokens = {
  sm: 'rounded-md',
  md: 'rounded-lg',
  lg: 'rounded-xl',
  full: 'rounded-full'
}

export const spacingTokens = {
  gutter: 'p-4',
  section: 'space-y-4'
}

export const themeTokens = {
  light: {
    background: 'bg-bg',
    surface: 'bg-card',
    text: 'text-text',
    border: 'border-border'
  },
  dark: {
    background: 'bg-bg',
    surface: 'bg-card',
    text: 'text-text',
    border: 'border-border'
  }
}

export type ButtonVariant = keyof typeof colorTokens | 'primary' | 'secondary' | 'ghost' | 'danger'
export type ButtonSize = keyof typeof sizeTokens
