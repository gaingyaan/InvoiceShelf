import { markRaw, shallowRef } from 'vue'
import type { ShallowRef } from 'vue'
import type { Router } from 'vue-router'
import { client } from '@/scripts/api/client'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { registerAdditionalMessages } from '@/scripts/plugins/i18n'
import type {
  BootstrapCompletedEvent,
  CompanyChangeEvent,
  ComponentExtensionContribution,
  InvoiceShelfExtensionApi,
  InvoiceShelfExtensionEvents,
  SettingsNavigationContribution,
  SettingsPageContribution,
} from './types'

type ComponentSlot =
  | 'headerActions'
  | 'companyLayoutOverlays'
  | 'richEditorToolbarActions'

interface RegisteredComponentContribution extends ComponentExtensionContribution {
  component: ComponentExtensionContribution['component']
}

function comparePriority<T extends { priority?: number; id: string }>(a: T, b: T): number {
  return (a.priority ?? 100) - (b.priority ?? 100) || a.id.localeCompare(b.id)
}

function assertContributionId(id: string): void {
  if (!id.trim()) {
    throw new Error('InvoiceShelf extension contributions require a stable id.')
  }
}

/**
 * Host-owned reactive registry. Modules only receive the public API below,
 * never the host's Pinia stores or layout implementation.
 */
export class ExtensionRegistry {
  readonly headerActions = shallowRef<RegisteredComponentContribution[]>([])
  readonly companyLayoutOverlays = shallowRef<RegisteredComponentContribution[]>([])
  readonly richEditorToolbarActions = shallowRef<RegisteredComponentContribution[]>([])
  readonly companySettingsNavigation = shallowRef<SettingsNavigationContribution[]>([])
  readonly adminSettingsNavigation = shallowRef<SettingsNavigationContribution[]>([])

  private readonly teardowns = new Set<() => void>()

  registerComponent(
    slot: ComponentSlot,
    contribution: ComponentExtensionContribution,
  ): () => void {
    assertContributionId(contribution.id)
    const target = this[slot] as ShallowRef<RegisteredComponentContribution[]>
    const entry: RegisteredComponentContribution = {
      ...contribution,
      component: markRaw(contribution.component),
    }

    return this.track(() => {
      target.value = [...target.value.filter((item) => item.id !== entry.id), entry]
        .sort(comparePriority)

      return () => {
        target.value = target.value.filter((item) => item !== entry)
      }
    })
  }

  registerNavigation(
    slot: 'companySettingsNavigation' | 'adminSettingsNavigation',
    contribution: SettingsNavigationContribution,
  ): () => void {
    assertContributionId(contribution.id)
    const target = this[slot] as ShallowRef<SettingsNavigationContribution[]>
    const entry = { ...contribution }

    return this.track(() => {
      target.value = [...target.value.filter((item) => item.id !== entry.id), entry]
        .sort(comparePriority)

      return () => {
        target.value = target.value.filter((item) => item !== entry)
      }
    })
  }

  reset(): void {
    for (const teardown of [...this.teardowns]) {
      teardown()
    }
  }

  trackTeardown(unregister: () => void): () => void {
    return this.track(() => unregister)
  }

  private track(register: () => () => void): () => void {
    const unregister = register()
    let active = true
    const teardown = () => {
      if (!active) return
      active = false
      unregister()
      this.teardowns.delete(teardown)
    }

    this.teardowns.add(teardown)
    return teardown
  }
}

export const extensionRegistry = new ExtensionRegistry()

class ExtensionApi implements InvoiceShelfExtensionApi {
  private readonly listeners = new Map<
    keyof InvoiceShelfExtensionEvents,
    Set<(payload: unknown) => void>
  >()
  private readonly settingsPageTeardowns = new Map<string, () => void>()

  constructor(readonly router: Router) {}

  readonly client = client

  registerHeaderAction(contribution: ComponentExtensionContribution): () => void {
    return extensionRegistry.registerComponent('headerActions', contribution)
  }

  registerCompanyLayoutOverlay(contribution: ComponentExtensionContribution): () => void {
    return extensionRegistry.registerComponent('companyLayoutOverlays', contribution)
  }

  registerRichEditorToolbarAction(contribution: ComponentExtensionContribution): () => void {
    return extensionRegistry.registerComponent('richEditorToolbarActions', contribution)
  }

