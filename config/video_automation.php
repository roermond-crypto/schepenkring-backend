<?php

return [
    'enabled' => env('VIDEO_AUTOMATION_ENABLED', true),
    'auto_on_create' => env('VIDEO_AUTOMATION_ON_CREATE', true),
    'auto_on_publish' => env('VIDEO_AUTOMATION_ON_PUBLISH', true),

    // Treat these as "published" statuses (case-insensitive).
    'publish_statuses' => array_filter(array_map('trim', explode(',', env('VIDEO_AUTOMATION_PUBLISH_STATUSES', 'active,for sale,for bid,published')))),

    'min_images' => (int) env('VIDEO_AUTOMATION_MIN_IMAGES', 8),
    'max_images' => (int) env('VIDEO_AUTOMATION_MAX_IMAGES', 15),
    'seconds_per_image' => (int) env('VIDEO_AUTOMATION_SECONDS_PER_IMAGE', 2),
    'fps' => (int) env('VIDEO_AUTOMATION_FPS', 30),
    'resolution' => env('VIDEO_AUTOMATION_RESOLUTION', '1080x1920'),

    'cta_text' => env('VIDEO_AUTOMATION_CTA_TEXT', 'View full specs on NauticSecure.com'),
    'template_type' => env('VIDEO_AUTOMATION_TEMPLATE', 'vertical_slideshow_v1'),

    // Auto-scheduling defaults
    'auto_schedule' => env('VIDEO_AUTOMATION_AUTO_SCHEDULE', true),
    'auto_notify_owner_whatsapp' => env('VIDEO_AUTOMATION_NOTIFY_OWNER_WHATSAPP', true),
    'schedule_time' => env('VIDEO_AUTOMATION_SCHEDULE_TIME', '10:30'),
    'skip_weekends' => env('VIDEO_AUTOMATION_SKIP_WEEKENDS', false),
    'default_publishers' => array_filter(array_map('trim', explode(',', env('VIDEO_AUTOMATION_PUBLISHERS', 'facebook,instagram,google,linkedin,apple')))),

    'utm' => [
        'source' => env('VIDEO_AUTOMATION_UTM_SOURCE', 'yext'),
        'medium' => env('VIDEO_AUTOMATION_UTM_MEDIUM', 'social'),
        'campaign' => env('VIDEO_AUTOMATION_UTM_CAMPAIGN', 'boat_video'),
    ],
];
