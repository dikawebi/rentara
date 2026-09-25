# Development Plan — Rentara MVP

**Tujuan:** pilot terbatas di Jakarta, web responsif, Bahasa Indonesia/Inggris, kurang dari 50 unit.  
**Target awal:** 3–4 bulan, dengan target perencanaan 16 minggu.  
**Model kerja:** satu pemilik produk/developer mengarahkan agent AI; agent spesialis dapat digunakan bergantian untuk implementasi dan review. Target 16 minggu tetap bersyarat: AI mempercepat implementasi, tetapi keputusan produk, integrasi, debugging, review kode, QA, dan deployment memerlukan supervisi manusia.

## 1. Ringkasan kelayakan jadwal

Estimasi terperinci yang tersedia saat ini:

| Area | Estimasi engineering-days |
|---|---:|
| Fondasi akun/organisasi/audit (A1–A5) | 18–30 ED |
| Properti, listing, pencarian dan moderasi (B1–B4, C1–C2, C4) | 24–39 ED |
| Pengajuan sampai booking kedaluwarsa/selesai (D1–D6) | 26–41 ED |
| **Subtotal tiket terestimasi** | **68–110 ED** |
| Operasional penyewa, tagihan, notifikasi, kesiapan rilis (E/F/G) | **43–69 ED indikatif** |
| **Perkiraan total MVP** | **111–179 ED** |

Estimasi E/F/G masih indikatif. ED mengukur kompleksitas/pekerjaan rekayasa, bukan durasi kalender, dan tidak otomatis turun karena memakai AI. Catat throughput dan waktu review aktual dari dua sprint pertama.

Dengan satu orang yang mengarahkan agent AI, target 16 minggu untuk seluruh MVP tetap berisiko dan bergantung pada pengalaman teknis, kualitas konteks repo, serta jumlah iterasi review. Jadwal 3–4 bulan adalah target bersyarat; validasi kapasitas setelah dua sprint pertama. Jika integrasi, debugging, atau review melebihi rencana, pangkas scope atau geser pilot.

## 2. Prinsip perencanaan

- Bangun satu vertical slice yang berfungsi tiap sprint; integrasikan lebih awal, hindari menunggu seluruh backend/frontend selesai.
- Tetapkan kontrak status, izin, dan API sebelum pekerjaan paralel untuk listing, booking, dan invoice.
- Booking, akses lintas tenant, dan berkas identitas/kontrak adalah jalur kritis; keamanan dan pengujian tidak boleh ditunda ke akhir.
- Gunakan pilot terbatas dan onboarding terbantu untuk maksimal 50 unit; jangan bangun skala nasional, payment gateway, aplikasi native, atau agen lintas organisasi pada MVP.
- P0 adalah ruang lingkup kandidat, bukan kewajiban memaksakan semuanya ke tanggal tetap. Perubahan target dilakukan dengan mengurangi cakupan atau menggeser tanggal.

## 3. Rencana tahap dan sprint

Rencana 8 sprint dua mingguan sebagai kerangka AI-assisted. Sprint adalah timebox dan checkpoint demo, bukan asumsi bahwa agent bekerja seperti engineer penuh waktu. Fitur tidak dianggap selesai hanya karena agent menghasilkan kode atau kode berhasil di-merge; kriteria penerimaan tiket dan uji pada staging harus lulus.

| Tahap | Minggu | Hasil/gerbang | Fokus tiket |
|---|---:|---|---|
| **0. Product & technical lock** | 3–5 hari kerja, sebagian paralel sprint 1 | Role matrix, state machine, model unit/ketersediaan, kontrak listing review, pilihan hosting/storage/email, definisi privacy/retensi disetujui | Finalisasi A1, A4, A5, B3, D4, D6; spike unggah privat dan konflik unit |
| **1. Fondasi** | 1–2 | Staging dapat dipakai; akun, verifikasi email, locale; organisasi dan scope awal | A1–A5 (A4 dilanjutkan sprint 2) |
| **2. Properti & unit** | 3–4 | Pengelola membuat properti/unit; kontrol scope; upload foto; listing tersimpan sebagai draft | B1, B2, A4/G1 |
| **3. Review & marketplace** | 5–6 | Admin approve/reject; hanya listing approved tampil; pencarian dan detail listing Jakarta | B3, C1, C2, C4 |
| **4. Pengajuan & dokumen** | 7–8 | Pencari mengajukan; dokumen privat; pengelola memeriksa identitas | D1, D2, D3; G3 awal |
| **5. Kesepakatan & booking window** | 9–10 | Ringkasan syarat/kontrak, persetujuan dua pihak, status `Verified`, tenggat dan expiry | D4, D6, F2/F3 minimum |
| **6. Pembayaran manual & tenancy** | 11–12 | Pembayaran luar platform dicatat; `Completed` atau `Expired`; slot dan tenancy konsisten | D5, E1, B4; uji konkurensi |
| **7. Tagihan & tenant portal** | 13–14 | Deposit, tagihan bulanan otomatis, pencatatan pembayaran/pengembalian, tampilan penyewa | E2–E4, F1–F3 |
| **8. Hardening & pilot** | 15–16 | Uji keamanan/akses, pemulihan backup, operasional moderasi, onboarding pilot <50 unit | F4, G1–G6, perbaikan blocker |

