<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\RateTable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RateTableController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Billing/Rates/Index', [
            'tables' => RateTable::withCount('rates')->latest()->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Billing/Rates/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'currency'    => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
        ]);

        RateTable::create($validated);

        return redirect()->route('billing.rates.index')
                         ->with('success', __('messages.rate_table_created'));
    }

    public function show(RateTable $rate): Response
    {
        $rate->load('rates');

        return Inertia::render('Billing/Rates/Show', ['table' => $rate]);
    }

    public function edit(RateTable $rate): Response
    {
        return Inertia::render('Billing/Rates/Form', ['table' => $rate]);
    }

    public function update(Request $request, RateTable $rate): \Illuminate\Http\RedirectResponse
    {
        $rate->update($request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'currency'    => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
            'enabled'     => ['boolean'],
        ]));

        return redirect()->route('billing.rates.index')
                         ->with('success', __('messages.rate_table_updated'));
    }

    public function destroy(RateTable $rate): \Illuminate\Http\RedirectResponse
    {
        $rate->delete();

        return redirect()->route('billing.rates.index')
                         ->with('success', __('messages.rate_table_deleted'));
    }
}
