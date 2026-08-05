<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useCustomerStore } from '../store'
import { useUserStore } from '../../../../stores/user.store'
import CustomerDropdown from '../components/CustomerDropdown.vue'
import CustomerViewSidebar from '@/scripts/features/company/customers/components/CustomerViewSidebar.vue'
import CustomerChart from '@/scripts/features/company/customers/components/CustomerChart.vue'
import CustomerStatement from '@/scripts/features/company/customers/components/CustomerStatement.vue'
import CustomerBalanceCard from '@/scripts/features/company/customers/components/CustomerBalanceCard.vue'
import type { Customer } from '@/scripts/types/domain/customer'

const ABILITIES = {
  EDIT_CUSTOMER: 'edit-customer',
  DELETE_CUSTOMER: 'delete-customer',
  CREATE_ESTIMATE: 'create-estimate',
  CREATE_INVOICE: 'create-invoice',
  CREATE_PAYMENT: 'create-payment',
  CREATE_EXPENSE: 'create-expense',
  VIEW_CUSTOMER: 'view-customer',
  VIEW_FINANCIAL_REPORTS: 'view-financial-reports',
} as const

const customerStore = useCustomerStore()
const userStore = useUserStore()

const router = useRouter()
const route = useRoute()

const pageTitle = computed<string>(() => {
  return customerStore.selectedViewCustomer.name ?? ''
})

const isLoading = computed<boolean>(() => customerStore.isFetchingViewData)

const customerCurrency = computed(() => customerStore.selectedViewCustomer.currency)
const selectedCustomer = computed<Customer | null>(() => (
  customerStore.selectedViewCustomer.id
    ? customerStore.selectedViewCustomer as Customer
    : null
))
const invoiceDueAmount = computed(() => customerStore.selectedViewCustomer.invoice_due_amount ?? customerStore.selectedViewCustomer.due_amount ?? 0)
const availableCredit = computed(() => customerStore.selectedViewCustomer.available_credit ?? 0)
const accountBalance = computed(() => customerStore.selectedViewCustomer.account_balance ?? (invoiceDueAmount.value - availableCredit.value))
const canViewStatement = computed(() => {
  return userStore.hasAbilities(ABILITIES.VIEW_CUSTOMER)
    && userStore.hasAbilities(ABILITIES.VIEW_FINANCIAL_REPORTS)
})

watch(
  () => route.params.id,
  (id) => {
    if (id) {
      void customerStore.fetchViewCustomer({ id: Number(id) })
    }
  },
  { immediate: true },
)

function canCreateTransaction(): boolean {
  return userStore.hasAbilities([
    ABILITIES.CREATE_ESTIMATE,
    ABILITIES.CREATE_INVOICE,
    ABILITIES.CREATE_PAYMENT,
    ABILITIES.CREATE_EXPENSE,
  ])
}

function hasAtleastOneAbility(): boolean {
  return userStore.hasAbilities([
    ABILITIES.DELETE_CUSTOMER,
    ABILITIES.EDIT_CUSTOMER,
  ])
}

function refreshData(): void {
  router.push('/admin/customers')
}
</script>

<template>
  <BasePage class="xl:pl-96">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <router-link
          v-if="userStore.hasAbilities(ABILITIES.EDIT_CUSTOMER)"
          :to="`/admin/customers/${route.params.id}/edit`"
        >
          <BaseButton
            class="mr-3"
            variant="primary-outline"
            :content-loading="isLoading"
          >
            {{ $t('general.edit') }}
          </BaseButton>
        </router-link>

        <BaseDropdown
          v-if="canCreateTransaction()"
          position="bottom-end"
          :content-loading="isLoading"
        >
          <template #activator>
            <BaseButton
              class="mr-3"
              variant="primary"
              :content-loading="isLoading"
            >
              {{ $t('customers.new_transaction') }}
            </BaseButton>
          </template>

          <router-link
            v-if="userStore.hasAbilities(ABILITIES.CREATE_ESTIMATE)"
            :to="`/admin/estimates/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="DocumentIcon" class="mr-3 text-body" />
              {{ $t('estimates.new_estimate') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(ABILITIES.CREATE_INVOICE)"
            :to="`/admin/invoices/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="DocumentTextIcon" class="mr-3 text-body" />
              {{ $t('invoices.new_invoice') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(ABILITIES.CREATE_PAYMENT)"
            :to="`/admin/payments/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="CreditCardIcon" class="mr-3 text-body" />
              {{ $t('payments.new_payment') }}
            </BaseDropdownItem>
          </router-link>

          <router-link
            v-if="userStore.hasAbilities(ABILITIES.CREATE_EXPENSE)"
            :to="`/admin/expenses/create?customer=${$route.params.id}`"
          >
            <BaseDropdownItem>
              <BaseIcon name="CalculatorIcon" class="mr-3 text-body" />
              {{ $t('expenses.new_expense') }}
            </BaseDropdownItem>
          </router-link>
        </BaseDropdown>

        <CustomerDropdown
          v-if="hasAtleastOneAbility()"
          :class="{
            'ml-3': isLoading,
          }"
          :row="customerStore.selectedViewCustomer"
          :load-data="refreshData"
        />
      </template>
    </BasePageHeader>

    <!-- Customer View Sidebar -->
    <CustomerViewSidebar />

    <BaseTabGroup>
      <BaseTab :title="$t('customers.overview')">
        <div class="grid gap-4 mt-6 md:grid-cols-3 xl:grid-cols-2 min-[1400px]:grid-cols-3">
          <CustomerBalanceCard :label="$t('customers.invoice_due')" :amount="invoiceDueAmount" :currency="customerCurrency" />
          <CustomerBalanceCard :label="$t('customers.available_credit')" :amount="availableCredit" :currency="customerCurrency" />
          <CustomerBalanceCard class="xl:col-span-2 min-[1400px]:col-span-1" :label="$t('customers.net_account_balance')" :amount="accountBalance" :currency="customerCurrency" :credit-label="$t('customers.credit')" />
        </div>
        <CustomerChart />
      </BaseTab>
      <BaseTab v-if="canViewStatement" :title="$t('customers.statement')">
        <CustomerStatement v-if="selectedCustomer" :customer="selectedCustomer" />
      </BaseTab>
    </BaseTabGroup>
  </BasePage>
</template>