### Gerbang kelulusan tahap

- **Akhir sprint 2:** pengguna/role dan organisasi berjalan; pengujian lintas tenant dasar lulus.
- **Akhir sprint 4:** listing dapat dicari, tetapi tidak muncul sebelum approval admin; dokumen identitas privat.
- **Akhir sprint 6:** seluruh alur booking berhasil dan expiry/slot release teruji, termasuk race pembayaran vs expiry.
- **Akhir sprint 7:** invoice bulanan tidak duplikat saat retry; portal penyewa hanya menampilkan datanya sendiri.
- **Akhir sprint 8:** kriteria keamanan/operasi pilot lulus dan support/moderasi siap.

## 4. Model kerja dengan agent AI

- **Anda (product owner/technical lead):** menetapkan prioritas, memberikan keputusan dan contoh data, menyetujui acceptance criteria, menjalankan/memvalidasi aplikasi, serta menyetujui perubahan sensitif.
- **Agent implementasi:** mengerjakan satu ticket kecil/vertical slice per tugas, membuat perubahan dan test, lalu merangkum file yang disentuh, keputusan, asumsi, dan cara verifikasi.
- **Agent reviewer/QA (opsional, tugas terpisah):** meninjau diff secara independen, mencoba kasus negatif/permission, dan melaporkan temuan tanpa mengubah code pada tugas review.
- Agent hanya mengerjakan tugas paralel bila file/domain tidak tumpang tindih; integrasi dan konflik diselesaikan sebelum ticket dianggap selesai.
- Tetapkan satu sumber instruksi proyek untuk stack/versi, struktur modul, conventions, command test, aturan authorization, data privat, dan batas scope tiap ticket.

### Siklus kerja per ticket

1. Pilih satu ticket dan pastikan dependensi, acceptance criteria, scope file, serta contoh input/output jelas.
2. Untuk ticket berisiko tinggi, minta agent memeriksa struktur terkait dan mengusulkan perubahan sebelum implementasi.
3. Minta implementasi beserta test relevan; agent melaporkan command yang dijalankan dan hasilnya.
4. Review diff sendiri. Jangan menerima perubahan permission, migrasi, status booking, retensi, atau akses file hanya berdasarkan penjelasan agent.
5. Jalankan test lokal; untuk booking/authorization gunakan kasus negatif dan concurrency yang sesuai.
6. Lakukan review independen atau manual, perbaiki temuan, lalu merge satu ticket pada satu waktu.
7. Demo di staging dan tandai selesai hanya setelah seluruh acceptance criteria lulus.

AI dapat mempercepat scaffolding, CRUD, dan test boilerplate. Tetap sediakan waktu untuk keputusan domain, pemeriksaan migrasi, debugging integrasi, review keamanan, regression testing, dan operasi pilot.

## 5. Jalur kritis dan keputusan sebelum coding terkait

