<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\CallRecord;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        return Inertia::render('CallCenter/Records', [
            'records' => $this->filteredRecords($request)->paginate(50),
            'filters' => $request->only(['search', 'date_from', 'date_to']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredRecords($request);

        return response()->streamDownload(function () use ($query) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Started (ISO 8601)', 'Caller', 'Destination', 'Direction', 'Duration (seconds)', 'Hangup cause'], escape: '');
            foreach ($query->lazy(500) as $call) {
                $row = [
                    $call->started_at?->toIso8601String(), $call->caller_id_number,
                    $call->destination_number, $call->direction, $call->duration_seconds, $call->hangup_cause,
                ];
                fputcsv($file, array_map(fn ($value) => is_string($value) && preg_match('/^[\x00-\x20]*[=+@-]|^[\t\r\n]/', $value)
                    ? "'".$value : $value, $row), escape: '');
            }
            fclose($file);
        }, 'call-detail-records.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'private, no-store']);
    }

    private function filteredRecords(Request $request): Builder
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $query = CallRecord::latest('started_at')->orderBy('id');

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

        return $query;
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
