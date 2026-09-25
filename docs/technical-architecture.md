# Technical Architecture Document — Rentara

**Status:** Rekomendasi awal; keputusan stack perlu dikonfirmasi sebelum implementasi.

## 1. Prinsip

- Modular monolith untuk MVP: deployment sederhana, transaksi konsisten; pecah layanan hanya ketika kebutuhan skala terbukti.
- API dan aturan bisnis server-side; UI tidak menjadi batas otorisasi.
- Data multi-tenant dengan pemeriksaan akses pada setiap operasi dan audit untuk tindakan sensitif.
- Berkas privat terpisah dari aset listing publik; pemrosesan unggahan melalui alur terkontrol.
- Integrasi eksternal lewat adapter supaya penyedia dapat diganti.
- Optimalkan tahap pertama untuk pilot 3–4 bulan dengan kurang dari 50 unit; hindari infrastruktur terdistribusi dan otomasi skala besar yang belum diperlukan.

## 2. Rekomendasi stack untuk tim saat ini

Dengan pengalaman utama PHP/Laravel dan tim 1–2 orang, rekomendasi MVP adalah **Laravel + Inertia.js + Vue 3 (atau React jika tim lebih nyaman) + TypeScript pada UI**, PostgreSQL, Laravel Queue, serta object storage S3-compatible. Ini mengurangi jumlah aplikasi dan deployment yang harus dirawat tim kecil, sambil tetap mendukung UI responsif dan pengalaman seperti SPA.

- **Web/API:** Laravel modular monolith; Blade/Inertia untuk web, endpoint REST berversi dan OpenAPI untuk kontrak integrasi/mobile selanjutnya.
- **Database:** PostgreSQL; PostGIS bila pencarian radius Jakarta dibutuhkan pada pilot.
- **Queue/cache:** mulai dengan queue database/layanan terkelola untuk email, expiry booking dan pekerjaan media; Redis saat kebutuhan throughput/locking terbukti.
- **Media:** object storage S3-compatible; CDN foto listing publik, dokumen identitas/kontrak privat.
- **Operasional:** managed hosting yang mendukung Laravel, scheduler/queue worker, CI/CD, secret manager, error tracking, log terstruktur dan backup otomatis.

| Opsi | Kelebihan | Trade-off | Kesesuaian |
|---|---|---|---|
| Laravel + Inertia | Selaras dengan keahlian tim, satu codebase/deployment, iterasi cepat | Interaktivitas dan batas API perlu dirancang disiplin; pengalaman mobile kelak membutuhkan API | **Rekomendasi MVP** untuk tim 1–2 orang |
| Laravel API + Next.js | Frontend/API terpisah; cocok bila UI/frontend berkembang mandiri | Dua aplikasi, deployment dan autentikasi lintas origin menambah beban | Bila ada tim frontend terpisah atau kebutuhan UI sangat interaktif |
| Next.js + NestJS | TypeScript end-to-end dan kontrak API eksplisit | Kurva belajar dan beban operasi lebih tinggi untuk tim PHP kecil | Jika tim beralih ke TypeScript sebagai kompetensi inti |

Validasi pilihan Vue/React, provider hosting, email, dan object storage sebelum implementasi. Rancang aturan bisnis/domain agar tidak bergantung pada komponen UI sehingga REST API mobile dapat ditambahkan kemudian.

### Deployment shared hosting untuk pilot

VPS tidak wajib untuk pilot Jakarta di bawah 50 unit. Laravel + Inertia dapat berjalan di shared hosting yang dikelola baik, selama paket/provider memenuhi syarat berikut:

- Mendukung versi PHP/Laravel yang dipilih, ekstensi GD, Composer/SSH atau mekanisme deploy aman, HTTPS, dan pengaturan web root ke direktori `public/` Laravel saja.
- Menyediakan cron yang dapat menjalankan `php artisan schedule:run` setiap menit. Untuk volume pilot kecil, gunakan database queue dan worker terjadwal (`queue:work --stop-when-empty`); pastikan retry, failed jobs, dan monitoring tersedia.
- Menyediakan database yang didukung aplikasi. PostgreSQL tetap pilihan awal; bila paket shared hosting hanya menawarkan MySQL/MariaDB, Laravel mendukungnya, tetapi tetapkan satu engine sebelum implementasi dan uji migrasi, locking, unique constraints, serta transaksi booking pada engine tersebut.
- Menyediakan isolasi akun yang memadai, backup database, log/error access, batas CPU/memori yang jelas, dan proses pemulihan. Jangan mengandalkan backup provider sebagai satu-satunya salinan.
- Menyimpan dokumen identitas dan kontrak di object storage privat (S3-compatible) atau storage di luar web root dengan pembatasan akses yang teruji. Jangan menyimpannya di `public_html` atau direktori publik.

