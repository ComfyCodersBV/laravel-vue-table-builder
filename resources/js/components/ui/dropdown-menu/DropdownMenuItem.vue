<script setup lang="ts">
import { DropdownMenuItem, type DropdownMenuItemProps, useForwardProps } from 'reka-ui'
import { cn } from '../../../lib/utils'
import type { HTMLAttributes } from 'vue'

const props = defineProps<DropdownMenuItemProps & { class?: HTMLAttributes['class'] }>()
const forwarded = useForwardProps(props)
</script>

<template>
    <DropdownMenuItem
        v-bind="forwarded"
        :class="cn('relative flex cursor-default select-none items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50', props.class)"
    >
        <slot />
    </DropdownMenuItem>
</template>
