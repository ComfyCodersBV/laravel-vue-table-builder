import { usePage } from '@inertiajs/vue3'

export function useTranslations(namespace: string) {
    const page = usePage()

    const translations = (page.props as any)[namespace] || {}

    function t(key: string, replacements: Record<string, any> = {}) {
        if (key.includes('::')) {
            key = key.split('::')[1]
        }

        if (key.startsWith('table.')) {
            key = key.replace(/^table\./, '')
        }

        let value = key
            .split('.')
            .reduce((obj, k) => obj?.[k], translations)

        if (!value) return key

        Object.entries(replacements).forEach(([k, v]) => {
            value = value.replace(`:${k}`, String(v))
        })

        return value
    }

    return { t }
}
