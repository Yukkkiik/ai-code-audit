// Config ESLint milik platform. Dipakai dengan `--config` + `--no-config-lookup`: config, plugin, dan skrip dari
// repositori target tidak pernah dimuat. Parser TypeScript tanpa `project` (tanpa tsconfig target, tanpa type-aware).
import tsParser from '@typescript-eslint/parser';
import security from 'eslint-plugin-security';

export default [
  {
    files: ['**/*.{js,jsx,mjs,cjs}'],
    languageOptions: {
      ecmaVersion: 'latest',
      parserOptions: { ecmaFeatures: { jsx: true } },
    },
  },
  {
    files: ['**/*.{ts,tsx,mts,cts}'],
    languageOptions: {
      parser: tsParser,
      parserOptions: { ecmaFeatures: { jsx: true } },
    },
  },
  {
    files: ['**/*.{js,jsx,mjs,cjs,ts,tsx,mts,cts}'],
    linterOptions: { reportUnusedDisableDirectives: 'off' },
    plugins: { security },
    rules: {
      ...security.configs.recommended.rules,
      // Terlalu banyak false positive untuk pemindaian otomatis.
      'security/detect-object-injection': 'off',

      'no-eval': 'error',
      'no-implied-eval': 'error',
      'no-new-func': 'error',
      'no-script-url': 'error',

      'no-debugger': 'warn',
      'no-fallthrough': 'warn',
      'no-unsafe-finally': 'error',
      'no-unsafe-negation': 'error',
      'no-self-assign': 'warn',
    },
  },
];
