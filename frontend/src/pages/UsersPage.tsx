import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Copy, Plus, Trash2, UserRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import type { Role } from '../types/auth';
import { PageHeader } from '../components/PageHeader';
import { Field, Input, Select } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert, SuccessAlert } from '../components/ui/Alert';

interface ManagedUser {
  id: number;
  name: string;
  email: string;
  role: Role;
  whatsapp_number: string | null;
}

const CREATABLE_ROLES: Role[] = ['tresorier', 'conseil'];

export function UsersPage() {
  const { t } = useTranslation();

  const [users, setUsers] = useState<ManagedUser[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [form, setForm] = useState({ name: '', email: '', role: 'tresorier' as Role });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [generatedPassword, setGeneratedPassword] = useState<{ email: string; password: string } | null>(null);

  async function loadUsers() {
    setIsLoading(true);
    try {
      const { data } = await api.get<{ data: ManagedUser[] }>('/api/users');
      setUsers(data.data);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadUsers();
  }, []);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setGeneratedPassword(null);
    setIsSubmitting(true);

    try {
      const { data } = await api.post<{ data: ManagedUser; generated_password: string }>('/api/users', form);
      setGeneratedPassword({ email: data.data.email, password: data.generated_password });
      setForm({ name: '', email: '', role: 'tresorier' });
      await loadUsers();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm(t('users.confirmDelete'))) {
      return;
    }

    setError(null);

    try {
      await api.delete(`/api/users/${id}`);
      await loadUsers();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  function copyPassword() {
    if (generatedPassword) {
      void navigator.clipboard.writeText(generatedPassword.password);
    }
  }

  return (
    <div>
      <PageHeader title={t('users.title')} subtitle={t('users.subtitle')} />

      <div className="flex flex-col gap-6 lg:flex-row">
        <form
          onSubmit={handleSubmit}
          className="flex h-fit w-full flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:w-80 lg:shrink-0"
        >
          <p className="text-sm font-semibold text-slate-900">{t('users.addTitle')}</p>

          <Field label={t('users.nameLabel')} htmlFor="user-name">
            <Input
              id="user-name"
              value={form.name}
              onChange={(event) => setForm((previous) => ({ ...previous, name: event.target.value }))}
              required
            />
          </Field>

          <Field label={t('users.emailLabel')} htmlFor="user-email">
            <Input
              id="user-email"
              type="email"
              value={form.email}
              onChange={(event) => setForm((previous) => ({ ...previous, email: event.target.value }))}
              required
            />
          </Field>

          <Field label={t('users.roleLabel')} htmlFor="user-role">
            <Select
              id="user-role"
              value={form.role}
              onChange={(event) => setForm((previous) => ({ ...previous, role: event.target.value as Role }))}
            >
              {CREATABLE_ROLES.map((role) => (
                <option key={role} value={role}>
                  {t(`users.roles.${role}`)}
                </option>
              ))}
            </Select>
          </Field>

          <Button type="submit" isLoading={isSubmitting} className="self-start">
            <Plus className="size-4" />
            {t('users.addButton')}
          </Button>
        </form>

        <div className="flex-1">
          {error && (
            <div className="mb-4">
              <ErrorAlert>{error}</ErrorAlert>
            </div>
          )}

          {generatedPassword && (
            <div className="mb-4">
              <SuccessAlert>
                <div className="flex flex-wrap items-center gap-2">
                  <span>
                    {t('users.generatedPasswordLabel', { email: generatedPassword.email })}{' '}
                    <span className="font-mono font-semibold">{generatedPassword.password}</span>
                  </span>
                  <button
                    type="button"
                    onClick={copyPassword}
                    className="inline-flex items-center gap-1 rounded-lg border border-current px-2 py-1 text-xs font-medium"
                  >
                    <Copy className="size-3.5" />
                    {t('users.copyButton')}
                  </button>
                </div>
              </SuccessAlert>
            </div>
          )}

          {isLoading ? (
            <p className="text-sm text-slate-500">{t('common.loading')}</p>
          ) : users.length === 0 ? (
            <EmptyState />
          ) : (
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <table className="w-full text-start text-sm">
                <thead>
                  <tr className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th className="px-5 py-3">{t('users.colName')}</th>
                    <th className="px-5 py-3">{t('users.colEmail')}</th>
                    <th className="px-5 py-3">{t('users.colRole')}</th>
                    <th className="px-5 py-3" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {users.map((managedUser) => (
                    <tr key={managedUser.id} className="hover:bg-slate-50/60">
                      <td className="px-5 py-3.5 font-medium text-slate-900">{managedUser.name}</td>
                      <td className="px-5 py-3.5 text-slate-600">{managedUser.email}</td>
                      <td className="px-5 py-3.5">
                        <span className="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">
                          {t(`users.roles.${managedUser.role}`)}
                        </span>
                      </td>
                      <td className="px-5 py-3.5">
                        {managedUser.role !== 'admin' && (
                          <div className="flex justify-end">
                            <button
                              onClick={() => handleDelete(managedUser.id)}
                              aria-label={t('common.delete')}
                              title={t('common.delete')}
                              className="flex size-8 items-center justify-center rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50"
                            >
                              <Trash2 className="size-4" />
                            </button>
                          </div>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function EmptyState() {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <div className="mb-3 flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <UserRound className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('users.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('users.emptyDesc')}</p>
    </div>
  );
}
