import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { AxiosError } from 'axios';
import { api, ensureCsrfCookie } from '../lib/api';
import type { User } from '../types/auth';

interface RegisterPayload {
  residence_name: string;
  lots_count: number;
  name: string;
  email: string;
  whatsapp_number: string;
  password: string;
  password_confirmation: string;
}

interface LoginPayload {
  email: string;
  password: string;
}

interface AuthContextValue {
  user: User | null;
  isLoading: boolean;
  register: (payload: RegisterPayload) => Promise<void>;
  login: (payload: LoginPayload) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const fetchUser = useCallback(async () => {
    try {
      const { data } = await api.get<{ user: User }>('/api/user');
      setUser(data.user);
    } catch {
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchUser();
  }, [fetchUser]);

  const register = useCallback(async (payload: RegisterPayload) => {
    await ensureCsrfCookie();
    const { data } = await api.post<{ user: User }>('/api/register', payload);
    setUser(data.user);
  }, []);

  const login = useCallback(async (payload: LoginPayload) => {
    await ensureCsrfCookie();
    const { data } = await api.post<{ user: User }>('/api/login', payload);
    setUser(data.user);
  }, []);

  const logout = useCallback(async () => {
    await api.post('/api/logout');
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider value={{ user, isLoading, register, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }

  return context;
}

/**
 * Admin and trésorier both get write access to the financial module
 * (cotisations, paiements, dépenses, recettes) by default — the backend is
 * the actual source of truth (a résidence can revoke trésorier's grant via
 * Rôles et permissions), this only controls whether the UI shows the
 * edit affordances at all.
 */
export function canManageFinances(user: User | null): boolean {
  return user?.role === 'admin' || user?.role === 'tresorier';
}

export function extractErrorMessage(error: unknown): string {
  if (error instanceof AxiosError) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;

    if (data?.errors) {
      return Object.values(data.errors).flat().join(' ');
    }

    if (data?.message) {
      return data.message;
    }
  }

  return "Une erreur inattendue s'est produite.";
}
