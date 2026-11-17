# RacikHRIS

HRIS & payroll otomatis untuk **PT Racik Solusi Teknologi** (Jl. Sidowaras No.35, Gresik).
Laravel 13 · Sanctum · DomPDF · SQLite (dev) / MySQL / PostgreSQL.

- Backend: Reza Eka Firmansyah (1202230015)
- Frontend: Muhammad Febriano Aldo Syahputra (1202230021)

## Setup

```sh
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve        # http://127.0.0.1:8000
php artisan test
```

Untuk MySQL/PostgreSQL ubah `DB_*` di `.env`. Matikan `APP_DEMO_SWITCHER` di produksi.

## Akun demo (password: `password`)

| Role | Email | Akses |
|---|---|---|
| HR Admin | hr@demo.test | master data, payroll, semua data |
| Manager (Tech Lead) | manager@demo.test | approval cuti/lembur/koreksi tim |
| Employee | karyawan1@demo.test | clock-in/out, pengajuan, slip gaji |

Role switcher: `GET /api/auth/demo-accounts`, `POST /api/auth/demo-login {"role": "hr_admin|manager|employee"}`.

## API (semua `/api`, kecuali auth pakai `Authorization: Bearer <token>`)

| Method | Endpoint | Role |
|---|---|---|
| POST | `/auth/login`, `/auth/demo-login` | publik |
| GET/POST | `/auth/me`, `/auth/logout` | login |
| GET / PUT | `/company` | login / HR |
| GET | `/employees` | HR, manager |
| POST/GET/PUT/DELETE | `/employees[/{id}]` | HR (DELETE = nonaktifkan) |
| GET | `/attendances?month=YYYY-MM&mode=&employee_id=` | scoped per role |
| GET | `/attendances/today` | login |
| POST | `/attendances/clock-in {mode: WFO\|WFH\|CLIENT_SITE, client_name?}` · `/attendances/clock-out` | login |
| GET/POST | `/leave-requests`, `/overtime-requests`, `/attendance-corrections` | login |
| POST | `/{leave-requests\|overtime-requests\|attendance-corrections}/{id}/approve\|reject` | manager, HR |
| GET | `/payroll/periods`, `/payroll/periods/{id}` | HR |
| POST | `/payroll/generate {year, month}` | HR |
| POST | `/payroll/periods/{id}/finalize`, `/mark-paid` | HR |
| GET | `/payroll/periods/{id}/export` (CSV rekap bank) | HR |
| GET | `/payslips/mine`, `/payslips/{id}`, `/payslips/{id}/pdf` | karyawan (periode final), HR |

Data per role: karyawan melihat dirinya, manager melihat timnya, HR melihat semua.

## Aturan payroll (`config/payroll.php`)

- Bruto = gaji pokok + tunjangan tetap + (makan + transport harian × hari hadir) + lembur.
- Lembur hari kerja: upah/jam = gaji pokok / 173; jam pertama 1,5×, berikutnya 2× (hanya lembur yang disetujui).
- BPJS Kesehatan 1% (plafon Rp12 jt), JHT 2%, JP 1% (plafon Rp10.547.400) dari gaji pokok + tunjangan tetap.
- PPh 21 TER: kategori A/B/C dari status PTKP × tarif bruto bulanan. **Verifikasi tabel TER dengan PMK 168/2023 sebelum dipakai riil.**
- Telat = clock-in melewati 08:00 + toleransi 15 menit (`company_settings`).
- Payslip baru terlihat karyawan setelah periode **finalized**; periode final tidak bisa digenerate ulang.

## Alur Git

```sh
# sekali saja (sudah dibuat): main, develop, feature/backend-api-reza, feature/frontend-aldo
git remote add origin <url-repo>
git push -u origin main develop feature/backend-api-reza feature/frontend-aldo

# Reza
git checkout feature/backend-api-reza
git add -A && git commit -m "feat(payroll): ..." && git push

# Aldo
git checkout feature/frontend-aldo
git pull origin develop           # ambil API terbaru
git add -A && git commit -m "feat(ui): ..." && git push

# Integrasi: PR feature/* -> develop; rilis akhir: develop -> main
git checkout develop && git merge --no-ff feature/backend-api-reza
```

Aturan: backend hanya menyentuh `app/`, `database/`, `routes/`, `config/`, `tests/`; frontend hanya `resources/` dan aset. Konvensi commit: `feat|fix|chore|test|docs(scope): pesan`.
