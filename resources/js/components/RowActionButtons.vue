<script setup lang="ts">
import {LoaderCircle} from '@lucide/vue'
import {Button} from './ui/button'
import {resolveRowActionIcon, type RowActionIcons} from '../lib/row-action-icons'
import type {RowAction} from '../types/table-builder'

const props = withDefaults(defineProps<{
    actions: RowAction[]
    icons?: RowActionIcons
    running?: boolean
    busy?: RowAction | null
    iconOnly?: boolean
}>(), {
    running: false,
    busy: null,
    iconOnly: false,
})

const emit = defineEmits<{
    run: [action: RowAction, event: MouseEvent]
}>()

function iconOf(action: RowAction) {
    return resolveRowActionIcon(action.icon, props.icons)
}

function showsIconOnly(action: RowAction): boolean {
    return (props.iconOnly || action.iconOnly === true) && iconOf(action) !== null
}

function sizeOf(action: RowAction): 'sm' | 'icon-sm' {
    return showsIconOnly(action) ? 'icon-sm' : 'sm'
}

function labelOf(action: RowAction): string | undefined {
    return showsIconOnly(action) ? action.label : undefined
}

function disabledTitle(action: RowAction): string | undefined {
    if (!showsIconOnly(action)) {
        return action.disabledReason ?? undefined
    }

    return action.disabledReason ? `${action.label}: ${action.disabledReason}` : action.label
}

function isBusy(action: RowAction): boolean {
    return props.busy === action
}

function downloadName(action: RowAction): string {
    return typeof action.download === 'string' ? action.download : ''
}

function run(action: RowAction, event: MouseEvent): void {
    emit('run', action, event)
}
</script>

<template>
    <template v-for="action in actions" :key="action.key">
        <span v-if="action.disabled" :title="disabledTitle(action)" class="inline-flex">
            <Button :size="sizeOf(action)" :variant="action.variant" disabled :aria-label="showsIconOnly(action) ? disabledTitle(action) : undefined" :data-row-action="action.key" :data-icon-only="showsIconOnly(action) ? '' : undefined">
                <component :is="iconOf(action)" v-if="iconOf(action)" aria-hidden="true"/>
                <template v-if="!showsIconOnly(action)">{{ action.label }}</template>
            </Button>
        </span>
        <Button v-else-if="action.download || action.method === 'get'" :size="sizeOf(action)" :variant="action.variant" as-child>
            <a
                :href="action.url"
                :download="action.download ? downloadName(action) : undefined"
                :data-row-action="action.key"
                :data-row-action-download="action.download ? '' : undefined"
                :data-icon-only="showsIconOnly(action) ? '' : undefined"
                :aria-label="labelOf(action)"
                :title="labelOf(action)"
                :aria-disabled="running ? 'true' : undefined"
                :class="{'pointer-events-none opacity-50': running}"
                @click="run(action, $event)"
            >
                <component :is="iconOf(action)" v-if="iconOf(action)" aria-hidden="true"/>
                <template v-if="!showsIconOnly(action)">{{ action.label }}</template>
            </a>
        </Button>
        <Button
            v-else
            :size="sizeOf(action)"
            :variant="action.variant"
            :disabled="running"
            :aria-busy="isBusy(action) ? 'true' : undefined"
            :aria-label="labelOf(action)"
            :title="labelOf(action)"
            :data-row-action="action.key"
            :data-icon-only="showsIconOnly(action) ? '' : undefined"
            @click="run(action, $event)"
        >
            <LoaderCircle v-if="isBusy(action)" class="animate-spin" aria-hidden="true" data-row-action-spinner/>
            <component :is="iconOf(action)" v-else-if="iconOf(action)" aria-hidden="true"/>
            <template v-if="!showsIconOnly(action)">{{ action.label }}</template>
        </Button>
    </template>
</template>
