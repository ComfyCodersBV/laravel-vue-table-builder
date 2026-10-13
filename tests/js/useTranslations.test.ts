import {describe, expect, it, vi} from 'vitest'
import {TABLE_TRANSLATIONS, useTranslations} from '../../resources/js/composables/useTranslations'

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            vue_table_builder_table_translations: {
                no_results: 'No results found.',
                bulk_actions: {
                    selected_simple: ':count :type selected',
                },
            },
            vue_mailbox_translations: {
                table: {
                    subject: 'Table subject',
                },
                compose: {
                    title: 'New message',
                },
            },
        },
    }),
}))

describe('useTranslations', () => {
    it('strips the table group for the table builder bundle', () => {
        const {t} = useTranslations(TABLE_TRANSLATIONS)

        expect(t('vue-table-builder::table.no_results')).toBe('No results found.')
        expect(t('table.no_results')).toBe('No results found.')
        expect(t('table.bulk_actions.selected_simple', {count: 2, type: 'rows'})).toBe('2 rows selected')
    })

    it('reads keys of another bundle without stripping a table prefix', () => {
        const {t} = useTranslations('vue_mailbox_translations')

        expect(t('table.subject')).toBe('Table subject')
        expect(t('compose.title')).toBe('New message')
        expect(t('mailbox::compose.title')).toBe('New message')
    })

    it('strips the group a bundle is shared under when it is given', () => {
        const {t} = useTranslations('vue_mailbox_translations', {group: 'mailbox'})

        expect(t('mailbox::mailbox.compose.title')).toBe('New message')
        expect(t('compose.title')).toBe('New message')
    })

    it('falls back to the last segment of a missing key', () => {
        const {t} = useTranslations('vue_mailbox_translations')

        expect(t('compose.missing')).toBe('missing')
    })
})
