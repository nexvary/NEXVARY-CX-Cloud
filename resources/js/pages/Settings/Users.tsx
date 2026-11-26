import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';

interface Props {
  users: {
    data: { id: string; name: string; email: string; active: boolean }[];
    prev_page_url: string | null;
    next_page_url: string | null;
  };
  canCreate: boolean;
  tenants: { id: string; name: string }[];
}

export default function UsersSettings({ users, canCreate, tenants }: Props) {
  const ar = usePage().props.locale === 'ar';
  const label = (arabic: string, english: string) => ar ? arabic : english;
  const title = label('مستخدمو لوحة Nexvary', 'Nexvary users');
  const [creating, setCreating] = useState(false);
  const form = useForm({ name: '', email: '', tenant_id: '', password: '', password_confirmation: '' });

  const fields = [
    { key: 'name', title: label('الاسم', 'Name'), type: 'text' },
    { key: 'email', title: label('البريد الإلكتروني', 'Email'), type: 'email' },
    { key: 'password', title: label('كلمة المرور', 'Password'), type: 'password' },
    { key: 'password_confirmation', title: label('تأكيد كلمة المرور', 'Confirm password'), type: 'password' },
  ] as const;

  return (
    <AppLayout title={title}>
      <Head title={title} />
      <div className="p-6 space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-xl font-bold">{title}</h1>
          {canCreate && tenants.length > 0 && !creating && (
            <button type="button" className="nx-btn-primary" onClick={() => setCreating(true)}>
              {label('مستخدم جديد', 'New user')}
            </button>
          )}
        </div>
        <p className="text-sm text-nx-muted">
          {label('حسابات SIP تُدار من FusionPBX عبر الحسابات ← الامتدادات.', 'Manage SIP accounts in FusionPBX under Accounts → Extensions.')}
        </p>
        {canCreate && tenants.length === 0 && <p className="text-sm text-nx-muted">
          {label('أضف مؤسسة نشطة من صفحة الإدارة قبل إنشاء مستخدم.', 'Add an active organization in Administration before creating users.')}
        </p>}
        {creating && (
          <form className="nx-card p-4 grid gap-3 sm:grid-cols-2" onSubmit={event => {
            event.preventDefault();
            form.post('/settings/users', {
              onSuccess: () => { form.reset(); setCreating(false); },
              onError: () => form.reset('password', 'password_confirmation'),
            });
          }}>
            <label className="grid gap-1 sm:col-span-2">
              {label('المؤسسة', 'Organization')}
              <select required className="nx-input" value={form.data.tenant_id} onChange={event => form.setData('tenant_id', event.target.value)}>
                <option value="">{label('اختر مؤسسة', 'Select an organization')}</option>
                {tenants.map(tenant => <option key={tenant.id} value={tenant.id}>{tenant.name}</option>)}
              </select>
              {form.errors.tenant_id && <span role="alert">{form.errors.tenant_id}</span>}
            </label>
            {fields.map(field => (
              <label key={field.key} className="grid gap-1">
                {field.title}
                <input required className="nx-input" type={field.type} autoComplete={field.type === 'password' ? 'new-password' : undefined}
                  value={form.data[field.key]} onChange={event => form.setData(field.key, event.target.value)} />
                {form.errors[field.key] && <span role="alert">{form.errors[field.key]}</span>}
              </label>
            ))}
            <div className="flex gap-2 sm:col-span-2">
              <button type="submit" disabled={form.processing} className="nx-btn-primary">{label('إنشاء مستخدم', 'Create user')}</button>
              <button type="button" className="nx-btn-secondary" onClick={() => {
                form.reset();
                form.clearErrors();
                setCreating(false);
              }}>{label('إلغاء', 'Cancel')}</button>
            </div>
          </form>
        )}
        <div className="nx-card overflow-x-auto">
          <table className="w-full text-start">
            <thead><tr>
              <th className="p-3 text-start">{label('الاسم', 'Name')}</th>
              <th className="p-3 text-start">{label('البريد الإلكتروني', 'Email')}</th>
              <th className="p-3 text-start">{label('الحالة', 'Status')}</th>
            </tr></thead>
            <tbody>{users.data.map(user => <tr key={user.id} className="border-t border-nx">
              <td className="p-3">{user.name}</td>
              <td className="p-3" dir="ltr">{user.email}</td>
              <td className="p-3">{user.active ? label('نشط', 'Active') : label('معطّل', 'Disabled')}</td>
            </tr>)}</tbody>
          </table>
          {users.data.length === 0 && <p className="p-3 text-nx-muted">{label('لا توجد حسابات.', 'No users found.')}</p>}
        </div>
        <div className="flex gap-2">
          {users.prev_page_url && <Link className="nx-btn-secondary" href={users.prev_page_url}>{label('السابق', 'Previous')}</Link>}
          {users.next_page_url && <Link className="nx-btn-secondary" href={users.next_page_url}>{label('التالي', 'Next')}</Link>}
        </div>
      </div>
    </AppLayout>
  );
}
