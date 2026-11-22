<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Contact::latest();

        if ($request->filled('search')) {
            $q = $request->input('search');
            $query->where(fn($qb) =>
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%")
                   ->orWhere('phone', 'like', "%{$q}%")
            );
        }

        return Inertia::render('CRM/Contacts/Index', [
            'contacts' => $query->paginate(30),
            'filters'  => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CRM/Contacts/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['nullable', 'email'],
            'phone'   => ['nullable', 'string'],
            'company' => ['nullable', 'string'],
            'notes'   => ['nullable', 'string'],
            'tags'    => ['nullable', 'array'],
        ]);

        Contact::create($validated);

        return redirect()->route('crm.contacts.index')
                         ->with('success', __('messages.contact_created'));
    }

    public function show(Contact $contact): Response
    {
        $contact->load('tickets', 'smsConversations');

        return Inertia::render('CRM/Contacts/Show', compact('contact'));
    }

    public function edit(Contact $contact): Response
    {
        return Inertia::render('CRM/Contacts/Form', compact('contact'));
    }

    public function update(Request $request, Contact $contact): \Illuminate\Http\RedirectResponse
    {
        $contact->update($request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['nullable', 'email'],
            'phone'   => ['nullable', 'string'],
            'company' => ['nullable', 'string'],
            'notes'   => ['nullable', 'string'],
            'tags'    => ['nullable', 'array'],
            'enabled' => ['boolean'],
        ]));

        return redirect()->route('crm.contacts.index')
                         ->with('success', __('messages.contact_updated'));
    }

    public function destroy(Contact $contact): \Illuminate\Http\RedirectResponse
    {
        $contact->delete();

        return redirect()->route('crm.contacts.index')
                         ->with('success', __('messages.contact_deleted'));
    }
}
