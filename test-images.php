<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$images = App\Models\YachtImage::orderBy('id', 'desc')->limit(5)->get();
foreach($images as $img) {
    echo "ID: {$img->id}, Yacht: {$img->yacht_id}\n";
    $candidates = array_filter([
        $img->optimized_master_url,
        $img->url,
        $img->original_kept_url,
        $img->thumb_url,
        $img->original_temp_url,
    ]);
    foreach ($candidates as $c) echo "  Candidate: $c\n";
    // call private method using reflection
    $controller = new App\Http\Controllers\Api\AiPipelineController();
    $reflection = new ReflectionClass(get_class($controller));
    $method = $reflection->getMethod('resolveStoredImagePath');
    $method->setAccessible(true);
    $path = $method->invokeArgs($controller, [$img]);
    echo "  Resolved path: $path\n";
    if ($path && file_exists($path)) {
        echo "  Size: " . filesize($path) . " bytes\n";
    } else {
        echo "  FILE MISSING OR NULL\n";
    }
}
