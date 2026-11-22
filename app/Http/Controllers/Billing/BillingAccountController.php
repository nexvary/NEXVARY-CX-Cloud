<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingAccountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Billing/Accounts/Index', [
            'accounts' => BillingAccount::latest()->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Billing/Accounts/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'currency'      => ['required', 'string', 'size:3'],
            'credit_limit'  => ['numeric', 'min:0'],
            'rate_table_id' => ['nullable', 'uuid'],
            'type'          => ['nullable', 'string'],
        ]);

        BillingAccount::create($validated);

        return redirect()->route('billing.accounts.index')
                         ->with('success', __('messages.account_created'));
    }

    public function show(BillingAccount $account): Response
    {
        return Inertia::render('Billing/Accounts/Show', compact('account'));
    }

    public function edit(BillingAccount $account): Response
    {
        return Inertia::render('Billing/Accounts/Form', compact('account'));
    }

    public function update(Request $request, BillingAccount $account): \Illuminate\Http\RedirectResponse
    {
        $account->update($request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'enabled'  => ['boolean'],
        ]));

        return redirect()->route('billing.accounts.index')
                         ->with('success', __('messages.account_updated'));
    }

    public function destroy(BillingAccount $account): \Illuminate\Http\RedirectResponse
    {
        $account->delete();

        return redirect()->route('billing.accounts.index')
                         ->with('success', __('messages.account_deleted'));
    }
}
