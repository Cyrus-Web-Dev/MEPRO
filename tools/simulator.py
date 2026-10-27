#!/usr/bin/env python3
"""
Simulator ESP32 untuk menguji MEPRO tanpa hardware.

  pip install paho-mqtt
  python tools/simulator.py --machines INJ-01,INJ-02 --rate 0.45

Opsi uji:
  --downtime-after 60 --downtime-for 200   mesin berhenti menghitung (heartbeat tetap jalan) => downtime otomatis
  --buffer-test                            kirim 5 pesan lama bertanda buffered di awal
  --duplicate-test                         kirim ulang pesan yang sama (harus diabaikan server)
Nomor seq disimpan di tools/.sim_seq.json agar tidak bertabrakan saat simulator dijalankan ulang.
"""
import argparse
import json
import os
import random
import time

import paho.mqtt.client as mqtt

STATE_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".sim_seq.json")
PREFIX = "mepro/v1/machines"


def load_state():
    try:
        with open(STATE_FILE) as f:
            return json.load(f)
    except Exception:
        return {}


def save_state(state):
    with open(STATE_FILE, "w") as f:
        json.dump(state, f)


def make_client(code, args):
    try:
        c = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id=f"sim-{code}")
    except AttributeError:  # paho-mqtt 1.x
        c = mqtt.Client(client_id=f"sim-{code}")
    if args.user:
        c.username_pw_set(args.user, args.password)
    c.will_set(f"{PREFIX}/{code}/status", json.dumps({"state": "offline"}), qos=1, retain=True)
    c.connect(args.host, args.port, keepalive=30)
    c.loop_start()
    c.publish(f"{PREFIX}/{code}/status", json.dumps({"state": "online", "ip": "10.0.0.1", "fw": "sim"}), qos=1, retain=True)
    return c


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--port", type=int, default=1883)
    ap.add_argument("--user", default=None)
    ap.add_argument("--password", default=None)
    ap.add_argument("--machines", default="INJ-01")
    ap.add_argument("--rate", type=float, default=0.45, help="unit per detik")
    ap.add_argument("--interval", type=float, default=5.0)
    ap.add_argument("--downtime-after", type=float, default=None)
    ap.add_argument("--downtime-for", type=float, default=200)
    ap.add_argument("--buffer-test", action="store_true")
    ap.add_argument("--duplicate-test", action="store_true")
    args = ap.parse_args()

    codes = [c.strip() for c in args.machines.split(",") if c.strip()]
    state = load_state()
    clients, totals = {}, {}

    for code in codes:
        clients[code] = make_client(code, args)
        state.setdefault(code, int(time.time()))  # seq awal unik
        totals[code] = 0

    def publish_counter(code, delta, ts, buffered=False):
        state[code] += 1
        totals[code] += delta
        payload = {"seq": state[code], "ts": int(ts), "delta": delta, "total": totals[code], "buffered": buffered, "fw": "sim"}
        clients[code].publish(f"{PREFIX}/{code}/counter", json.dumps(payload), qos=0)
        return payload

    if args.buffer_test:
        now = time.time()
        for code in codes:
            for i in range(5, 0, -1):
                publish_counter(code, random.randint(1, 3), now - i * 5, buffered=True)
        print("buffer-test: 5 pesan lama terkirim per mesin")

    start = time.time()
    last_hb = 0
    print("Simulator berjalan. Ctrl+C untuk berhenti.")
    try:
        while True:
            now = time.time()
            elapsed = now - start
            stopped = args.downtime_after is not None and args.downtime_after <= elapsed < args.downtime_after + args.downtime_for

            for code in codes:
                if stopped:
                    continue
                delta = max(0, int(round(random.gauss(args.rate * args.interval, 0.8))))
                if delta > 0:
                    p = publish_counter(code, delta, now)
                    if args.duplicate_test:
                        clients[code].publish(f"{PREFIX}/{code}/counter", json.dumps(p), qos=0)
                    print(f"{code}: +{delta} (total {totals[code]})")

            if now - last_hb >= 30:
                last_hb = now
                for code in codes:
                    clients[code].publish(f"{PREFIX}/{code}/heartbeat", json.dumps({"ts": int(now), "rssi": -60, "uptime": int(elapsed), "buffered_count": 0}), qos=0)

            if stopped:
                print(f"[downtime simulasi] sisa {int(args.downtime_after + args.downtime_for - elapsed)} dtk")
            save_state(state)
            time.sleep(args.interval)
    except KeyboardInterrupt:
        for code, c in clients.items():
            c.publish(f"{PREFIX}/{code}/status", json.dumps({"state": "offline"}), qos=1, retain=True)
            time.sleep(0.2)
            c.loop_stop()
            c.disconnect()
        save_state(state)
        print("Berhenti.")


if __name__ == "__main__":
    main()
