import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';

interface Code { code: string; name: string; description: string }

export default function FeatureCodesIndex({ codes }: { codes: Code[] }) {
  return (
    <AppLayout>
      <Head title="Feature Codes" />
      <div className="p-6 max-w-3xl mx-auto space-y-4">
        <h1 className="text-xl font-semibold text-white">Feature Codes</h1>
        <p className="text-gray-400 text-sm">Dial these codes from any extension to activate PBX features.</p>

        <div className="nx-card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-white/5 text-gray-400 text-xs uppercase">
              <tr>
                <th className="px-4 py-3 text-left">Code</th>
                <th className="px-4 py-3 text-left">Feature</th>
                <th className="px-4 py-3 text-left">Description</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {codes.map(c => (
                <tr key={c.code} className="hover:bg-white/5">
                  <td className="px-4 py-3 font-mono text-green-400 font-bold">{c.code}</td>
                  <td className="px-4 py-3 text-white">{c.name}</td>
                  <td className="px-4 py-3 text-gray-400">{c.description}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
}
