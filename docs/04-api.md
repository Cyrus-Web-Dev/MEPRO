# API (prefix `/api`, header `Authorization: Bearer <token>`, `Accept: application/json`)

| Method & path | Role | Keterangan |
|---|---|---|
| POST `/login` {email,password} | publik | -> `{token,user}` |
| GET `/me`, POST `/logout`, GET `/operators` | semua | |
| GET `/health` | publik | status DB, zona waktu, mesin online |
| GET `/machines`, `/machines/{id}` | semua | snapshot live (run, OEE, downtime) |
| GET `/master/{resource}` | semua (users: admin/supervisor) | resource: machines, devices, products, materials, shifts, reject-reasons, downtime-reasons, users |
| POST/PUT/DELETE `/master/{resource}[/{id}]` | admin | CRUD |
| GET/PUT `/products/{id}/bom` | GET semua, PUT admin/supervisor | PUT {items:[{material_id,qty_per_unit,scrap_percent}]} |
| GET `/work-orders?status=a,b&machine_id=` , GET `/work-orders/{id}` | semua | |
| POST `/work-orders`, PUT `/work-orders/{id}`, POST `.../cancel`, `.../complete` | admin/supervisor | |
| POST `/work-orders/{id}/start` {machine_id,operator_id?,shift_id?} | semua | operator selalu atas nama sendiri |
| POST `/runs/{id}/end` {after: paused\|completed} | semua | |
| POST `/runs/{id}/rejects` {reject_reason_id,qty,note?} | semua | |
| POST `/machines/{id}/downtimes` {downtime_reason_id?} | semua | bila ada downtime otomatis terbuka, ia diberi alasan |
| PATCH `/downtimes/{id}` {downtime_reason_id}, POST `/downtimes/{id}/end` | semua | |
| GET `/reports/oee?from=&to=&machine_id=` | admin/supervisor | OEE per run/mesin/operator + Pareto |
| POST `/integration/work-orders` | header `X-Api-Key` | upsert WO dari ERP |

Contoh integrasi ERP:
```bash
curl -X POST http://host/api/integration/work-orders -H "X-Api-Key: $MEPRO_INTEGRATION_KEY" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"external_ref":"ERP-7781","product_code":"SIRUP-250","product_name":"Sirup 250ml","target_qty":1000,"machine_code":"INJ-01","due_date":"2026-11-05"}'
```
Aturan bisnis yang dilanggar mengembalikan HTTP 422 `{message}`.
