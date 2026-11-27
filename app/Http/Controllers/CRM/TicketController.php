<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Ticket::with('contact', 'assignee')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return Inertia::render('CRM/Tickets/Index', [
            'tickets' => $query->paginate(30),
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CRM/Tickets/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'subject'    => ['required', 'string', 'max:255'],
            'body'       => ['required', 'string'],
            'priority'   => ['required', 'string'],
            'contact_id' => ['nullable', 'uuid'],
            'channel'    => ['nullable', 'string'],
        ]);

        Ticket::create(array_merge($validated, ['status' => 'open']));

        return redirect()->route('crm.tickets.index')
                         ->with('success', __('messages.ticket_created'));
    }

    public function show(Ticket $ticket): Response
    {
        $ticket->load('contact', 'assignee', 'replies');

        return Inertia::render('CRM/Tickets/Show', compact('ticket'));
    }

    public function edit(Ticket $ticket): Response
    {
        return Inertia::render('CRM/Tickets/Form', compact('ticket'));
    }

    public function update(Request $request, Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $ticket->update($request->validate([
            'subject'  => ['required', 'string', 'max:255'],
            'body'     => ['nullable', 'string'],
            'priority' => ['required', 'string'],
        ]));

        return redirect()->route('crm.tickets.index')
                         ->with('success', __('messages.ticket_updated'));
    }

    public function destroy(Ticket $ticket): \Illuminate\Http\RedirectResponse
    {
        $ticket->delete();

        return redirect()->route('crm.tickets.index')
                         ->with('success', __('messages.ticket_deleted'));
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $ticket->update(['assigned_to' => $request->input('user_id')]);

        return response()->json(['ok' => true]);
    }

    public function changeStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $ticket->update([
            'status'      => $request->input('status'),
            'resolved_at' => $request->input('status') === 'resolved' ? now() : null,
        ]);

        return response()->json(['ok' => true]);
    }
}
