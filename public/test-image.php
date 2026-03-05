<?php
$filename = '1771163351_download (2).jpg'; // pick one with spaces
$path = __DIR__ . '/storage/boats/' . $filename;

echo "Requested file: " . htmlspecialchars($filename) . "<br>";
echo "Full path: " . $path . "<br>";
echo "File exists? " . (file_exists($path) ? 'Yes' : 'No') . "<br>";
echo "Is readable? " . (is_readable($path) ? 'Yes' : 'No') . "<br>";

if (file_exists($path)) {
    echo "File size: " . filesize($path) . " bytes<br>";
    echo "File owner: " . fileowner($path) . "<br>";
    echo "File permissions: " . substr(sprintf('%o', fileperms($path)), -4) . "<br>";
}   