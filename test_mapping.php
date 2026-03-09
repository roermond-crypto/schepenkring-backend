<?php
// /tmp/test_mapping.php

use App\Models\Yacht;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = 'https://www.schepenkring.nl/aanbod-boten/2006394/maxum-2900/';
$yacht = Yacht::where('external_url', $url)->first();

if ($yacht) {
    echo "Found Yacht ID: " . $yacht->id . "\n";
} else {
    echo "Yacht not found for URL: " . $url . "\n";
}

// Simulate Controller logic
$lookupUrl = rtrim($url, '/');
$resolvedYacht = Yacht::where('external_url', $lookupUrl)
    ->orWhere('external_url', $lookupUrl . '/')
    ->first();

if ($resolvedYacht) {
    echo "Controller logic success! Resolved to ID: " . $resolvedYacht->id . "\n";
} else {
    echo "Controller logic failed to resolve.\n";
}
