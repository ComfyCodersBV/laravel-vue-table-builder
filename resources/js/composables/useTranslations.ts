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

        let value: string | undefined = key
            .split('.')
            .reduce((obj, k) => obj?.[k], translations)

        if (!value || typeof value !== 'string') return key.split('.').pop() ?? key

        Object.entries(replacements)
            .sort((a, b) => b[0].length - a[0].length)
            .forEach(([k, v]) => {
                value = (value as string).replace(new RegExp(`:${k}`, 'g'), String(v))
            })

        return value
    }

    return { t }
}
