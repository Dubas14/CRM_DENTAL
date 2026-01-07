import pluginVue from 'eslint-plugin-vue'
import vueTsEslintConfig from '@vue/eslint-config-typescript'
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting'

export default [
  {
    name: 'app/files-to-lint',
    files: ['**/*.{ts,mts,tsx,vue}']
  },

  {
    name: 'app/files-to-ignore',
    ignores: ['**/dist/**', '**/dist-ssr/**', '**/coverage/**']
  },

  ...pluginVue.configs['flat/essential'],
  ...vueTsEslintConfig(),
  skipFormatting,

  {
    name: 'app/custom-rules',
    rules: {
      // Дозволяємо any для швидкої розробки (TODO: поступово виправити)
      '@typescript-eslint/no-explicit-any': 'warn',
      // Дозволяємо однослівні назви UI компонентів та деяких views
      'vue/multi-word-component-names': ['error', {
        ignores: ['Avatar', 'Badge', 'Button', 'Drawer', 'Dropdown', 'Tabs', 'Employees']
      }],
      // Невикористані змінні - warning (з можливістю ігнорувати через _)
      '@typescript-eslint/no-unused-vars': ['warn', {
        argsIgnorePattern: '^_',
        varsIgnorePattern: '^_',
        caughtErrors: 'none'
      }],
      // Empty object types
      '@typescript-eslint/no-empty-object-type': 'off'
    }
  }
]
