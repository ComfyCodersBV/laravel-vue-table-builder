<script setup lang="ts">
import {ChevronDown, ChevronLeft, ChevronRight} from 'lucide-vue-next'
import {Button} from './ui/button'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from './ui/dropdown-menu'
import type {PaginationData} from '../types/table-builder'
import {useTranslations} from '../composables/useTranslations'

const {t} = useTranslations('vue_table_builder_table_translations')

withDefaults(defineProps<{
    pagination: PaginationData
    perPageOptions?: number[]
    compact?: boolean
}>(), {
    compact: false,
})

const emit = defineEmits<{
    'update:perPage': [string]
    navigate: [-1 | 1]
}>()
</script>

<template>
    <div :class="compact ? 'flex items-center gap-3' : 'flex items-center justify-between gap-3'">
        <div class="flex items-center gap-3">
            <div v-if="perPageOptions && perPageOptions.length > 1" class="flex items-center gap-1.5">
                <span class="text-sm text-muted-foreground">
                    {{ t('vue-table-builder::table.pagination.per_page') }}
                </span>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="cursor-pointer">
                            {{ pagination.per_page }}
                            <ChevronDown class="ml-1 h-3.5 w-3.5"/>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem
                            v-for="option in perPageOptions"
                            :key="option"
                            class="cursor-pointer"
                            @click="emit('update:perPage', String(option))"
                        >
                            {{ option }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <span class="text-sm text-muted-foreground">
                {{
                    t('vue-table-builder::table.pagination.showing', {
                        from: pagination.from ?? 0,
                        to: pagination.to ?? 0,
                        total: pagination.total,
                    })
                }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-3 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-background enabled:cursor-pointer"
                :disabled="pagination.current_page <= 1"
                @click="emit('navigate', -1)"
            >
                <ChevronLeft class="h-4 w-4"/>
                {{ t('vue-table-builder::table.pagination.previous') }}
            </button>

            <div class="text-sm">
                {{
                    t('vue-table-builder::table.pagination.page_of', {
                        current: pagination.current_page,
                        last: pagination.last_page,
                    })
                }}
            </div>

            <button
                type="button"
                class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-3 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-background enabled:cursor-pointer"
                :disabled="pagination.current_page >= pagination.last_page"
                @click="emit('navigate', 1)"
            >
                {{ t('vue-table-builder::table.pagination.next') }}
                <ChevronRight class="h-4 w-4"/>
            </button>
        </div>
    </div>
</template>
