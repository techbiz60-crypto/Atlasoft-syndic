import type { ButtonHTMLAttributes } from 'react';
import { Loader2 } from 'lucide-react';

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost';
type Size = 'sm' | 'md';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  isLoading?: boolean;
}

const variantClasses: Record<Variant, string> = {
  primary:
    'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-600 shadow-sm shadow-brand-900/10 disabled:bg-brand-300',
  secondary:
    'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 focus-visible:outline-slate-400 disabled:text-slate-400',
  danger:
    'bg-white text-rose-600 border border-rose-200 hover:bg-rose-50 focus-visible:outline-rose-500 disabled:text-rose-300',
  ghost: 'text-slate-600 hover:bg-slate-100 focus-visible:outline-slate-400',
};

const sizeClasses: Record<Size, string> = {
  sm: 'px-3 py-1.5 text-sm gap-1.5',
  md: 'px-4 py-2.5 text-sm gap-2',
};

export function Button({
  variant = 'primary',
  size = 'md',
  isLoading = false,
  disabled,
  className = '',
  children,
  ...props
}: ButtonProps) {
  return (
    <button
      disabled={disabled || isLoading}
      className={`inline-flex items-center justify-center rounded-lg font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed ${variantClasses[variant]} ${sizeClasses[size]} ${className}`}
      {...props}
    >
      {isLoading && <Loader2 className="size-4 animate-spin" />}
      {children}
    </button>
  );
}
