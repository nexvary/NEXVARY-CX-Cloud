<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\CallRecord;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallRecordController extends Controller
{
    public function __construct(private readonly FreeSwitchService $pbx) {}

    public function live(): Response
    {
        $activeCalls = $this->pbx->getActiveCalls();

        return Inertia::render('CallCenter/Live', [
            'active_calls' => $activeCalls,
        ]);
    }

    public function hangup(string $uuid): JsonResponse
    {
        $this->pbx->api("uuid_kill {$uuid}");

        return response()->json(['ok' => true]);
    }

    public function index(Request $request): Response
    {
        $query = CallRecord::latest('started_at');

        if ($request->filled('search')) {
            $q = $request->input('search');
            $query->where(function ($q2) use ($q) {
                $q2->where('caller_id_number', 'like', "%{$q}%")
                   ->orWhere('destination_number', 'like', "%{$q}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('started_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('started_at', '<=', $request->input('date_to'));
        }

        return Inertia::render('CallCenter/Records', [
            'records' => $query->paginate(50),
            'filters' => $request->only(['search', 'date_from', 'date_to']),
        ]);
    }

    public function recordings(Request $request): Response
    {
        $records = CallRecord::whereNotNull('recording_path')
            ->latest('started_at')
            ->paginate(50);

        return Inertia::render('CallCenter/Recordings', [
            'records' => $records,
        ]);
    }
}
