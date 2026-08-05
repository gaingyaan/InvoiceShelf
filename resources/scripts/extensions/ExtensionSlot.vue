<script setup lang="ts">
import { computed } from 'vue'
import { extensionRegistry, extensionItems } from './runtime'
import type { RichEditorContext } from './types'

const props = defineProps<{
  name: 'header-actions' | 'company-layout-overlays' | 'rich-editor-toolbar-actions'
  context?: RichEditorContext
}>()

const contributions = computed(() => {
  const items = {
    'header-actions': extensionRegistry.headerActions.value,
    'company-layout-overlays': extensionRegistry.companyLayoutOverlays.value,
    'rich-editor-toolbar-actions': extensionRegistry.richEditorToolbarActions.value,
  }[props.name]

  return extensionItems(items)
})

function componentProps(props_: Record<string, unknown> | undefined): Record<string, unknown> {
  return props.context === undefined
    ? (props_ ?? {})
    : { ...props_, context: props.context }
}
</script>

<template>
  <component
    :is="contribution.component"
    v-for="contribution in contributions"
    :key="contribution.id"
    v-bind="componentProps(contribution.props)"
  />
</template>
