<?php

return [
    'storage_disk' => env('INVOICE_STORAGE_DISK', 'local'),
    'retention_years_default' => (int) env('INVOICE_RETENTION_YEARS', 7),
    'retention_years_immovable_property' => (int) env('INVOICE_RETENTION_YEARS_IMMOVABLE', 10),
    'max_upload_kb' => (int) env('INVOICE_MAX_UPLOAD_KB', 51200),
];
