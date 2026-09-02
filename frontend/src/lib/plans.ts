import type { SubscriptionPlan } from '../types/auth';

/**
 * Mirrors the pricing catalog in the backend's App\SubscriptionPlan enum.
 * Kept as a small static table here (rather than fetched from an endpoint)
 * since it changes rarely — same approach already used for month names and
 * payment method labels elsewhere in this app.
 */
export interface PlanCatalogEntry {
  plan: SubscriptionPlan;
  label: string;
  maxLots: number | null;
  monthlyPrice: number | null;
  annualPrice: number | null;
}

export const upgradeablePlans: PlanCatalogEntry[] = [
  { plan: 'starter', label: 'Starter', maxLots: 15, monthlyPrice: 50, annualPrice: 480 },
  { plan: 'standard', label: 'Standard', maxLots: 40, monthlyPrice: 100, annualPrice: 960 },
  { plan: 'plus', label: 'Plus', maxLots: 70, monthlyPrice: 160, annualPrice: 1536 },
  { plan: 'premium', label: 'Premium', maxLots: 100, monthlyPrice: 220, annualPrice: 2112 },
  { plan: 'custom', label: 'Sur devis', maxLots: null, monthlyPrice: null, annualPrice: null },
];
