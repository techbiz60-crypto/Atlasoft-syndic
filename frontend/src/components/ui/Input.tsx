import type { InputHTMLAttributes, LabelHTMLAttributes, ReactNode, SelectHTMLAttributes } from 'react';

export function Field({
  label,
  children,
  htmlFor,
}: {
  label: string;
  children: ReactNode;
  htmlFor?: string;
} & LabelHTMLAttributes<HTMLLabelElement>) {
  return (
    <label htmlFor={htmlFor} className="flex flex-col gap-1.5">
      <span className="text-sm font-medium text-slate-700">{label}</span>
      {children}
    </label>
  );
}

const inputBaseClasses =
  'w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition-shadow focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15 disabled:bg-slate-50 disabled:text-slate-400';

export function Input(props: InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={`${inputBaseClasses} ${props.className ?? ''}`} />;
}

export function Select(props: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select {...props} className={`${inputBaseClasses} ${props.className ?? ''}`} />;
}