1. **A1:** konfirmasi pilihan Laravel + Inertia, provider shared hosting (VPS tidak wajib untuk pilot bila semua prasyarat hosting terpenuhi), database engine, email, dan object storage privat.
2. **A4/B1:** setujui permission matrix, scope organisasi/properti, dan cara pemilik/staf menjadi tenant.
3. **B3/C4:** tetapkan field yang memicu review ulang serta apakah versi approved lama tetap tayang selama revisi.
4. **B4/D4/D5/D6:** setujui unit dan rentang tanggal, cara menahan slot, expiry timezone, nilai minimum/maksimum masa berlaku, serta perilaku transaksi yang menang pada race.
5. **D2/G3:** setujui format/ukuran file identitas dan kontrak, antivirus/processing, akses, retensi 30 hari untuk aplikasi ditolak/expired dan 30 hari setelah tenancy berakhir.
6. **D4:** tinjau format ringkasan syarat dan template kontrak; jangan menganggap persetujuan elektronik sebagai nasihat atau tanda tangan digital yang tersertifikasi.
7. **E2:** tentukan frekuensi dan aturan invoice untuk perubahan tanggal tenancy, bulan pertama/terakhir, tunggakan, pembatalan, dan pengembalian deposit.

## 6. Cut line bila jadwal berisiko

Pertahankan untuk pilot: listing + approval admin, pengajuan, review identitas, persetujuan syarat, expiry/slot release, catatan pembayaran manual, tenant scope, serta backup dan pengujian keamanan.

Pangkas atau sederhanakan lebih dulu:

- Analitik dashboard dan laporan non-esensial.
- Filter marketplace lanjutan; mulai dengan area, rentang harga, tipe, ketersediaan, dan fasilitas utama.
- Kustomisasi invoice di luar jadwal bulanan standar dan tagihan satu kali.
- Preferensi notifikasi lanjutan; pertahankan email/status penting dan notifikasi in-app dasar.
- Admin reporting selain moderasi listing yang diperlukan untuk pilot.

Tetap di luar MVP: payment gateway, tanda tangan digital, chat real-time, saved search/favorit, aplikasi native, KYC otomatis, dan portofolio agen tanpa consent.

## 7. Ritme kerja dan definisi selesai

- **Per sprint:** planning singkat dengan tiket siap, demo akhir sprint kepada pemilik produk, review acceptance, dan retrospektif risiko.
- **Setiap hari:** tinjau progres agent, blocker, dan diff; semua perubahan lewat review sebelum merge, termasuk perubahan yang dibuat agent.
- **Definition of Ready:** keputusan bisnis tersedia, dependensi jelas, desain/API disepakati, acceptance criteria terukur, data sensitif/permission dipetakan.
- **Definition of Done:** implementasi merged, validasi server-side dan authorization ada, unit/integration test relevan lulus, UI responsive dan state error tersedia, audit/notifikasi/retensi diperbarui bila relevan, dokumentasi API/config diperbarui, staging acceptance lulus.
- Jangan memindahkan tiket dengan blocker kebijakan (misalnya masa expiry atau dokumen wajib) ke sprint aktif sebelum keputusan dibuat.

## 8. Risiko dan tindakan

| Risiko | Dampak | Tindakan |
|---|---|---|
| Estimasi total melewati kapasitas target | Pilot terlambat atau fitur tidak stabil | Kalibrasi E/F/G dan throughput setelah sprint 1; pilih cut line sebelum sprint 3 |
| Kesalahan otorisasi tenant | Kebocoran data identitas/booking | Authorization policy terpusat, negative tests untuk setiap resource privat, review independen sebelum pilot |
| Race booking/expiry | Unit dipesan ganda atau slot tertahan | Transaksi/constraint DB, expiry idempotent, uji konkurensi pembayaran vs scheduler |
| File privat terekspos/retensi gagal | Insiden privasi | Bucket privat, signed URL singkat, audit, job penghapusan terpantau dan retry |
| Review admin memperlambat onboarding | Listing terlambat tayang | Buat antrean sederhana, SLA internal pilot, dan panduan moderator |
| Jadwal invoice menghasilkan duplikasi/salah tanggal | Sengketa tagihan | Periode invoice unik, kasus tanggal 29–31 diuji, jadwal dihitung dengan timezone Asia/Jakarta |

## 9. Metrik pilot dan keputusan setelah pilot

Ukur jumlah listing masuk/approved dan waktu review; rasio listing lengkap; pengajuan yang ditinjau; waktu sampai keputusan; booking `Completed` vs `Expired`; konflik unit; invoice duplikat/gagal; keluhan yang selesai; kegagalan email/job; tiket support; serta temuan akses/security. Setelah 4–6 minggu pilot, putuskan apakah memperluas kota/volume, menambah otomasi onboarding, memilih model monetisasi, atau mengintegrasikan payment gateway.
