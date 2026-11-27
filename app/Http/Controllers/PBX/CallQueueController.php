<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\CallQueue;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\QueueProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallQueueController extends Controller
{
    public function __construct(
        private readonly FreeSwitchService $pbx,
        private readonly QueueProvisioningService $provisioning,
    ) {}

    public function index(): Response
    {
        return Inertia::render('PBX/Queues/Index', [
            'queues' => CallQueue::latest()->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PBX/Queues/Form');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255', 'unique:call_queues,name'],
            'strategy'  => ['required', 'string'],
            'timeout'   => ['integer', 'min:30'],
            'moh_sound' => ['nullable', 'string'],
        ]);

        $queue = CallQueue::create($validated);
        $this->provisioning->provision($queue);

        return redirect()->route('pbx.queues.index')
                         ->with('success', __('messages.queue_created'));
    }

    public function show(CallQueue $queue): Response
    {
        return Inertia::render('PBX/Queues/Form', ['queue' => $queue, 'readonly' => true]);
    }

    public function edit(CallQueue $queue): Response
    {
        return Inertia::render('PBX/Queues/Form', ['queue' => $queue]);
    }

    public function update(Request $request, CallQueue $queue): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'strategy'  => ['required', 'string'],
            'timeout'   => ['integer', 'min:30'],
            'moh_sound' => ['nullable', 'string'],
            'enabled'   => ['boolean'],
        ]);

        $queue->update($validated);
        $this->provisioning->provision($queue->fresh());

        return redirect()->route('pbx.queues.index')
                         ->with('success', __('messages.queue_updated'));
    }

    public function destroy(CallQueue $queue): \Illuminate\Http\RedirectResponse
    {
        $this->provisioning->deprovision($queue);
        $queue->delete();

        return redirect()->route('pbx.queues.index')
                         ->with('success', __('messages.queue_deleted'));
    }

    public function agentStatus(Request $request, CallQueue $queue, string $agent): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
