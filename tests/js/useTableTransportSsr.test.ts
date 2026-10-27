// @vitest-environment node
import {describe, expect, it, vi} from 'vitest'
import {computed, ref} from 'vue'
import {useTableTransport} from '../../resources/js/composables/useTableTransport'
import type {Column, TableData} from '../../resources/js/types/table-builder'

vi.mock('@inertiajs/vue3', () => ({
    router: {get: vi.fn(), reload: vi.fn(), post: vi.fn()},
}))

function column(key: string): Column {
    return {
        key,
        label: key,
        can_be_hidden: false,
        hidden: false,
        sortable: true,
        sorted: false,
        highlight: false,
        class: '',
        alignment: '',
        clickable: true,
        boolean: false,
    }
}

const table: TableData = {
    name: 'default',
    data: [],
    columns: [column('name'), column('slug')],
    filters: [],
    searchInputs: {},
    perPageOptions: [10],
    defaultSort: 'name',
    bulkActions: [],
    rowLinks: [],
    rowLinkType: '',
    rowLinkTarget: '_self',
}

describe('useTableTransport during server-side rendering', () => {
    it('resolves the sort of an inertia table without a window', () => {
        const transport = useTableTransport({
            table: computed(() => table),
            transport: ref('inertia'),
            source: ref(undefined),
            name: ref('default'),
        })

        expect(typeof window).toBe('undefined')
        expect(transport.sortFor(column('name'))).toBe('asc')
        expect(transport.sortFor(column('slug'))).toBe(false)
    })
})
