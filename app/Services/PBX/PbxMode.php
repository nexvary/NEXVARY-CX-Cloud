<?php

namespace App\Services\PBX;

final class PbxMode
{
    public static function fusionPbx(): bool
    {
        return (bool) config('nexvary.freeswitch.fusionpbx', false);
    }

    public static function requireLocalManagement(): void
    {
        abort_if(self::fusionPbx(), 403, 'PBX management is delegated to FusionPBX.');
    }
}
