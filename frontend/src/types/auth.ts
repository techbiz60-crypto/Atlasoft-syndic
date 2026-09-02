export type Role = 'admin' | 'tresorier' | 'conseil' | 'coproprietaire';

export type SubscriptionPlan = 'free' | 'starter' | 'standard' | 'plus' | 'premium' | 'custom';
export type SubscriptionStatus = 'free' | 'trial' | 'active' | 'expired';

export interface Subscription {
  id: number;
  residence_id: number;
  plan: SubscriptionPlan;
  plan_label: string;
  billing_cycle: 'monthly' | 'annual' | null;
  trial_ends_at: string | null;
  current_period_end: string | null;
  status: SubscriptionStatus;
  is_writable: boolean;
  days_remaining: number | null;
  monthly_price: number | null;
  annual_price: number | null;
}

export interface SubscriptionInvoice {
  id: number;
  subscription_id: number;
  plan: SubscriptionPlan;
  plan_label: string;
  billing_cycle: 'monthly' | 'annual';
  amount: number;
  period_start: string;
  period_end: string;
  validated_at: string;
}

export interface Residence {
  id: number;
  name: string;
  address: string | null;
  lots_count: number;
  bank_rib: string | null;
  opening_balance: number;
}

export interface User {
  id: number;
  residence_id: number | null;
  role: Role;
  name: string;
  email: string;
  email_verified_at: string | null;
  whatsapp_number: string | null;
  is_platform_admin: boolean;
  /** Null only for a platform admin account — every tenant user always has one. */
  residence: Residence | null;
}

export interface PlatformResidence {
  residence_id: number;
  residence_name: string;
  lots_count: number;
  admin_name: string | null;
  admin_email: string | null;
  admin_whatsapp: string | null;
  subscription: Subscription | null;
}
