/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        bg: 'rgb(var(--bg-main) / <alpha-value>)',
        card: 'rgb(var(--bg-card) / <alpha-value>)',
        text: 'rgb(var(--text-main) / <alpha-value>)',
        border: 'rgb(var(--border-soft) / <alpha-value>)',
        'bg-main': 'rgb(var(--bg-main) / <alpha-value>)',
        'bg-surface': 'rgb(var(--bg-surface) / <alpha-value>)',
        'bg-card': 'rgb(var(--bg-card) / <alpha-value>)',
        'border-soft': 'rgb(var(--border-soft) / <alpha-value>)',
        'border-strong': 'rgb(var(--border-strong) / <alpha-value>)',
        'text-main': 'rgb(var(--text-main) / <alpha-value>)',
        'text-muted': 'rgb(var(--text-muted) / <alpha-value>)',
        accent: 'rgb(var(--accent) / <alpha-value>)',
      },
    },
  },
  plugins: [],
}
