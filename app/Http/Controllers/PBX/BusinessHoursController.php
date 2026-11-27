<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use App\Models\BusinessHours;
use App\Models\DayNightMode;
use App\Services\PBX\BusinessHoursService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessHoursController extends Controller
{
    public function __construct(private BusinessHoursService $service) {}

    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        return Inertia::render('PBX/BusinessHours/Index', [
            'schedules'  => $this->service->list($tenantId),
            'dayNight'   => $this->service->listDayNight($tenantId),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PBX/BusinessHours/Form', ['schedule' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:128'],
            'schedule'  => ['required', 'array'],
            'holidays'  => ['nullable', 'array'],
            'timezone'  => ['required', 'string', 'max:64'],
            'enabled'   => ['boolean'],
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        $this->service->create($data);

        return redirect()->route('pbx.business-hours.index')->with('success', 'Schedule created.');
    }

    public function edit(BusinessHours $businessHour): Response
    {
        return Inertia::render('PBX/BusinessHours/Form', ['schedule' => $businessHour]);
    }

    public function update(Request $request, BusinessHours $businessHour): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:128'],
            'schedule'  => ['required', 'array'],
            'holidays'  => ['nullable', 'array'],
            'timezone'  => ['required', 'string', 'max:64'],
            'enabled'   => ['boolean'],
        ]);

        $this->service->update($businessHour, $data);

        return redirect()->route('pbx.business-hours.index')->with('success', 'Schedule updated.');
    }

    public function destroy(BusinessHours $businessHour): RedirectResponse
    {
        $this->service->delete($businessHour);
        return redirect()->route('pbx.business-hours.index')->with('success', 'Schedule deleted.');
    }

    public function storeDayNight(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:128'],
            'mode'              => ['required', 'in:day,night'],
            'day_destination'   => ['nullable', 'string', 'max:128'],
            'night_destination' => ['nullable', 'string', 'max:128'],
            'enabled'           => ['boolean'],
        ]);
        $data['tenant_id'] = $request->user()->tenant_id;
        $this->service->createDayNight($data);
        return redirect()->route('pbx.business-hours.index');
    }

    public function toggleDayNight(DayNightMode $dayNightMode): RedirectResponse
    {
        $this->service->toggleMode($dayNightMode);
        return redirect()->route('pbx.business-hours.index');
    }

    public function destroyDayNight(DayNightMode $dayNightMode): RedirectResponse
    {
        $this->service->deleteDayNight($dayNightMode);
        return redirect()->route('pbx.business-hours.index');
    }
}
