import {Pencil, Trash2} from '@lucide/vue'
import {markRaw, type Component} from 'vue'

export type RowActionIcons = Record<string, Component>

const registeredIcons: RowActionIcons = {}

export function registerRowActionIcons(icons: RowActionIcons): void {
    for (const [name, icon] of Object.entries(icons)) {
        registeredIcons[name] = markRaw(icon)
    }
}

export function resolveRowActionIcon(name: string | null | undefined, icons?: RowActionIcons): Component | null {
    if (!name) {
        return null
    }

    return icons?.[name] ?? registeredIcons[name] ?? null
}

export const DEFAULT_ROW_ACTION_ICONS: RowActionIcons = {
    pencil: Pencil,
    'trash-2': Trash2,
}

export function registerDefaultRowActionIcons(): void {
    registerRowActionIcons(DEFAULT_ROW_ACTION_ICONS)
}
