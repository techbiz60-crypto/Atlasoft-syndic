import { Building2 } from 'lucide-react';

export function Logo({ className = '' }: { className?: string }) {
  return (
    <div className={`flex items-center gap-2 ${className}`}>
      <span className="flex size-9 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm shadow-brand-900/20">
        <Building2 className="size-5" strokeWidth={2.25} />
      </span>
      <span className="text-lg font-bold tracking-tight text-slate-900">
        Atlasoft <span className="text-brand-600">Syndic</span>
      </span>
    </div>
  );
}
