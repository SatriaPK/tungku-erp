import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import { defineConfig, globalIgnores } from 'eslint/config'
import tsParser from '@typescript-eslint/parser'

export default defineConfig([
  globalIgnores(['dist']),
  {
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      parser: tsParser,
      parserOptions: { ecmaFeatures: { jsx: true } },
      globals: globals.browser,
    },
    extends: [
      {
        ...js.configs.recommended,
        rules: {
          ...js.configs.recommended.rules,
          'no-unused-vars': 'off',
        },
      },
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
  },
  {
    files: ['**/*.{js,jsx}'],
    extends: [
      {
        ...js.configs.recommended,
        rules: {
          ...js.configs.recommended.rules,
          'no-unused-vars': 'off',
        },
      },
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      globals: globals.browser,
      parserOptions: { ecmaFeatures: { jsx: true } },
    },
  },
])
