<?php

use App\Services\PBX\FreeSwitchService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$pbx = $app->make(FreeSwitchService::class);

try {
    $status = $pbx->getStatus();
    if (!$status['connected'] || empty($status['version'])) {
        fwrite(STDERR, "FreeSWITCH did not return its authenticated status.\n");
        exit(1);
    }
    $calls = $pbx->api('show calls as json');
    if (!is_string($calls) || json_decode($calls, true, flags: JSON_THROW_ON_ERROR)['row_count'] !== 0) {
        fwrite(STDERR, "Expected an empty call list on the isolated switch.\n");
        exit(1);
    }
    printf("Laravel ESL authentication and API payload verified against FreeSWITCH %s.\n", $status['version']);
} finally {
    $pbx->disconnect();
}