Hindari paket yang hanya menyediakan PHP statis tanpa SSH/deploy, cron andal, kontrol web root, atau isolasi akun. Uji jalur scheduler, queue, unggah privat, backup/restore, serta beban pencarian dan gambar di staging. Jika batas resource/queue menjadi bottleneck, pindahkan aplikasi ke managed app hosting atau VPS; arsitektur domain tidak perlu diubah.

## 3. Konteks dan komponen

`Browser (public, tenant portal, management, admin)` → `Web app/BFF` → `REST API` → `Domain modules` → `PostgreSQL`.

Modul domain: Identity & Organizations, Properties & Units, Listings & Search, Applications & Bookings, Tenancies, Billing & Manual Payments, Maintenance, Notifications, Media, Moderation, Audit. Queue worker/scheduler menjalankan email dan job tertunda; pemrosesan foto pilot berjalan sinkron dengan batas unggah yang ketat dan dapat dipindah ke worker jika hosting mendukungnya. Penyedia email, object storage, peta/geocoding, dan kelak payment gateway berada di belakang adapter.

## 4. Entitas utama

- `User`, `Organization`, `OrganizationMembership` (peran/scope), `OwnerRelationship`.
- `Property` (organization/owner scope), `Unit`, `PropertyPhoto` (path privat, thumbnail WebP privat, urutan dan uploader), `Listing` (slug, status `Draft`/`PendingReview`/`Approved`/`Rejected`/`Paused`, catatan moderator dan waktu review), `ListingMedia`, `Amenity`.
- `RentalApplication` (applicant, listing/unit, tanggal/durasi, status, snapshot profil minimum/listing/dokumen yang diminta, versi/waktu persetujuan privasi, active-application key unik), `IdentityDocument` (tipe, path privat terenkripsi, uploader, status/hasil review dan `delete_after`), `Booking` (snapshot ringkasan syarat, kontrak privat, persetujuan kedua pihak, status pembayaran manual, konfirmasi penerimaan oleh pemilik/pengelola), `BookingExpiryPolicy` (default organisasi dan override properti/booking).
- `Tenancy`, `Tenant`, `Invoice` (jenis termasuk deposit dan periode berulang), `ManualPaymentRecord`, `DepositRefundRecord`, `PaymentEvidence`.
- `MaintenanceRequest`, `MaintenanceComment`, `Notification`, `Report`, `AuditEvent`.

Semua entitas tenant-bound menyimpan `organization_id` atau scope pemilik yang eksplisit. Relasi lintas tabel memvalidasi kesamaan tenant di lapisan domain dan, bila sesuai, constraint/database policy. Pengaturan dokumen identitas yang diminta dapat berbeda per properti. Dokumen identitas disimpan selama tenancy dan dijadwalkan untuk dihapus 30 hari setelah tenancy berakhir; dokumen pengajuan ditolak/kedaluwarsa dihapus 30 hari setelah status akhir. Metadata dan object key acak disimpan, bukan URL permanen.

## 5. API dan alur transaksi

