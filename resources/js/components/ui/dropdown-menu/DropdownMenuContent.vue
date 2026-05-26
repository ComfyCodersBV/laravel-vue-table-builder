<script setup lang="ts">
import { DropdownMenuContent, type DropdownMenuContentProps, useForwardProps } from 'reka-ui'
import { cn } from '../../../lib/utils'
import type { HTMLAttributes } from 'vue'

const props = defineProps<DropdownMenuContentProps & { class?: HTMLAttributes['class'] }>()
const forwarded = useForwardProps(props)
</script>

<template>
    <DropdownMenuContent
        v-bind="forwarded"
        :class="cn('z-50 min-w-32 overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md', props.class)"
    >
        <slot />
    </DropdownMenuContent>
</template>
