import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { MessageSquare, Send, Plus, Search } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';

interface Message {
  id: string;
  body: string;
  direction: 'inbound' | 'outbound';
  created_at: string;
}

interface Conversation {
  id: string;
  contact_number: string;
  status: string;
  last_message_at: string;
  messages?: Message[];
}

interface Props {
  conversations: { data: Conversation[]; total: number };
  active_conversation?: Conversation & { messages: Message[] };
}

const SmsPage: React.FC<Props> = ({ conversations, active_conversation }) => {
  const [search, setSearch] = useState('');

  const { data, setData, post, processing, reset } = useForm({ body: '' });

  const sendMessage = (e: React.FormEvent) => {
    e.preventDefault();
    if (!active_conversation) return;
    post(`/sms/${active_conversation.id}`, { onSuccess: () => reset() });
  };

  const filtered = conversations.data.filter(c =>
    c.contact_number.includes(search)
  );

  return (
    <AppLayout title="SMS">
      <Head title="SMS" />

      <div className="nx-page-header flex items-center justify-between">
        <div>
          <h1 className="nx-page-title">SMS Conversations</h1>
          <p className="nx-page-subtitle">{conversations.total} conversations</p>
        </div>
        <button className="nx-btn-primary gap-2 text-sm">
          <Plus className="w-4 h-4" />
          New Message
        </button>
      </div>

      <div className="flex h-[calc(100vh-10rem)] nx-animate-in">
        {/* Conversation list */}
        <div className="w-72 border-e border-nx flex flex-col">
          <div className="p-3 border-b border-nx">
            <div className="relative">
              <Search className="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-nx-muted" />
              <input
                className="nx-input ps-9 text-sm"
                placeholder="Search number..."
                value={search}
                onChange={e => setSearch(e.target.value)}
              />
            </div>
          </div>
          <div className="flex-1 overflow-y-auto">
            {filtered.length === 0 && (
              <div className="p-8 text-center text-nx-muted text-sm">
                <MessageSquare className="w-8 h-8 mx-auto mb-2 opacity-30" />
                No conversations
              </div>
            )}
            {filtered.map(c => (
              <button
                key={c.id}
                onClick={() => router.visit(`/sms/${c.id}`)}
                className={`w-full text-start px-4 py-3 border-b border-nx/50 hover:bg-nx-card transition-colors ${
                  active_conversation?.id === c.id ? 'bg-nx-card-hover' : ''
                }`}
              >
                <div className="flex items-center gap-2">
                  <div
                    className="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                    style={{ background: 'var(--nx-green-muted)', color: 'var(--nx-green)' }}
                  >
                    {c.contact_number.slice(-2)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between">
                      <span className="font-mono text-sm text-nx-primary truncate">
                        {c.contact_number}
                      </span>
                      {c.status === 'new' && (
                        <span
                          className="w-2 h-2 rounded-full shrink-0"
                          style={{ background: 'var(--nx-green)' }}
                        />
                      )}
                    </div>
                    <p className="text-xs text-nx-muted truncate">{c.last_message_at}</p>
                  </div>
                </div>
              </button>
            ))}
          </div>
        </div>

        {/* Message thread */}
        {active_conversation ? (
          <div className="flex-1 flex flex-col">
            <div className="px-4 py-3 border-b border-nx">
              <p className="font-mono font-medium text-nx-primary">{active_conversation.remote_number}</p>
            </div>
            <div className="flex-1 overflow-y-auto p-4 space-y-3">
              {active_conversation.messages?.map(msg => (
                <div
                  key={msg.id}
                  className={`flex ${msg.direction === 'outbound' ? 'justify-end' : 'justify-start'}`}
                >
                  <div
                    className={`max-w-xs px-3 py-2 rounded-xl text-sm ${
                      msg.direction === 'outbound'
                        ? 'bg-green-500/20 text-green-100'
                        : 'bg-nx-elevated text-nx-primary'
                    }`}
                  >
                    <p>{msg.body}</p>
                    <p className="text-xs text-nx-muted mt-1">{msg.created_at}</p>
                  </div>
                </div>
              ))}
            </div>
            <form onSubmit={sendMessage} className="p-3 border-t border-nx flex gap-2">
              <input
                className="nx-input flex-1 text-sm"
                value={data.body}
                onChange={e => setData('body', e.target.value)}
                placeholder="Type a message..."
              />
              <button type="submit" disabled={processing || !data.body.trim()} className="nx-btn-primary px-3">
                <Send className="w-4 h-4" />
              </button>
            </form>
          </div>
        ) : (
          <div className="flex-1 flex items-center justify-center text-nx-muted">
            <div className="text-center">
              <MessageSquare className="w-10 h-10 mx-auto mb-3 opacity-20" />
              <p className="text-sm">Select a conversation</p>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
};

export default SmsPage;
