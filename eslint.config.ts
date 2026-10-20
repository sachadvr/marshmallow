import stylistic from '@stylistic/eslint-plugin'
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting'
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript'
import pluginVue from 'eslint-plugin-vue'

export default defineConfigWithVueTs(
  {
    name: 'app/files-to-ignore',
    ignores: [
      'vendor/**',
      'var/**',
      'public/build/**',
      'public/bundles/**',
      'assets/api/generated/**',
    ],
  },
  pluginVue.configs['flat/recommended'],
  vueTsConfigs.recommendedTypeChecked,
  skipFormatting,
  {
    name: 'app/no-else',
    rules: {
      'no-restricted-syntax': [
        'error',
        {
          selector: 'IfStatement[alternate]',
          message: 'Avoid else: use an early return, a guard clause or a ternary.',
        },
      ],
    },
  },
  {
    name: 'app/blank-line-before-control-flow',
    plugins: { '@stylistic': stylistic },
    rules: {
      '@stylistic/padding-line-between-statements': [
        'error',
        {
          blankLine: 'always',
          prev: '*',
          next: ['if', 'for', 'while', 'do', 'switch', 'try', 'return', 'throw'],
        },
      ],
    },
  },
)
