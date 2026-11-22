<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RouteController extends Controller
{
    public function index(Request $request): Response
    {
        $type = str_contains($request->route()->getName() ?? '', 'inbound') ? 'inbound' : 'outbound';

        return Inertia::render('PBX/Routes/Index', [
            'type'   => $type,
            'routes' => [],
        ]);
    }

    public function create(Request $request): Response
    {
        $type = str_contains($request->route()->getName() ?? '', 'inbound') ? 'inbound' : 'outbound';

        return Inertia::render('PBX/Routes/Form', ['type' => $type]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->back()->with('success', 'Route saved.');
    }

    public function show(string $route): Response
    {
        return Inertia::render('PBX/Routes/Form', ['readonly' => true]);
    }

    public function edit(string $route): Response
    {
        return Inertia::render('PBX/Routes/Form');
    }

    public function update(Request $request, string $route): \Illuminate\Http\RedirectResponse
    {
        return redirect()->back()->with('success', 'Route updated.');
    }

    public function destroy(string $route): \Illuminate\Http\RedirectResponse
    {
        return redirect()->back()->with('success', 'Route deleted.');
    }
}
