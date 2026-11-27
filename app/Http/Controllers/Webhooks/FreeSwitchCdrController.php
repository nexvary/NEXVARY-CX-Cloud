<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CallRecord;
use App\Models\Extension;
use App\Models\Trunk;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives XML-CDR records from FreeSWITCH mod_xml_cdr via HTTP POST.
 *
 * FreeSWITCH configuration (autoload_configs/xml_cdr.conf.xml):
 *   <param name="url" value="https://your-domain/webhooks/freeswitch/cdr"/>
 *   <param name="retries" value="2"/>
 *   <param name="delay" value="5"/>
 *   <param name="auth-scheme" value="basic"/>
 *   <param name="credentials" value="nexvary:${nexvary_cdr_secret}"/>
 *
 * The endpoint is IP-ACL protected via config/nexvary.php freeswitch.allowed_ips
 * and optionally authenticated with NEXVARY_CDR_SECRET in the environment.
 */
class FreeSwitchCdrController extends Controller
{
    public function store(Request $request): Response
    {
        // Verify shared secret when configured
        $secret = config('nexvary.freeswitch.cdr_secret');
        if ($secret) {
            [$user, $pass] = explode(':', base64_decode(substr($request->header('Authorization', 'Basic '), 7)), 2);
            if ($pass !== $secret) {
                return response('Unauthorized', 401);
            }
        }

        $xml = $request->getContent();
        if (empty($xml)) {
            return response('No body', 400);
        }

        try {
            $this->processXmlCdr($xml);
        } catch (\Throwable $e) {
            Log::error('FreeSWITCH CDR parse error', ['error' => $e->getMessage()]);
            // Return 200 so FreeSWITCH doesn't retry indefinitely
        }

        return response('', 200);
    }

    private function processXmlCdr(string $xml): void
    {
        $doc = new \SimpleXMLElement($xml, LIBXML_NOERROR);
        $vars = $doc->variables ?? null;
        if (!$vars) {
            return;
        }

        $uuid             = (string) ($vars->uuid ?? '');
        $direction        = (string) ($vars->direction ?? 'inbound');
        $callerIdNumber   = (string) ($vars->caller_id_number ?? '');
        $callerIdName     = (string) ($vars->caller_id_name ?? '');
        $destinationNumber= (string) ($vars->destination_number ?? '');
        $hangupCause      = (string) ($vars->hangup_cause ?? '');
        $duration         = (int) ($vars->duration ?? 0);
        $billsec          = (int) ($vars->billsec ?? 0);
        $startEpoch       = (int) ($vars->start_epoch ?? 0);
        $answerEpoch      = (int) ($vars->answer_epoch ?? 0);
        $endEpoch         = (int) ($vars->end_epoch ?? 0);
        $recordingPath    = (string) ($vars->record_path ?? '');
        $tenantId         = (string) ($vars->nexvary_tenant_id ?? '');
        $gatewayId        = (string) ($vars->nexvary_gateway_id ?? '');

        // Skip calls with no meaningful data
        if (!$uuid || $duration === 0) {
            return;
        }

        // Avoid duplicate inserts on retried webhooks
        if (CallRecord::where('uuid', $uuid)->exists()) {
            return;
        }

        // Look up associated extension and trunk by number
        $extension = Extension::withoutGlobalScopes()
            ->where('number', $direction === 'outbound' ? $callerIdNumber : $destinationNumber)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        $trunk = $gatewayId
            ? Trunk::withoutGlobalScopes()->find($gatewayId)
            : null;

        CallRecord::withoutGlobalScopes()->create([
            'tenant_id'          => $tenantId ?: ($extension?->tenant_id),
            'uuid'               => $uuid,
            'caller_id_number'   => $callerIdNumber,
            'caller_id_name'     => $callerIdName,
            'destination_number' => $destinationNumber,
            'direction'          => $direction,
            'duration_seconds'   => $duration,
            'billable_seconds'   => $billsec,
            'hangup_cause'       => $hangupCause,
            'recording_path'     => $recordingPath ?: null,
            'extension_id'       => $extension?->id,
            'trunk_id'           => $trunk?->id,
            'started_at'         => $startEpoch  ? now()->setTimestamp($startEpoch)  : null,
            'answered_at'        => $answerEpoch ? now()->setTimestamp($answerEpoch) : null,
            'ended_at'           => $endEpoch    ? now()->setTimestamp($endEpoch)    : null,
        ]);
    }
}
