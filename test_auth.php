<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
echo "User email: " . $user->email . "\n";
echo "Password hash: " . $user->password . "\n";
echo "Testing Hash::check with 'password': " . (\Illuminate\Support\Facades\Hash::check('password', $user->password) ? 'true' : 'false') . "\n";
echo "Testing Auth::attempt: " . (\Illuminate\Support\Facades\Auth::attempt(['email' => $user->email, 'password' => 'password']) ? 'true' : 'false') . "\n";
