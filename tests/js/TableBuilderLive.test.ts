import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest'
import {router} from '@inertiajs/vue3'
import {mount} from '@vue/test-utils'
import TableBuilder from '../../resources/js/components/TableBuilder.vue'
import type {BulkAction, Column, TableData} from '../../resources/js/types/table-builder'

vi.mock('@inertiajs/vue3', () => ({
    router: {get: vi.fn(), reload: vi.fn(), post: vi.fn(), visit: vi.fn()},
    usePage: () => ({props: {}}),
}))

const bulkUrl = '/table/bulk-action?signature=x'

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

function bulkAction(overrides: Partial<BulkAction> = {}): BulkAction {
    return {
        key: '0',
        label: 'Move to',
        url: bulkUrl,
        confirm: false,
        confirmText: '',
        confirmButton: '',
        cancelButton: '',
        requirePassword: false,
        ...overrides,
    }
}

function tableData(overrides: Partial<TableData> = {}): TableData {
    return {
        name: 'lines',
        data: [{id: 'a', name: 'First'}, {id: 'b', name: 'Second'}],
        columns: [column('name')],
        filters: [],
        searchInputs: {},
        perPageOptions: [],
        defaultSort: '',
        bulkActions: [],
        rowLinks: [],
        rowLinkType: '',
        rowLinkTarget: '_self',
        ...overrides,
    } as unknown as TableData
}

function flush(): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, 0))
}

async function selectFirstRow(wrapper: ReturnType<typeof mount>): Promise<void> {
    await wrapper.find('tbody [role="checkbox"]').trigger('click')
    await flush()
}

async function runBulkAction(wrapper: ReturnType<typeof mount>, label: string): Promise<void> {
    const trigger = wrapper.findAll('button').find((button) => button.text() === 'title')!
    await trigger.trigger('click')
    await flush()

    const item = Array.from(document.querySelectorAll<HTMLElement>('[role="menuitem"]')).find((element) => element.textContent?.trim() === label)!
    item.click()
    await flush()
}

function choiceDialog(): HTMLElement | null {
    return document.querySelector<HTMLElement>('[data-bulk-choice-dialog]')
}

