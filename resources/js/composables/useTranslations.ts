import {usePage} from '@inertiajs/vue3'

export const TABLE_TRANSLATIONS = 'vue_table_builder_table_translations'

export type TranslationReplacements = Record<string, string | number>

export interface UseTranslationsOptions {
    group?: string | null
}

function stripPrefix(key: string, prefix: string): string {
    return key.startsWith(prefix) ? key.slice(prefix.length) : key
}

export function useTranslations(namespace: string = TABLE_TRANSLATIONS, options: UseTranslationsOptions = {}) {
    const page = usePage()

    const translations = (page.props as Record<string, any>)[namespace] || {}

    const group = options.group !== undefined ? options.group : (namespace === TABLE_TRANSLATIONS ? 'table' : null)

    function t(key: string, replacements: TranslationReplacements = {}): string {
        if (key.includes('::')) {
            key = key.split('::')[1]
        }

        if (group) {
            key = stripPrefix(key, `${group}.`)
        }

        let value: unknown = key
            .split('.')
            .reduce<any>((object, segment) => object?.[segment], translations)

        if (!value || typeof value !== 'string') {
            return key.split('.').pop() ?? key
        }

        Object.entries(replacements)
            .sort((a, b) => b[0].length - a[0].length)
            .forEach(([name, replacement]) => {
                value = (value as string).replace(new RegExp(`:${name}`, 'g'), String(replacement))
            })

        return value as string
    }

    return {t}
}
