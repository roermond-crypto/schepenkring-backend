<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = \App\Models\User::take(5)->get();
foreach ($users as $user) {
    echo "Email: " . $user->email . " | Password: " . (\Illuminate\Support\Facades\Hash::check('password', $user->password) ? 'Matches password' : 'Does not match') . "\n";
}
