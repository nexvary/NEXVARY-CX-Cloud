import React from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Users, Edit, Trash2, Search, Phone, Mail } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Contact {
  id: string;
  name: string;
  email: string | null;
  phone: string | null;
  company: string | null;
  enabled: boolean;
}

interface Props {
  contacts: { data: Contact[]; total: number; current_page: number; last_page: number };
  filters: { search?: string };
}

const ContactsIndex: React.FC<Props> = ({ contacts, filters }) => {
  const { data, setData } = useForm({ search: filters.search ?? '' });

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/crm/contacts', { search: data.search } as Record<string, string>, { preserveState: true });
  };

  const handleDelete = (id: string, name: string) => {
    if (!confirm(`Delete contact "${name}"?`)) return;
    router.delete(`/crm/contacts/${id}`);
  };

  return (
    <AppLayout title="Contacts">
      <Head title="Contacts" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">CRM Contacts</h1>
          <p className="nx-page-subtitle">{contacts.total} contacts</p>
        </div>
        <Link href="/crm/contacts/create" className="nx-btn-primary">
          <Plus className="w-4 h-4" />
          New Contact
        </Link>
      </div>

      <div className="p-6 space-y-4 nx-animate-in">
        <form onSubmit={handleSearch} className="flex gap-3">
          <div className="relative flex-1">
            <Search className="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-nx-muted" />
            <input
              className="nx-input ps-9"
              value={data.search}
              onChange={e => setData('search', e.target.value)}
              placeholder="Search name, email, phone..."
            />
          </div>
          <button type="submit" className="nx-btn-secondary">Search</button>
        </form>

        <div className="nx-card overflow-hidden">
          <table className="nx-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Contact Info</th>
                <th>Company</th>
                <th>Status</th>
                <th className="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {contacts.data.length === 0 && (
                <tr>
                  <td colSpan={5} className="text-center py-12 text-nx-muted">
                    <Users className="w-10 h-10 mx-auto mb-3 opacity-30" />
                    No contacts found.
                  </td>
                </tr>
              )}
              {contacts.data.map(c => (
                <tr key={c.id}>
                  <td>
                    <Link href={`/crm/contacts/${c.id}`} className="font-medium text-nx-primary hover:text-green-400 transition-colors">
                      {c.name}
                    </Link>
                  </td>
                  <td>
                    <div className="space-y-0.5">
                      {c.phone && (
                        <div className="flex items-center gap-1.5 text-xs text-nx-secondary">
                          <Phone className="w-3 h-3" />
                          <span className="font-mono">{c.phone}</span>
                        </div>
                      )}
                      {c.email && (
                        <div className="flex items-center gap-1.5 text-xs text-nx-secondary">
                          <Mail className="w-3 h-3" />
                          {c.email}
                        </div>
                      )}
                    </div>
                  </td>
                  <td className="text-nx-secondary text-sm">{c.company ?? '—'}</td>
                  <td>
                    <span className={`nx-badge ${c.enabled ? 'nx-badge-green' : 'nx-badge-gray'}`}>
                      {c.enabled ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td>
                    <div className="flex items-center justify-end gap-1 pe-2">
                      <Link href={`/crm/contacts/${c.id}/edit`} className="nx-btn-ghost p-1.5">
                        <Edit className="w-3.5 h-3.5" />
                      </Link>
                      <button
                        onClick={() => handleDelete(c.id, c.name)}
                        className="nx-btn-ghost p-1.5 text-red-400 hover:text-red-300"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {contacts.last_page > 1 && (
            <div className="px-4 py-3 border-t border-nx text-xs text-nx-muted flex justify-between">
              <span>{contacts.total} total</span>
              <span>Page {contacts.current_page} / {contacts.last_page}</span>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
};

export default ContactsIndex;
