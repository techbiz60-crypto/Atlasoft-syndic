export interface Building {
  id: number;
  residence_id: number;
  name: string;
  lots_count?: number;
}

export interface LotTypeRate {
  id: number;
  lot_type_id: number;
  amount: number;
  effective_date: string;
}

export interface LotType {
  id: number;
  residence_id: number;
  name: string;
  current_amount: number | null;
  rates: LotTypeRate[];
}

export interface OpeningBalance {
  id: number;
  amount: number;
  period: string;
  paid_amount: number;
  status: FundCallStatus;
}

export interface LotOwnerHistoryEntry {
  id: number;
  owner_name: string;
  owner_phone: string | null;
  owner_email: string | null;
  started_at: string;
}

export type LotReferenceType = 'elevator_chip' | 'garage_number';

export interface LotReferenceEntry {
  id: number;
  type: LotReferenceType;
  value: string;
}

export interface ResidentUser {
  id: number;
  name: string;
  email: string;
}

export interface Lot {
  id: number;
  residence_id: number;
  building_id: number;
  lot_type_id: number;
  number: string;
  floor: string | null;
  owner_name: string;
  owner_phone: string | null;
  owner_email: string | null;
  lot_type: LotType;
  building: Building;
  opening_balance: OpeningBalance | null;
  resident_user: ResidentUser | null;
}

export type PaymentMethod = 'virement' | 'especes' | 'cheque';
export type FundCallStatus = 'unpaid' | 'partial' | 'paid';

export interface Payment {
  id: number;
  fund_call_id: number;
  amount: number;
  paid_at: string;
  method: PaymentMethod;
  notes: string | null;
}

export interface FundCall {
  id: number;
  lot_id: number;
  amount: number;
  period: string;
  is_opening_balance: boolean;
  paid_amount: number;
  status: FundCallStatus;
  lot: Lot;
  payments: Payment[];
}

/**
 * One row from GET /api/payments — payments created together in a single
 * bulk action share a `batch_id` and are pre-grouped by the backend into one
 * row with a combined `amount` and the list of `periods` it covers, so a
 * copropriétaire paying 6 months at once shows as one transaction, not 6.
 * `batch_id` is null for a standalone single-month payment.
 */
export interface PaymentWithContext {
  id: number;
  fund_call_id: number;
  batch_id: string | null;
  amount: number;
  paid_at: string;
  method: PaymentMethod;
  notes: string | null;
  lot: {
    id: number;
    number: string;
    owner_name: string;
    building: { id: number; name: string };
  };
  periods: string[];
  is_opening_balance: boolean;
}

export interface MonthCell {
  month: number;
  fund_call_id: number | null;
  amount: number | null;
  paid_amount: number;
  status: FundCallStatus | 'none';
}

export interface MatrixOpeningBalance {
  fund_call_id: number;
  amount: number;
  paid_amount: number;
  status: FundCallStatus;
}

export interface MatrixRow {
  lot_id: number;
  lot_number: string;
  building_name: string;
  owner_name: string;
  months: MonthCell[];
  opening_balance: MatrixOpeningBalance | null;
}

export interface ExpenseCategory {
  id: number;
  residence_id: number;
  name: string;
  sort_order: number;
  expenses_count?: number;
}

export interface Expense {
  id: number;
  expense_category_id: number;
  category: ExpenseCategory;
  method: PaymentMethod;
  paid_at: string;
  label: string | null;
  amount: number;
  has_receipt: boolean;
}

export interface RevenueCategory {
  id: number;
  residence_id: number;
  name: string;
  revenues_count?: number;
}

export interface Revenue {
  id: number;
  revenue_category_id: number;
  category: RevenueCategory;
  method: PaymentMethod;
  received_at: string;
  label: string | null;
  amount: number;
  has_receipt: boolean;
}

export interface TreasuryCategoryLine {
  name: string;
  amounts: number[];
}

export interface TreasuryReport {
  year: number;
  opening_balance: number;
  cotisations: number[];
  revenue_categories: TreasuryCategoryLine[];
  expense_categories: TreasuryCategoryLine[];
  income_by_month: number[];
  expenses_by_month: number[];
  net_by_month: number[];
  balance_by_month: number[];
  closing_balance: number;
}

export interface LedgerMovement {
  id: string;
  date: string;
  direction: 'in' | 'out';
  kind: 'cotisation' | 'opening_balance' | 'revenue' | 'expense';
  label: string;
  reference: string;
  method: PaymentMethod;
  amount: number;
  /** Number of months a single payment covered; absent for revenues and expenses. */
  months_covered?: number;
  /** Balance after this movement, in chronological order. */
  balance: number;
}

export interface Ledger {
  year: number;
  opening_balance: number;
  closing_balance: number;
  total_in: number;
  total_out: number;
  data: LedgerMovement[];
}

export interface AgReport {
  year: number;
  residence_name: string;
  /** Grouped by the month each payment covers, not the month it was received. */
  cotisations: number[];
  opening_balance_recovered: number[];
  revenue_categories: TreasuryCategoryLine[];
  expense_categories: TreasuryCategoryLine[];
  income_by_month: number[];
  expenses_by_month: number[];
  net_by_month: number[];
  total_income: number;
  total_expenses: number;
  result: number;
}

export interface PaymentsReportRow {
  lot_id: number;
  floor: string | null;
  lot_number: string;
  owner_name: string;
  opening_balance_remaining: number | null;
  opening_balance_paid_this_year: number | null;
  months: (number | null)[];
  total: number;
}

export interface PaymentsReport {
  year: number;
  building_name: string | null;
  rows: PaymentsReportRow[];
}

export interface UnpaidLot {
  lot_id: number;
  lot_number: string;
  building_name: string;
  owner_name: string;
  owner_phone: string | null;
  total_due: number;
  months_late: number;
  oldest_unpaid_period: string | null;
  last_payment_date: string | null;
  opening_balance_due: number;
}
