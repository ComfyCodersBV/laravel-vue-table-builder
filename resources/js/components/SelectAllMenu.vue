<script setup lang="ts">
import {DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger} from './ui/dropdown-menu'
import {Checkbox} from './ui/checkbox'
import {useTranslations} from '../composables/useTranslations'

defineProps<{
    checked: boolean
    selectedCount: number
    pageCount: number
    total: number | null
}>()

const emit = defineEmits<{
    clear: []
    selectPage: []
    selectAll: []
}>()

const {t} = useTranslations('vue_table_builder_table_translations')
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <div
                class="flex cursor-pointer items-center gap-2"
                role="button"
                tabindex="0"
                :aria-label="t('vue-table-builder::table.bulk_actions.title')"
                data-select-all
            >
                <Checkbox :model-value="checked" class="pointer-events-none h-4 w-4"/>
                <slot/>
            </div>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start">
            <DropdownMenuItem v-if="selectedCount > 0" @click="emit('clear')">
                {{ t('vue-table-builder::table.bulk_actions.clear_selection') }}
            </DropdownMenuItem>
            <DropdownMenuItem v-if="!checked" @click="emit('selectPage')">
                {{ t('vue-table-builder::table.bulk_actions.select_this_page', {count: String(pageCount)}) }}
            </DropdownMenuItem>
            <DropdownMenuItem v-if="total !== null" @click="emit('selectAll')">
                {{ t('vue-table-builder::table.bulk_actions.select_all_results', {total: String(total)}) }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
