import {describe, expect, it, vi} from 'vitest'
import {mount} from '@vue/test-utils'
import TablePagination from '../../resources/js/components/TablePagination.vue'

vi.mock('@inertiajs/vue3', () => ({usePage: () => ({props: {}})}))

const pagination = {current_page: 2, last_page: 3, per_page: 25, total: 60, from: 26, to: 50}

describe('TablePagination test hooks', () => {
    it('prefixes the compact pagination so it differs from the one below the table', () => {
        const below = mount(TablePagination, {props: {pagination}})
        const above = mount(TablePagination, {props: {pagination, compact: true}})

        expect(below.find('[data-testid="table-next"]').exists()).toBe(true)
        expect(below.find('[data-testid="table-count"]').exists()).toBe(true)
        expect(above.find('[data-testid="table-top-next"]').exists()).toBe(true)
        expect(above.find('[data-testid="table-next"]').exists()).toBe(false)
    })
})
