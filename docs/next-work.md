# Next Work Plan — Rentara MVP

Status: siap dikerjakan setelah commit `1ed9585`.

Dokumen ini mengubah gap yang tersisa menjadi urutan kerja konkret. Belum ada tiket di bawah ini yang dianggap selesai hanya karena test atau rencana sudah ditulis.

## Prioritas 1 — Lengkapi isolasi tenant (G1)

### G1.1 — Dokumen identitas dan booking

Tambahkan negative Feature tests untuk organisasi B terhadap data organisasi A:

- review status dokumen identitas;
- download dokumen identitas;
- konfirmasi pembayaran booking;
- perubahan atau akses booking privat;
- audit event dan status database tetap tidak berubah setelah request ditolak.

Definition of done:

- setiap route privat yang diuji mengembalikan `403` atau `404` sesuai kontrak route;
- tidak ada perubahan application, document, booking, tenancy, invoice, atau audit event;
- applicant yang sah dan owner/manager organisasi yang sah tetap dapat mengakses alur normal;
- test dijalankan dengan `RefreshDatabase` dan storage fake.

### G1.2 — Child resource dan keluhan

Tambahkan negative tests untuk unit, foto properti, reorder/delete foto, serta keluhan organisasi. Pastikan ID organisasi, properti, dan child resource yang tidak cocok tidak dapat digunakan untuk IDOR atau untuk memicu reset listing.

## Prioritas 2 — Retensi dan keamanan unggahan (G3)

Verifikasi dan lengkapi kontrak berikut:

- dokumen identitas dan bukti pembayaran hanya berada di disk privat;
- object key tidak memakai nama file asli dan tidak menghasilkan URL publik permanen;
- file ditolak berdasarkan MIME/ukuran/format yang tidak diizinkan;
- dokumen aplikasi ditolak/kedaluwarsa dihapus setelah 30 hari;
- dokumen tenancy dihapus 30 hari setelah tenancy berakhir;
- job purge idempotent, aman saat retry, dan mencatat kegagalan tanpa isi dokumen.

Definition of done:

- test mencakup metadata dan object storage, bukan hanya response HTTP;
- akses lintas organisasi ditolak untuk setiap kategori file privat;
- scheduler/command dapat dijalankan ulang tanpa error atau penghapusan data yang salah.

## Prioritas 3 — Operasional moderasi minimum (F4)

Validasi dashboard/admin workflow untuk:

- antrean listing `PendingReview` dengan pagination/filter;
- approve/reject dengan alasan dan audit;
- hide/restore listing yang sudah published;
- pemisahan akses moderator dari dokumen identitas dan tenancy;
- empty, error, dan stale-request state pada halaman Inertia.

Definition of done:

- moderator hanya melihat field dan media yang diperlukan untuk review listing;
- keputusan stale tidak menimpa status terbaru;
- setiap keputusan menghasilkan audit event yang berisi actor, target, alasan, dan timestamp.

## Prioritas 4 — Readiness deployment dan observability (A1/G4)

Siapkan sebelum staging:

- health/readiness endpoint tanpa membocorkan konfigurasi;
- queue failure dan retry yang terlihat;
- scheduler command list dan cron/worker runbook;
- request/correlation ID pada log aplikasi;
- security headers, redaksi token/PII, dan pemeriksaan dependency/secrets;
- CI yang menjalankan Pint, test suite, dan frontend build.

Definition of done:

- README menjelaskan setup, migration, test, build, queue, scheduler, deploy, dan rollback;
- CI gagal jika test atau build gagal;
- smoke check staging memiliki langkah verifikasi dan rollback yang dapat diulang.

## Urutan eksekusi

1. G1.1, karena booking, dokumen, dan pembayaran adalah data privat berisiko tinggi.
2. G1.2, untuk menutup pola IDOR pada child resource.
3. G3, karena retensi dan storage harus stabil sebelum pilot.
4. F4, agar operasi review listing siap digunakan.
5. A1/G4, sebagai gerbang staging dan pilot.

## Verifikasi setiap perubahan

Jalankan dari `rentara-app/`:

```text
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run build
```

Tambahkan test yang paling sempit terlebih dahulu, lalu ulangi seluruh suite sebelum commit. Jangan memasukkan `.env`, `vendor`, `node_modules`, build artifact, atau private storage ke commit.

## Keputusan yang masih diperlukan

- provider staging dan object storage privat;
- database staging (SQLite untuk test, PostgreSQL/MySQL untuk deployment);
- kebijakan RPO/RTO dan jadwal backup/restore;
- siapa yang memiliki izin moderator dan break-glass admin;
- batas retensi final untuk dokumen setelah tenancy berakhir.
