import { useEffect, useState } from 'react';
import { AlertTriangle } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { useAuth } from '../context/AuthContext';

interface GeneralAssembly {
  exercise_year: number;
  held_on: string | null;
}

/** Dispatched by ResidenceSettingsPage whenever an AG date is saved or cleared, so this banner updates instantly instead of waiting for the next page load. */
export const GENERAL_ASSEMBLIES_UPDATED_EVENT = 'atlasoft:general-assemblies-updated';

/** Mirrors Residence::fiscalYearStartsOn() on the backend. */
function fiscalYearStartsOn(year: number, month: number, day: number): Date {
  const daysInMonth = new Date(year, month, 0).getDate();
  return new Date(year, month - 1, Math.min(day, daysInMonth));
}

export function MissingAgDateBanner() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const [assemblies, setAssemblies] = useState<GeneralAssembly[] | null>(null);

  const canSee = user?.role === 'admin' || user?.role === 'tresorier';

  useEffect(() => {
    if (!canSee) {
      return;
    }

    function load() {
      api
        .get<{ data: GeneralAssembly[] }>('/api/general-assemblies')
        .then(({ data }) => setAssemblies(data.data))
        .catch(() => setAssemblies(null));
    }

    load();
    window.addEventListener(GENERAL_ASSEMBLIES_UPDATED_EVENT, load);
    return () => window.removeEventListener(GENERAL_ASSEMBLIES_UPDATED_EVENT, load);
  }, [canSee]);

  if (!canSee || !assemblies || !user?.residence) {
    return null;
  }

  const { fiscal_year_start_month: month, fiscal_year_start_day: day } = user.residence;
  const now = new Date();

  let currentExerciseYear = now.getFullYear();
  while (now < fiscalYearStartsOn(currentExerciseYear, month, day)) {
    currentExerciseYear -= 1;
  }
  while (now >= fiscalYearStartsOn(currentExerciseYear + 1, month, day)) {
    currentExerciseYear += 1;
  }

  const previousExerciseYear = currentExerciseYear - 1;
  const hasAgDate = assemblies.some((a) => a.exercise_year === previousExerciseYear && a.held_on);

  if (hasAgDate) {
    return null;
  }

  const exerciseEndDate = fiscalYearStartsOn(currentExerciseYear, month, day).toLocaleDateString('fr-FR');

  return (
    <div className="flex flex-wrap items-center justify-between gap-2.5 bg-rose-600 px-6 py-2.5 text-sm font-medium text-white">
      <div className="flex items-center gap-2.5">
        <AlertTriangle className="size-4 shrink-0" />
        {t('agReminder.message', { year: previousExerciseYear, date: exerciseEndDate })}
      </div>
      {user.role === 'admin' && (
        <Link to="/residence" className="shrink-0 rounded-lg border border-white/40 px-2.5 py-1 text-xs font-semibold hover:bg-white/10">
          {t('agReminder.action')}
        </Link>
      )}
    </div>
  );
}
