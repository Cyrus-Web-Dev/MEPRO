// MEPRO - firmware penghitung output mesin (ESP32, Arduino framework)
// Kontrak data: lihat docs/02-mqtt-contract.md
#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include <Preferences.h>
#include <time.h>
#include "config.h"

// ---------- hitung pulsa ----------
static volatile uint32_t pulses = 0;
static volatile uint32_t lastPulseUs = 0;
static portMUX_TYPE mux = portMUX_INITIALIZER_UNLOCKED;

void IRAM_ATTR onPulse() {
  uint32_t now = micros();
  portENTER_CRITICAL_ISR(&mux);
  if (now - lastPulseUs > DEBOUNCE_US) {
    pulses++;
    lastPulseUs = now;
  }
  portEXIT_CRITICAL_ISR(&mux);
}

static uint32_t takePulses() {
  portENTER_CRITICAL(&mux);
  uint32_t p = pulses;
  pulses = 0;
  portEXIT_CRITICAL(&mux);
  return p;
}

// ---------- buffer offline (RAM, ring) ----------
struct Msg {
  uint32_t seq;
  uint32_t ts;
  uint32_t delta;
  uint32_t total;
};
static Msg ring[BUFFER_SIZE];
static int ringHead = 0;   // indeks pesan tertua
static int ringCount = 0;

static Msg &ringAt(int i) { return ring[(ringHead + i) % BUFFER_SIZE]; }

static void ringPush(const Msg &m) {
  if (ringCount == BUFFER_SIZE) {
    // Penuh: gabungkan hitungan pesan tertua ke pesan berikutnya agar tidak ada hitungan hilang
    ringAt(1).delta += ringAt(0).delta;
    ringHead = (ringHead + 1) % BUFFER_SIZE;
    ringCount--;
  }
  ring[(ringHead + ringCount) % BUFFER_SIZE] = m;
  ringCount++;
}

static void ringPop() {
  ringHead = (ringHead + 1) % BUFFER_SIZE;
  ringCount--;
}

// ---------- state ----------
static WiFiClient wifi;
static PubSubClient mqtt(wifi);
static Preferences prefs;

static char tCounter[96], tStatus[96], tHeartbeat[96];
static uint32_t seq = 0;
static uint32_t total = 0;
static uint32_t lastSend = 0, lastHeartbeat = 0, lastWifiTry = 0, lastMqttTry = 0, lastZeroSendTs = 0;
static bool timeReady = false;

static uint32_t nowTs() { return (uint32_t)time(nullptr); }

static void persistSeq(bool force) {
  // Tulis ke flash tiap 20 pesan saja (hemat umur flash). Saat boot, seq dilompatkan +25 agar tidak pernah mundur.
  if (force || seq % 20 == 0) prefs.putUInt("seq", seq);
}

// ---------- jaringan ----------
static void ensureWifi() {
  if (WiFi.status() == WL_CONNECTED) return;
  if (millis() - lastWifiTry < 5000 && lastWifiTry != 0) return;
  lastWifiTry = millis();
  WiFi.disconnect();
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.println("WiFi: menyambung...");
}

static void ensureTime() {
  if (timeReady || WiFi.status() != WL_CONNECTED) return;
  configTime(0, 0, "pool.ntp.org", "time.google.com");
  if (nowTs() > 1700000000UL) {
    timeReady = true;
    Serial.println("NTP: waktu tersinkron");
  }
}

static void ensureMqtt() {
  if (mqtt.connected() || WiFi.status() != WL_CONNECTED) return;
  if (millis() - lastMqttTry < 5000 && lastMqttTry != 0) return;
  lastMqttTry = millis();

  char clientId[48];
  snprintf(clientId, sizeof(clientId), "mepro-%s", MACHINE_CODE);
  const char *user = strlen(MQTT_USER) ? MQTT_USER : nullptr;
  const char *pass = strlen(MQTT_PASS) ? MQTT_PASS : nullptr;

  // LWT: broker mengumumkan "offline" bila device mati tiba-tiba
  if (mqtt.connect(clientId, user, pass, tStatus, 1, true, "{\"state\":\"offline\"}")) {
    char buf[128];
    snprintf(buf, sizeof(buf), "{\"state\":\"online\",\"ip\":\"%s\",\"fw\":\"%s\"}", WiFi.localIP().toString().c_str(), FW_VERSION);
    mqtt.publish(tStatus, buf, true);
    Serial.println("MQTT: tersambung");
  } else {
    Serial.printf("MQTT: gagal, rc=%d\n", mqtt.state());
  }
}

static bool publishMsg(const Msg &m) {
  char buf[200];
  bool buffered = (nowTs() - m.ts) > 15;
  snprintf(buf, sizeof(buf),
           "{\"seq\":%lu,\"ts\":%lu,\"delta\":%lu,\"total\":%lu,\"buffered\":%s,\"fw\":\"%s\"}",
           (unsigned long)m.seq, (unsigned long)m.ts, (unsigned long)m.delta, (unsigned long)m.total,
           buffered ? "true" : "false", FW_VERSION);
  return mqtt.publish(tCounter, buf, false);
}

// ---------- program utama ----------
void setup() {
  Serial.begin(115200);
  snprintf(tCounter, sizeof(tCounter), "mepro/v1/machines/%s/counter", MACHINE_CODE);
  snprintf(tStatus, sizeof(tStatus), "mepro/v1/machines/%s/status", MACHINE_CODE);
  snprintf(tHeartbeat, sizeof(tHeartbeat), "mepro/v1/machines/%s/heartbeat", MACHINE_CODE);

  prefs.begin("mepro", false);
  seq = prefs.getUInt("seq", 0) + 25;
  persistSeq(true);

  pinMode(SENSOR_PIN, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(SENSOR_PIN), onPulse, FALLING);

  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  mqtt.setServer(MQTT_HOST, MQTT_PORT);
  mqtt.setBufferSize(512);
  mqtt.setKeepAlive(30);
}

void loop() {
  ensureWifi();
  ensureTime();
  ensureMqtt();
  if (mqtt.connected()) mqtt.loop();

  uint32_t ms = millis();

  // 1) Ambil hitungan tiap SEND_INTERVAL_MS (hanya bila waktu sudah valid)
  if (timeReady && ms - lastSend >= SEND_INTERVAL_MS) {
    lastSend = ms;
    uint32_t delta = takePulses();
    uint32_t ts = nowTs();
    // delta 0 hanya dikirim tiap 60 detik (tanda mesin hidup tapi diam)
    if (delta > 0 || ts - lastZeroSendTs >= 60) {
      if (delta == 0) lastZeroSendTs = ts;
      total += delta;
      seq++;
      persistSeq(false);
      ringPush(Msg{seq, ts, delta, total});
    }
  }

  // 2) Kirim isi buffer (maks 10 per putaran supaya loop tetap responsif)
  int sent = 0;
  while (mqtt.connected() && ringCount > 0 && sent < 10) {
    if (!publishMsg(ringAt(0))) break;  // gagal: coba lagi putaran berikutnya
    ringPop();
    sent++;
  }

  // 3) Heartbeat
  if (mqtt.connected() && ms - lastHeartbeat >= HEARTBEAT_MS) {
    lastHeartbeat = ms;
    char buf[160];
    snprintf(buf, sizeof(buf), "{\"ts\":%lu,\"rssi\":%d,\"uptime\":%lu,\"buffered_count\":%d}",
             (unsigned long)nowTs(), WiFi.RSSI(), (unsigned long)(ms / 1000), ringCount);
    mqtt.publish(tHeartbeat, buf, false);
  }
}
