<?php
declare(strict_types=1);

function supportedRuntime(int $version): bool {
    // Reject untested future branches; extend only after compatibility tests.
    $minimum=[803=>80336,804=>80427,805=>80512];
    return isset($minimum[intdiv($version,100)]) && $version >= $minimum[intdiv($version,100)];
}
function requireSecureRuntime(): void {
    if (!supportedRuntime(PHP_VERSION_ID) || !extension_loaded('pdo_mysql') || !extension_loaded('sodium')) {
        throw new RuntimeException('Unsupported PHP runtime or missing required extensions');
    }
}
