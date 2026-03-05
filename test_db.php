<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = \App\Models\User::take(5)->get();
foreach ($users as $user) {
    echo "Email: " . $user->email . " | Role: " . $user->role . " | Status: " . $user->status . "\n";
}
