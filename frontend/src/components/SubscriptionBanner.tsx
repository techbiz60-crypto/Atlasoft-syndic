import { useEffect, useState } from 'react';
import { AlertTriangle, Clock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import type { Subscription } from '../types/auth';

export function SubscriptionBanner() {
  const { t } = useTranslation();
  const [subscription, setSubscription] = useState<Subscription | null>(null);

  useEffect(() => {
    api
      .get<{ data: Subscription | null }>('/api/subscription')
      .then(({ data }) => setSubscription(data.data))
      .catch(() => setSubscription(null));
  }, []);

  if (!subscription || subscription.status === 'free' || subscription.status === 'active') {
    return null;
  }

  if (subscription.status === 'expired') {
    return (
      <div className="flex items-center gap-2.5 bg-rose-600 px-6 py-2.5 text-sm font-medium text-white">
        <AlertTriangle className="size-4 shrink-0" />
        {t('subscription.expiredBanner')}
      </div>
    );
  }

  if (subscription.days_remaining !== null && subscription.days_remaining <= 5) {
    return (
      <div className="flex items-center gap-2.5 bg-amber-500 px-6 py-2.5 text-sm font-medium text-white">
        <Clock className="size-4 shrink-0" />
        {t('subscription.trialEndingBanner', { count: subscription.days_remaining })}
      </div>
    );
  }

  return null;
}