  registerCompanySettingsNavigation(contribution: SettingsNavigationContribution): () => void {
    return extensionRegistry.registerNavigation('companySettingsNavigation', contribution)
  }

  registerAdminSettingsNavigation(contribution: SettingsNavigationContribution): () => void {
    return extensionRegistry.registerNavigation('adminSettingsNavigation', contribution)
  }

  registerCompanySettingsPage(contribution: SettingsPageContribution): () => void {
    return this.registerSettingsPage('settings', 'companySettingsNavigation', contribution)
  }

  registerAdminSettingsPage(contribution: SettingsPageContribution): () => void {
    return this.registerSettingsPage('admin.settings', 'adminSettingsNavigation', contribution)
  }

  addMessages(messages: Record<string, Record<string, unknown>>): void {
    registerAdditionalMessages(messages)
  }

  notify(type: 'success' | 'error' | 'warning' | 'info', message: string): void {
    useNotificationStore().showNotification({ type, message })
  }

  on<EventName extends keyof InvoiceShelfExtensionEvents>(
    event: EventName,
    listener: (payload: InvoiceShelfExtensionEvents[EventName]) => void,
  ): () => void {
    const listeners = this.listeners.get(event) ?? new Set<(payload: unknown) => void>()
    this.listeners.set(event, listeners)
    listeners.add(listener as (payload: unknown) => void)
    return () => listeners.delete(listener as (payload: unknown) => void)
  }

  emit<EventName extends keyof InvoiceShelfExtensionEvents>(
    event: EventName,
    payload: InvoiceShelfExtensionEvents[EventName],
  ): void {
    for (const listener of this.listeners.get(event) ?? []) {
      listener(payload)
    }
  }

  reset(): void {
    extensionRegistry.reset()
    this.settingsPageTeardowns.clear()
    for (const listeners of this.listeners.values()) {
      listeners.clear()
    }
  }

  private registerSettingsPage(
    parentName: string,
    navigationSlot: 'companySettingsNavigation' | 'adminSettingsNavigation',
    contribution: SettingsPageContribution,
  ): () => void {
    assertContributionId(contribution.id)
    if (!contribution.path || contribution.path.startsWith('/')) {
      throw new Error('InvoiceShelf extension settings paths must be relative.')
    }

    const routeName = `extension.${parentName}.${contribution.id}`
    const pageKey = `${parentName}:${contribution.id}`
    this.settingsPageTeardowns.get(pageKey)?.()

    const removeRoute = this.router.addRoute(parentName, {
      path: contribution.path,
      name: routeName,
      component: markRaw(contribution.component),
      meta: contribution.meta,
    })
    const removeNavigation = extensionRegistry.registerNavigation(navigationSlot, {
      id: contribution.id,
      priority: contribution.priority,
      visible: contribution.visible,
      title: contribution.title,
      icon: contribution.icon,
      to: { name: routeName },
    })

    let active = true
    const teardown = () => {
      if (!active) return
      active = false
      removeNavigation()
      removeRoute()
      this.settingsPageTeardowns.delete(pageKey)
    }

    const trackedTeardown = extensionRegistry.trackTeardown(teardown)
    this.settingsPageTeardowns.set(pageKey, trackedTeardown)
    return trackedTeardown
  }
}

let extensionApi: ExtensionApi | null = null

export function createExtensionApi(router: Router): InvoiceShelfExtensionApi {
  extensionApi ??= new ExtensionApi(router)
  return extensionApi
}

export function emitBootstrapCompleted(payload: BootstrapCompletedEvent): void {
  extensionApi?.emit('bootstrap:completed', payload)
}

export function emitCompanyChanging(payload: CompanyChangeEvent): void {
  extensionApi?.emit('company:changing', payload)
}

export function emitCompanyChanged(payload: CompanyChangeEvent): void {
  extensionApi?.emit('company:changed', payload)
}

export function isContributionVisible(contribution: { visible?: () => boolean }): boolean {
  try {
    return contribution.visible?.() ?? true
  } catch (error) {
    console.warn('InvoiceShelf extension visibility predicate failed.', error)
    return false
  }
}

export function extensionItems<T extends { visible?: () => boolean }>(
  items: readonly T[],
): T[] {
  return items.filter(isContributionVisible)
}
