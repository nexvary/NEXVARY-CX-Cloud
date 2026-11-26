import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

export default function EmailSettings({ fusionPbx }: { fusionPbx: boolean }) {
  const ar = usePage().props.locale === 'ar';
  const title = ar ? 'إشعارات البريد الإلكتروني' : 'Email notifications';

  return (
    <AppLayout title={title}>
      <Head title={title} />
      <main className="p-6 space-y-4">
        <h1 className="nx-page-title">{title}</h1>
        <section className="nx-card p-5 space-y-3">
          {fusionPbx ? <>
            <p>{ar ? 'تُضبط إشعارات السنترال في FusionPBX من Advanced ← Default Settings ← Email.' :
              'Configure PBX notifications in FusionPBX under Advanced → Default Settings → Email.'}</p>
            <p>{ar ? 'راجع محاولات التسليم في Status ← Email Queue.' :
              'Review delivery attempts under Status → Email Queue.'}</p>
            <Link className="nx-btn-secondary" href="/settings/integrations">
              {ar ? 'تكامل FusionPBX' : 'FusionPBX integration'}
            </Link>
          </> : <p>{ar ? 'إرسال إشعارات السنترال بالبريد غير متاح في هذا الوضع.' :
            'PBX email notification delivery is unavailable in this mode.'}</p>}
        </section>
      </main>
    </AppLayout>
  );
}
