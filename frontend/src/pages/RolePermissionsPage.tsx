import { useEffect, useState } from 'react';
import { Save, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import type { Role } from '../types/auth';
import { PageHeader } from '../components/PageHeader';
import { Button } from '../components/ui/Button';
import { ErrorAlert, SuccessAlert } from '../components/ui/Alert';

interface PermissionDefinition {
  key: string;
  label: string;
  group: string;
}

type EditableRole = Exclude<Role, 'admin'>;

const EDITABLE_ROLES: EditableRole[] = ['tresorier', 'conseil', 'coproprietaire'];

export function RolePermissionsPage() {
  const { t } = useTranslation();

  const [permissions, setPermissions] = useState<PermissionDefinition[]>([]);
  const [grants, setGrants] = useState<Record<EditableRole, string[]>>({ tresorier: [], conseil: [], coproprietaire: [] });
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    api
      .get<{ data: { permissions: PermissionDefinition[]; grants: Record<EditableRole, string[]> } }>('/api/role-permissions')
      .then(({ data }) => {
        setPermissions(data.data.permissions);
        setGrants(data.data.grants);
      })
      .catch((err) => setError(extractErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, []);

  function toggle(role: EditableRole, key: string) {
    setSuccess(false);
    setGrants((previous) => ({
      ...previous,
      [role]: previous[role].includes(key) ? previous[role].filter((k) => k !== key) : [...previous[role], key],
    }));
  }

  async function handleSave() {
    setError(null);
    setSuccess(false);
    setIsSaving(true);

    try {
      await api.put('/api/role-permissions', { grants });
      setSuccess(true);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSaving(false);
    }
  }

  const groups = Array.from(new Set(permissions.map((permission) => permission.group)));

  return (
    <div>
      <PageHeader title={t('rolePermissions.title')} subtitle={t('rolePermissions.subtitle')} />

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}
      {success && (
        <div className="mb-4">
          <SuccessAlert>{t('rolePermissions.savedMessage')}</SuccessAlert>
        </div>
      )}

      {isLoading ? (
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      ) : permissions.length === 0 ? (
        <EmptyState />
      ) : (
        <div className="flex flex-col gap-6">
          {groups.map((group) => (
            <div key={group} className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <div className="border-b border-slate-100 bg-slate-50 px-5 py-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  {t(`rolePermissions.group.${group}`)}
                </p>
              </div>
              <table className="w-full text-start text-sm">
                <thead>
                  <tr className="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th className="px-5 py-2.5 text-start">{t('rolePermissions.colPermission')}</th>
                    {EDITABLE_ROLES.map((role) => (
                      <th key={role} className="px-5 py-2.5 text-center">
                        {t(`users.roles.${role}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {permissions
                    .filter((permission) => permission.group === group)
                    .map((permission) => (
                      <tr key={permission.key}>
                        <td className="px-5 py-3 text-slate-700">{permission.label}</td>
                        {EDITABLE_ROLES.map((role) => (
                          <td key={role} className="px-5 py-3 text-center">
                            <input
                              type="checkbox"
                              className="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30"
                              checked={grants[role].includes(permission.key)}
                              onChange={() => toggle(role, permission.key)}
                            />
                          </td>
                        ))}
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          ))}

          <Button type="button" isLoading={isSaving} onClick={handleSave} className="self-start">
            <Save className="size-4" />
            {t('rolePermissions.saveButton')}
          </Button>
        </div>
      )}
    </div>
  );
}

function EmptyState() {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <div className="mb-3 flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <ShieldCheck className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('rolePermissions.emptyTitle')}</p>
    </div>
  );
}