- REST `/api/v1`; JSON, pagination cursor/limit, filter tervalidasi, idempotency key pada aksi konfirmasi yang dapat diulang.
- Endpoint publik hanya mengembalikan field listing yang telah disetujui admin; listing baru tidak dapat dipublikasikan ke marketplace sebelum status moderation `Approved`. Endpoint privat memeriksa identitas dan izin pada tiap request.
- Alur booking: pemilik/pengelola berwenang meninjau identitas. Ringkasan syarat terstruktur dan kontrak privat disimpan sebagai snapshot; `Verified` hanya setelah kedua pihak menyetujui keduanya. Masa berlaku memakai default organisasi dengan override opsional per properti/booking; hanya pemilik dan manager boleh mengubah kebijakan. Pembayaran terjadi di luar sistem dan tidak diklaim terverifikasi otomatis; pemilik/pengelola mencatat penerimaannya. Transaksi mengunci unit/slot, memeriksa tenggat dan konflik, lalu menetapkan `Completed`, mencatat aktor/waktu, dan menulis event outbox/notifikasi. Worker kedaluwarsa `Verified` yang tenggatnya lewat menjadi `Expired` dan melepaskan slot secara atomik. Konfirmasi pembayaran dan expiry harus idempotent serta bersaing secara aman pada transaksi yang sama agar salah satunya menang tanpa booking ganda.
- Status pengajuan/booking ditransisikan oleh command yang tervalidasi; simpan riwayat aktor, waktu dan alasan.
- Worker terjadwal membuat tagihan sewa bulanan pada tanggal masuk tenancy; tanggal 29–31 yang tidak ada di bulan tujuan jatuh tempo pada hari terakhir bulan. Gunakan idempotency key/periode unik agar retry tidak menggandakan invoice. Deposit serta penerimaan/pengembaliannya dicatat manual dan diaudit.
- Unggah foto saat ini memakai multipart ke Laravel setelah otorisasi, maksimal 5 foto per request dan 20 foto per properti, validasi image/MIME/ukuran/dimensi, lalu decode, orientasi EXIF, re-encode ke WebP dan simpan file penuh + thumbnail di disk `property_photos` privat. Nama file asli dan metadata EXIF tidak dipertahankan. Bila pindah ke direct-to-S3, gunakan URL unggah bertanda tangan berumur pendek dan validasi server-side sesudah unggah.
- Dokumen identitas diminta sesuai konfigurasi per properti yang disnapshot ketika pengajuan dibuat. JPEG/PNG diorientasi dan di-encode ulang sebagai JPEG; PDF disimpan privat tanpa preview inline. Byte file dienkripsi dengan Laravel Crypt sebelum disimpan pada disk privat dan hanya didekripsi untuk unduhan terotorisasi. Jaga `APP_KEY` stabil dan gunakan `APP_PREVIOUS_KEYS` saat rotasi kunci agar file lama tetap dapat dibaca. Download memerlukan applicant yang cocok atau owner/manager organisasi terkait dan dicatat pada `audit_events` tanpa isi/PII dokumen. Dokumen aplikasi ditolak/kedaluwarsa dijadwalkan untuk dihapus 30 hari setelah status akhir.

## 6. Pencarian dan media

Mulai dengan PostgreSQL full-text/trigram serta index pada harga, status, tipe, area, dan ketersediaan. Geospatial memakai PostGIS bila dibutuhkan. Search engine eksternal baru diperlukan setelah relevansi/volume menuntut. Foto properti tersimpan di disk privat dan hanya disajikan lewat route terautentikasi yang memeriksa membership organisasi. Sesudah listing `Approved`, route marketplace boleh menyajikan foto terpilih secara publik; setiap request memeriksa status listing dan relasi foto, tanpa URL storage permanen atau symlink. Identitas, kontrak, bukti pembayaran, dan lampiran keluhan tetap privat. Perubahan material properti/unit/foto mengembalikan listing ke `Draft` agar versi yang berubah tidak tetap tayang sebelum review ulang.

## 7. Reliability, observability, deployment

- Lingkungan dev/staging/prod terpisah; migrasi database versioned dan dapat diaudit.
- Backup terenkripsi dan uji pemulihan; RPO/RTO ditentukan bersama pemilik produk.
- Log tanpa password, token, isi dokumen, atau data identitas; correlation ID, metrik error/latensi, antrean dan job.
- Health/readiness probes, alerting, rollback deployment, dan rate limiting untuk autentikasi, pencarian serta unggah.
- Scheduler harian `identity-documents:purge-expired` menghapus file privat dan metadata setelah retention deadline; pada shared hosting, pastikan cron `schedule:run` aktif.
- `is_platform_admin` default false dan tidak dapat diubah melalui registrasi/profil. Provisioning admin produksi memakai prosedur deployment/database tepercaya. Seeder admin hanya berjalan di lingkungan local dan membuat akun bila `LOCAL_PLATFORM_ADMIN_EMAIL` serta `LOCAL_PLATFORM_ADMIN_PASSWORD` diisi di `.env`.
- Uji otomatis unit domain, integrasi PostgreSQL/storage, kontrak OpenAPI, serta alur e2e inti.

## 8. Keputusan yang perlu dikonfirmasi

Stack dan hosting final; kebutuhan peta/geocoding Jakarta; penyedia email; pencarian radius; retensi/region data; estimasi trafik; SLA/RPO/RTO; serta kebutuhan payment gateway tahap lanjutan.
