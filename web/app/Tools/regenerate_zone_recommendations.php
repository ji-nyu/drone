<?php
// CLI script: regenerate recommended_action fields inside Data/zone_risk_summary.json
if (php_sapi_name() !== 'cli') {
    echo "This script is intended to be run from CLI.\n";
    exit(1);
}

$base = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Data' . DIRECTORY_SEPARATOR;
$file = realpath($base . 'zone_risk_summary.json') ?: ($base . 'zone_risk_summary.json');
if (! is_file($file)) {
    fwrite(STDERR, "zone_risk_summary.json not found at: $file\n");
    exit(2);
}

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR . 'recommendation_helper.php';

$json = file_get_contents($file);
if ($json === false) {
    fwrite(STDERR, "Failed to read $file\n");
    exit(3);
}

$data = json_decode($json, true);
if (! is_array($data)) {
    fwrite(STDERR, "Invalid JSON in $file\n");
    exit(4);
}

recomputeZoneRecommendedActions($data);

$ok = file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
if ($ok === false) {
    fwrite(STDERR, "Failed to write $file\n");
    exit(5);
}

fwrite(STDOUT, "Regenerated recommended_action in: $file\n");
exit(0);
