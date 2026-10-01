import {describe, expect, it, vi} from 'vitest'
import {mount} from '@vue/test-utils'
import {h} from 'vue'
import TableBuilder from '../../resources/js/components/TableBuilder.vue'
import type {Column, TableData} from '../../resources/js/types/table-builder'

vi.mock('@inertiajs/vue3', () => ({
    router: {get: vi.fn(), reload: vi.fn(), post: vi.fn(), visit: vi.fn()},
    usePage: () => ({props: {}}),
}))

function column(key: string): Column {
    return {
        key,
        label: key,
        can_be_hidden: false,
        hidden: false,
        sortable: false,
        sorted: false,
        highlight: false,
        class: '',
        alignment: '',
        clickable: true,
        boolean: false,
    }
}

function tableData(): TableData {
    return {
        name: 'lines',
        data: [{id: 'a', name: 'First'}, {id: 'b', name: 'Second'}, {id: 'c', name: 'Third'}],
        columns: [column('name')],
        filters: [],
        searchInputs: {},
        perPageOptions: [],
        defaultSort: '',
        bulkActions: [],
        rowLinks: [],
        rowLinkType: '',
        rowLinkTarget: '_self',
    } as unknown as TableData
}

function dataTransfer() {
    return {effectAllowed: '', setData: vi.fn(), setDragImage: vi.fn()}
}

describe('TableBuilder rows', () => {
    it('emits the source and target position when a row is dropped on another', async () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData(), reorderable: true}})

        const handles = wrapper.findAll('[data-reorder-handle]')
        const rows = wrapper.findAll('tbody tr')

        await handles[0].trigger('dragstart', {dataTransfer: dataTransfer()})
        await rows[2].trigger('dragover')
        await rows[2].trigger('drop')

        expect(wrapper.emitted('reorder')).toEqual([[{from: 0, to: 2, row: {id: 'a', name: 'First'}}]])
    })

    it('does not emit when a row is dropped on itself', async () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData(), reorderable: true}})

        await wrapper.findAll('[data-reorder-handle]')[1].trigger('dragstart', {dataTransfer: dataTransfer()})
        await wrapper.findAll('tbody tr')[1].trigger('drop')

        expect(wrapper.emitted('reorder')).toBeUndefined()
    })

    it('renders no handles unless the table is reorderable', () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData()}})

        expect(wrapper.findAll('[data-reorder-handle]')).toHaveLength(0)
    })

    it('renders the row-after slot under its row with the full row width', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableData(), reorderable: true},
            slots: {
                actions: () => h('button', 'edit'),
                'row-after': ({row, colspan}: {row: any; colspan: number}) =>
                    h('tr', {class: 'nested'}, [h('td', {colspan}, `options of ${row.name}`)]),
            },
        })

        const rows = wrapper.findAll('tbody tr')

        expect(rows).toHaveLength(6)
        expect(rows[1].classes()).toContain('nested')
        expect(rows[1].text()).toBe('options of First')
        expect(rows[1].find('td').attributes('colspan')).toBe('3')
    })

    it('adds the classes returned by rowClass', () => {
        const wrapper = mount(TableBuilder, {
            props: {table: tableData(), rowClass: (row: any) => `status-${row.id}`},
        })

        expect(wrapper.findAll('tbody tr')[1].classes()).toContain('status-b')
    })

    it('emits row-click with the clicked row but not for clicks in the actions cell', async () => {
        const onRowClick = vi.fn()
        const wrapper = mount(TableBuilder, {
            props: {table: tableData(), onRowClick},
            slots: {actions: () => h('button', {class: 'action'}, 'edit')},
        })

        await wrapper.findAll('tbody tr')[1].find('td').trigger('click')
        await wrapper.findAll('.action')[0].trigger('click')

        expect(onRowClick).toHaveBeenCalledTimes(1)
        expect(onRowClick.mock.calls[0][0]).toMatchObject({row: {id: 'b', name: 'Second'}, index: 1})
        expect(wrapper.findAll('tbody tr')[1].classes()).toContain('cursor-pointer')
    })

    it('ignores clicks in an unclickable column behind the drag handle', async () => {
        const onRowClick = vi.fn()
        const table = tableData()
        table.columns = [column('name'), {...column('status'), clickable: false}]

        const wrapper = mount(TableBuilder, {props: {table, reorderable: true, onRowClick}})

        await wrapper.find('tbody tr [data-column-key="status"]').trigger('click')
        expect(onRowClick).not.toHaveBeenCalled()

        await wrapper.find('tbody tr [data-column-key="name"]').trigger('click')
        expect(onRowClick).toHaveBeenCalledTimes(1)
    })
})
