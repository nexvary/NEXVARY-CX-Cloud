<?php
namespace App\Http\Controllers\Provision;

use App\Http\Controllers\Controller;
use App\Models\DeviceProvision;
use App\Services\PBX\DeviceProvisionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Unauthenticated provisioning endpoint for SIP phones.
 * Rate-limited; serves device-vendor XML config by token.
 * Never returns SIP passwords.
 */
class ProvisionController extends Controller
{
    public function __construct(
        private readonly DeviceProvisionService $service,
    ) {}

    public function serve(Request $request, string $token): Response
    {
        $provision = DeviceProvision::findByToken($token);
        if (!$provision) {
            abort(404);
        }
        $provision->update(['last_provisioned_at' => now()]);
        $xml         = $this->service->render($provision);
        $contentType = $this->service->contentType($provision->device_type);
        $filename    = $this->service->filename($provision);
        return response($xml, 200)
            ->header('Content-Type', $contentType . '; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
