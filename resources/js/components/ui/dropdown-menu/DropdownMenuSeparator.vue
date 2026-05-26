<script setup lang="ts">
import { DropdownMenuSeparator, type DropdownMenuSeparatorProps, useForwardProps } from 'reka-ui'
import { cn } from '../../../lib/utils'
import type { HTMLAttributes } from 'vue'

const props = defineProps<DropdownMenuSeparatorProps & { class?: HTMLAttributes['class'] }>()
const forwarded = useForwardProps(props)
</script>

<template>
    <DropdownMenuSeparator
        v-bind="forwarded"
        :class="cn('-mx-1 my-1 h-px bg-muted', props.class)"
    />
</template>
