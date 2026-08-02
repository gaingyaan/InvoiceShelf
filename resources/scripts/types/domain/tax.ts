import type { Currency } from './currency'
import type { Company } from './company'

export enum TaxTypeCategory {
  GENERAL = 'GENERAL',
  MODULE = 'MODULE',
}

export type TaxTypeTransactionType = 'sales' | 'purchases'

export interface TaxType {
  id: number
  name: string
  percent: number
  fixed_amount: number
  calculation_type: string | null
  transaction_type: TaxTypeTransactionType
  type: TaxTypeCategory
  compound_tax: boolean
  collective_tax: number | null
  description: string | null
  company_id: number
  company?: Company
}

export interface Tax {
  id: number
  tax_type_id: number
  expense_id: number | null
  invoice_id: number | null
  estimate_id: number | null
  invoice_item_id: number | null
  estimate_item_id: number | null
  item_id: number | null
  company_id: number
  name: string
  amount: number
  percent: number
  calculation_type: string | null
  fixed_amount: number
  compound_tax: boolean
  base_amount: number
  currency_id: number | null
  type: TaxTypeCategory
  recurring_invoice_id: number | null
  tax_type?: TaxType
  currency?: Currency
}

/**
 * A tax attached to an expense. Expense taxes are entered as individual input
 * tax amounts, while the remaining fields are the tax-type snapshot returned
 * by the API and used to describe the row in the form.
 */
export interface ExpenseTax {
  id?: number
  tax_type_id: number
  amount: number
  name: string
  percent: number | null
  calculation_type: string | null
  fixed_amount: number | null
  compound_tax: boolean
  base_amount?: number
  currency_id?: number | null
  type?: TaxTypeCategory
  tax_type?: TaxType
}
