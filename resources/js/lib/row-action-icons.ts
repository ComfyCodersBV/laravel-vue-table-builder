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
