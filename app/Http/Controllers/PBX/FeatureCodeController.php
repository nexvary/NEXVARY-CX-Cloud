<?php

namespace App\Http\Controllers\PBX;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FeatureCodeController extends Controller
{
    private const CODES = [
        ['code' => '*2',   'name' => 'Call Pickup',         'description' => 'Pick up a ringing call in your group'],
        ['code' => '*3',   'name' => 'DND Toggle',          'description' => 'Toggle Do Not Disturb'],
        ['code' => '*4',   'name' => 'Call Forward All',    'description' => 'Set/clear call forward (prompt for destination)'],
        ['code' => '*5',   'name' => 'Call Forward Busy',   'description' => 'Forward when busy'],
        ['code' => '*6',   'name' => 'Call Forward No Ans', 'description' => 'Forward on no answer'],
        ['code' => '*52',  'name' => 'Check Voicemail',     'description' => 'Dial in to voicemail'],
        ['code' => '*67',  'name' => 'Block Caller ID',     'description' => 'Suppress caller ID for next call'],
        ['code' => '*68',  'name' => 'Park Call',           'description' => 'Park current call in next free slot'],
        ['code' => '*88',  'name' => 'Retrieve Parked',     'description' => 'Retrieve a parked call'],
        ['code' => '*98',  'name' => 'Company Directory',   'description' => 'Dial by name directory'],
        ['code' => '**',   'name' => 'Attended Transfer',   'description' => 'Transfer with announcement'],
        ['code' => '*9',   'name' => 'Record Call',         'description' => 'Toggle call recording (consent required)'],
    ];

    public function index(): Response
    {
        return Inertia::render('PBX/FeatureCodes/Index', [
            'codes' => self::CODES,
        ]);
    }
}
