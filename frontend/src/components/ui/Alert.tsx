import { AlertCircle, CheckCircle2 } from 'lucide-react';

export function ErrorAlert({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex items-start gap-2.5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
      <AlertCircle className="mt-0.5 size-4 shrink-0" />
      <span>{children}</span>
    </div>
  );
}

export function SuccessAlert({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex items-start gap-2.5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
      <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
      <span>{children}</span>
    </div>
  );
}