describe('TableBuilder bulk actions with choices', () => {
    const choices = [{value: '1', label: 'Inbox'}, {value: '2', label: 'Archive'}]

    beforeEach(() => {
        vi.mocked(router.post).mockClear()
    })

    afterEach(() => {
        document.body.innerHTML = ''
    })

    it('asks for a choice and posts it with the selected ids', async () => {
        const table = tableData({bulkActions: [bulkAction({choices, choicesLabel: 'Folder', confirmButton: 'Move'})]})
        const wrapper = mount(TableBuilder, {props: {table}, attachTo: document.body})

        await selectFirstRow(wrapper)
        await runBulkAction(wrapper, 'Move to')

        expect(router.post).not.toHaveBeenCalled()

        const dialog = choiceDialog()!
        const select = dialog.querySelector('select')!

        expect(dialog.textContent).toContain('Folder')
        expect(Array.from(select.options).map((option) => option.textContent?.trim())).toEqual(['choose', 'Inbox', 'Archive'])

        select.value = '2'
        select.dispatchEvent(new Event('change'))
        await flush()

        dialog.querySelector('form')!.dispatchEvent(new Event('submit', {cancelable: true}))
        await flush()

        expect(router.post).toHaveBeenCalledWith(bulkUrl, {ids: ['a'], choice: '2'}, expect.anything())
        expect(choiceDialog()).toBeNull()

        wrapper.unmount()
    })

    it('posts nothing when the choice dialog is cancelled', async () => {
        const table = tableData({bulkActions: [bulkAction({choices, cancelButton: 'Keep'})]})
        const wrapper = mount(TableBuilder, {props: {table}, attachTo: document.body})

        await selectFirstRow(wrapper)
        await runBulkAction(wrapper, 'Move to')

        Array.from(choiceDialog()!.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Keep')!.click()
        await flush()

        expect(choiceDialog()).toBeNull()
        expect(router.post).not.toHaveBeenCalled()

        wrapper.unmount()
    })

    it('posts a bulk action without choices straight away and without a choice', async () => {
        const table = tableData({bulkActions: [bulkAction({label: 'Resolve'})]})
        const wrapper = mount(TableBuilder, {props: {table}, attachTo: document.body})

        await selectFirstRow(wrapper)
        await runBulkAction(wrapper, 'Resolve')

        expect(choiceDialog()).toBeNull()
        expect(router.post).toHaveBeenCalledWith(bulkUrl, {ids: ['a']}, expect.anything())

        wrapper.unmount()
    })
})

describe('TableBuilder polling', () => {
    beforeEach(() => {
        vi.useFakeTimers()
        vi.mocked(router.reload).mockClear()
    })

    afterEach(() => {
        vi.useRealTimers()
        document.body.innerHTML = ''
    })

    function setHidden(hidden: boolean): void {
        Object.defineProperty(document, 'hidden', {configurable: true, get: () => hidden})
        document.dispatchEvent(new Event('visibilitychange'))
    }

    it('reloads only its own prop on every interval and stops when unmounted', async () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData({poll: 10}), only: ['lines']}})

        vi.advanceTimersByTime(10_000)
        expect(router.reload).toHaveBeenCalledTimes(1)
        expect(router.reload).toHaveBeenCalledWith({only: ['lines']})

        vi.advanceTimersByTime(10_000)
        expect(router.reload).toHaveBeenCalledTimes(2)

        wrapper.unmount()
        vi.advanceTimersByTime(30_000)

        expect(router.reload).toHaveBeenCalledTimes(2)
    })

    it('warns once in development when an inertia table polls without only', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined)
        const wrapper = mount(TableBuilder, {props: {table: tableData({poll: 5, schemaVersion: '1.0'})}})

        vi.advanceTimersByTime(15_000)

        expect(router.reload).toHaveBeenCalledTimes(3)
        expect(router.reload).toHaveBeenCalledWith({})
        expect(warn.mock.calls.filter(([message]) => String(message).includes('without an `only` prop'))).toHaveLength(1)

        wrapper.unmount()
        warn.mockRestore()
    })

    it('does not poll a table without a poll interval', () => {
        mount(TableBuilder, {props: {table: tableData()}})

        vi.advanceTimersByTime(60_000)

        expect(router.reload).not.toHaveBeenCalled()
    })

    it('pauses while rows are selected', async () => {
        const table = tableData({poll: 5, bulkActions: [bulkAction({label: 'Resolve'})]})
        const wrapper = mount(TableBuilder, {props: {table}})

        await wrapper.find('tbody [role="checkbox"]').trigger('click')
        vi.advanceTimersByTime(20_000)

        expect(wrapper.text()).toContain('selected_simple')
        expect(router.reload).not.toHaveBeenCalled()

        wrapper.unmount()
    })

    it('pauses while the page is hidden and catches up when it becomes visible', () => {
        const wrapper = mount(TableBuilder, {props: {table: tableData({poll: 5})}})

        setHidden(true)
        vi.advanceTimersByTime(20_000)

        expect(router.reload).not.toHaveBeenCalled()

        setHidden(false)

        expect(router.reload).toHaveBeenCalledTimes(1)

        wrapper.unmount()
    })

    it('refetches silently over http with the current query', async () => {
        vi.useRealTimers()
        const fetcher = vi.fn().mockResolvedValue({data: [{id: 'a', name: 'First'}], pagination: {current_page: 1, last_page: 1, per_page: 10, total: 1}})
        const wrapper = mount(TableBuilder, {props: {table: tableData({poll: 1, data: []}), transport: 'http', source: '/lines', fetcher}})
        await flush()

        expect(fetcher).toHaveBeenCalledTimes(1)

        await new Promise((resolve) => setTimeout(resolve, 1100))

        expect(fetcher).toHaveBeenCalledTimes(2)
        expect(fetcher.mock.calls[1][0]).toEqual(fetcher.mock.calls[0][0])
        expect(wrapper.find('[aria-busy="true"]').exists()).toBe(false)

        wrapper.unmount()
    })
})

describe('TableBuilder row classes', () => {
    it('merges the server class of a row with the rowClass prop, the prop winning', () => {
        const table = tableData({data: [{id: 'a', name: 'First', __class: 'font-semibold text-red-600'}, {id: 'b', name: 'Second'}]})
        const wrapper = mount(TableBuilder, {
            props: {table, rowClass: (row: any) => (row.id === 'a' ? 'text-blue-600' : 'italic')},
        })

        const rows = wrapper.findAll('tbody tr')

        expect(rows[0].classes()).toEqual(expect.arrayContaining(['font-semibold', 'text-blue-600']))
        expect(rows[0].classes()).not.toContain('text-red-600')
        expect(rows[1].classes()).toContain('italic')
    })

    it('applies the server class of a row without a rowClass prop', () => {
        const table = tableData({data: [{id: 'a', name: 'First', __class: ['opacity-50']}]})

        expect(mount(TableBuilder, {props: {table}}).find('tbody tr').classes()).toContain('opacity-50')
    })
})
