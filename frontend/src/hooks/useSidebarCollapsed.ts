import { useEffect, useState } from 'react';

const STORAGE_KEY = 'atlasoft-sidebar-collapsed';

export function useSidebarCollapsed() {
  const [collapsed, setCollapsed] = useState(() => {
    try {
      return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
      return false;
    }
  });

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
    } catch {
      // Ignore storage errors (private browsing, disabled storage, etc.) — collapse still works for the session.
    }
  }, [collapsed]);

  return [collapsed, setCollapsed] as const;
}
