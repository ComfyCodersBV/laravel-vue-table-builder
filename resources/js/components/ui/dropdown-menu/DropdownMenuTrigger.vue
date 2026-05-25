<script setup lang="ts">
import { DropdownMenuTrigger, type DropdownMenuTriggerProps } from 'reka-ui'

defineProps<DropdownMenuTriggerProps>()
</script>

<template>
    <DropdownMenuTrigger v-bind="$props">
        <slot />
    </DropdownMenuTrigger>
</template>
