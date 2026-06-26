<?php

if (PHP_SAPI != 'cli') {
    exit("CLI only\n");
}

// Bootstrap GLPI
include __DIR__ . '/../../../inc/includes.php';

// Manually require the files to see if it works
require_once __DIR__ . '/../src/Impact/Embodied/NumEcoEval/AbstractAsset.php';
require_once __DIR__ . '/../src/Impact/Embodied/NumEcoEval/Peripheral.php';

use GlpiPlugin\Carbon\Impact\Embodied\NumEcoEval\Peripheral;
use Peripheral as GlpiPeripheral;

echo "Running NumEcoEval calculation...\n";
try {
    $count = Peripheral::evaluateAll(GlpiPeripheral::class);
    echo "Success! Evaluated: $count items\n";
} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
}
