import logo from '../assets/atlasoft-syndic-logo.png';

export function Logo({ className = '' }: { className?: string }) {
  return <img src={logo} alt="Atlasoft Syndic" className={`h-16 w-auto ${className}`} />;
}

/**
 * The building pictogram alone, cropped out of the full logo (which also
 * carries the "Atlasoft Syndic" wordmark below it) — for tight spots like
 * the sidebar badge where there's only room for a square icon.
 */
export function LogoMark({ className = '' }: { className?: string }) {
  return (
    <span className={`block shrink-0 overflow-hidden rounded-xl bg-white ${className}`}>
      <img src={logo} alt="" className="h-[135%] w-full object-cover object-top" />
    </span>
  );
}
