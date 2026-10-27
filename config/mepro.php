<?php

return [
    'timezone' => env('MEPRO_TIMEZONE', 'Asia/Jakarta'),

    'mqtt' => [
        'host' => env('MQTT_HOST', '127.0.0.1'),
        'port' => (int) env('MQTT_PORT', 1883),
        'username' => env('MQTT_USERNAME'),
        'password' => env('MQTT_PASSWORD'),
        'client_id' => env('MQTT_CLIENT_ID', 'mepro-listener'),
        'tls' => (bool) env('MQTT_TLS', false),
        'topic_prefix' => env('MQTT_TOPIC_PREFIX', 'mepro/v1/machines'),
    ],

    // Tidak ada hitungan selama N detik pada mesin dengan run aktif => downtime otomatis
    'idle_threshold_seconds' => (int) env('MEPRO_IDLE_THRESHOLD', 120),
    // Tidak ada pesan apa pun dari device selama N detik => status offline
    'offline_after_seconds' => (int) env('MEPRO_OFFLINE_AFTER', 90),
    // Data mentah machine_counts lebih tua dari N hari diringkas per jam lalu dihapus
    'raw_retention_days' => (int) env('MEPRO_RAW_RETENTION_DAYS', 30),
    // API key untuk endpoint integrasi ERP (header X-Api-Key)
    'integration_key' => env('MEPRO_INTEGRATION_KEY'),
];
