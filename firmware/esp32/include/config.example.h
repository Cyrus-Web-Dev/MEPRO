#pragma once
// Salin file ini menjadi config.h lalu isi sesuai lokasi. JANGAN commit config.h.

#define WIFI_SSID        "NamaWiFiPabrik"
#define WIFI_PASS        "passwordwifi"

#define MQTT_HOST        "192.168.1.10"   // IP server broker
#define MQTT_PORT        1883
#define MQTT_USER        "inj-01"         // = devices.mqtt_username
#define MQTT_PASS        ""               // dev (anonymous): kosongkan

#define MACHINE_CODE     "INJ-01"         // = machines.code (huruf besar/angka/strip)
#define FW_VERSION       "1.0.0"

#define SENSOR_PIN       27               // sensor proximity/optocoupler -> GPIO (INPUT_PULLUP, aktif LOW)
#define DEBOUNCE_US      20000UL          // abaikan pulsa < 20 ms
#define SEND_INTERVAL_MS 5000UL
#define HEARTBEAT_MS     30000UL
#define BUFFER_SIZE      256              // ~21 menit data offline pada interval 5 detik
