# Feature Ticket List — Rentara

Daftar awal untuk estimasi dan prioritisasi. **P0** wajib MVP, **P1** penting bila kapasitas memungkinkan, **P2** tahap berikutnya. Dependensi ditulis sebagai ticket ID. Estimasi menggunakan **engineering-days (ED)**, bukan durasi kalender; 1 ED = satu hari kerja rekayasa, termasuk implementasi dan unit/integration test. Development direncanakan dengan pemilik produk/developer yang dibantu agent AI; ED mengukur kompleksitas, tidak secara langsung memprediksi kecepatan agent. Kalibrasi throughput setelah dua sprint pertama.

## Epic A — Fondasi dan akun

### A1 — Bootstrap aplikasi, CI/CD dan lingkungan (P0, 4–7 ED)

**Dependensi:** keputusan stack/hosting, repository dan akses lingkungan.

**Kriteria penerimaan:**
- Aplikasi Laravel/Inertia berjalan lokal dan deploy otomatis ke staging melalui pipeline; production environment terpisah.
- Konfigurasi sensitif hanya dari secret manager/environment yang terlindungi; tidak ada secret di repository atau log.
- Migrasi database dijalankan dengan proses terkendali; kegagalan deploy dapat di-rollback tanpa kehilangan data yang sudah committed.
- Health/readiness endpoint, log terstruktur dengan request/correlation ID, dan penanganan error aman tersedia.
- Queue worker dan scheduler berjalan pada staging; kegagalan job terlihat dan dapat dicoba ulang.
- README menjelaskan setup lokal, migrasi, test, deploy, rollback, serta akses lingkungan.

### A2 — Registrasi, login dan verifikasi email (P0, 5–8 ED)

**Dependensi:** A1, penyedia email transaksional, rancangan sesi.

**Kriteria penerimaan:**
- Pengguna dapat mendaftar, login/logout, meminta reset password, dan verifikasi alamat email melalui token sekali pakai yang kedaluwarsa.
- Password di-hash dengan algoritme adaptif aman; sesi menggunakan cookie `HttpOnly`, `Secure` di HTTPS, `SameSite` dan perlindungan CSRF.
- Login, reset password dan pengiriman ulang verifikasi memiliki rate limit; respons pemulihan akun tidak membocorkan keberadaan email.
- Email transaksional memakai template Bahasa Indonesia dan Inggris; token sensitif tidak dicatat pada log.
- Endpoint/fitur yang mensyaratkan email verified menolak pengguna yang belum terverifikasi di sisi server.
- Pengujian mencakup token invalid/kedaluwarsa, enumerasi akun, akses tanpa sesi, logout dan reset password.

### A3 — Profil dan preferensi bahasa (P0, 2–3 ED)

**Dependensi:** A2.

**Kriteria penerimaan:**
- Pengguna dapat melihat/mengubah data profil minimum dan memilih Bahasa Indonesia atau Inggris.
- Preferensi locale tersimpan per akun dan digunakan kembali pada login/perangkat yang sama; pengunjung anonim mendapat default yang konsisten.
- Teks UI berada di katalog terjemahan, tanpa string utama yang tertanam hanya dalam satu bahasa.
- Tanggal, waktu Asia/Jakarta, dan IDR diformat sesuai locale tanpa mengubah nilai tersimpan.
- Perubahan profil dibatasi pada akun sendiri dan divalidasi server.

### A4 — Organisasi, undangan dan role scope (P0, 5–8 ED)

**Dependensi:** A1, A2, A5 (kontrak audit); role/permission matrix disepakati.

**Kriteria penerimaan:**
- Pemilik dapat membuat organisasi dan menjadi `organization_owner`; seluruh resource tenant-bound mempunyai scope organisasi/pemilik yang eksplisit.
- Pemilik dapat mengundang anggota melalui email dengan role/scope yang diizinkan; undangan sekali pakai kedaluwarsa, dapat dibatalkan, dan tidak memberi akses sebelum diterima.
- Pemilik/manager dapat melihat anggota serta mencabut keanggotaan; perubahan role/scope berlaku segera pada request berikutnya.
- Role `organization_owner`, `manager`, `staff`, `tenant`, `applicant`, dan `platform_admin` memiliki aksi/scope eksplisit; prinsip default-deny diterapkan.
- Uji negatif membuktikan anggota organisasi A tidak dapat membaca/mengubah ID resource organisasi B dengan mengganti ID/URL/API payload.
- Agen lintas pemilik/organisasi tidak mendapat akses pada MVP tanpa hubungan/consent eksplisit; fitur portofolio agen tetap P1.

### A5 — Audit event dasar (P0, 2–4 ED)

**Dependensi:** A1; skema actor/tenant dan daftar aksi audit disepakati.

**Kriteria penerimaan:**
- Aksi sensitif mencatat aktor, tenant/scope, aksi, jenis dan ID target, timestamp, request ID, serta hasil; catatan bersifat append-only bagi pengguna aplikasi.
- Audit minimal mencakup perubahan role/undangan, akses dokumen, perubahan listing/moderasi, transisi booking, invoice/pembayaran, dan tindakan admin.
- Password, token, isi dokumen, nomor identitas, dan PII yang tidak diperlukan tidak masuk audit/log.
- Akses membaca audit dibatasi pada pemilik/manager untuk scope organisasinya dan admin sesuai kebutuhan operasional; seluruh akses istimewa tercatat.
- Uji memastikan aksi gagal/ditolak yang sensitif tetap menghasilkan event keamanan bila relevan dan data tidak dapat diubah oleh pengguna biasa.

**Catatan estimasi:** A5 membangun skema, helper/layanan penulisan, kebijakan baca, dan aksi fondasi. Integrasi audit untuk fitur listing, booking, dokumen dan pembayaran harus masuk estimasi masing-masing ticket, bukan diasumsikan seluruhnya selesai di A5.

**Estimasi total A1–A5: 18–30 ED**, tidak termasuk waktu menyiapkan akun/provider eksternal, keputusan produk, dan review keamanan independen.

## Epic B — Manajemen properti

Estimasi ticket properti/marketplace menggunakan engineering-days (ED) dan asumsi tim/QA yang dijelaskan di Epic D.

### B1 — CRUD properti dan unit (P0, 5–8 ED)

**Dependensi:** A1–A4.

**Kriteria penerimaan:**
- Pemilik/manager berizin dapat membuat dan mengubah properti kos/kontrakan serta satu atau lebih unit dengan nama, tipe, deskripsi, kapasitas, harga/periode, fasilitas, aturan, alamat lengkap, dan status.
- Field publik dan internal dipisahkan; alamat lengkap tampil pada listing publik sesuai keputusan produk, sementara catatan internal/kontak privat tidak terekspos.
- Pengelola/staf hanya dapat mengubah properti/unit sesuai scope; akses organisasi lain mendapat `403`/`404` tanpa membocorkan keberadaan data.
- Validasi server menolak harga negatif, kapasitas tidak valid, field wajib kosong, dan relasi unit/properti lintas organisasi.
- Draft tersimpan tanpa tampil di marketplace; perubahan penting memiliki aktor dan timestamp audit.

### B2 — Unggah foto listing (P0, 3–5 ED)

**Dependensi:** B1, storage privat/public media policy.

**Kriteria penerimaan:**
- Pengelola dapat mengunggah, mengurutkan, mengganti, dan menghapus foto dari properti/listing dalam scope.
- Server memvalidasi format/ukuran, menghasilkan nama object acak, membuang metadata EXIF, dan membuat thumbnail.
- Media listing yang belum disetujui admin tidak dapat diakses melalui jalur publik; hanya derivative listing approved yang disajikan melalui CDN.
- Upload gagal dapat diulang tanpa membuat orphan media; penghapusan listing/media membersihkan object sesuai kebijakan retensi.

### B3 — Siklus listing dan approval admin (P0, 3–5 ED)

**Dependensi:** B1, B2, A4, A5. Kontrak status antrean review dirilis bersama C4.

**Kriteria penerimaan:**
- Listing memiliki transisi eksplisit `Draft` → `Pending Review` → `Approved/Published` atau `Rejected`; pengelola dapat `Paused`/unpublish.
- Submit untuk review memeriksa kelengkapan data wajib dan hanya aktor berizin pada organisasi pemilik yang dapat mengirim.
- Listing baru tidak dapat diakses di marketplace/API publik sampai admin memberi approval.
- Perubahan pada field material (harga, alamat, unit, fasilitas inti, aturan, foto) mengembalikan listing ke review sebelum versi baru tampil; versi approved lama dapat ditangani sesuai keputusan kebijakan.
- Pengelola melihat status serta alasan reject yang aman; semua keputusan admin menyimpan aktor, timestamp, alasan dan audit.

### B4 — Ketersediaan unit (P0, 3–5 ED)

**Dependensi:** B1, A5. Sediakan kontrak ketersediaan/hold yang dipakai D5/D6; integrasi status booking dapat dikembangkan paralel berdasarkan kontrak tersebut.

**Kriteria penerimaan:**
- Pengelola dapat melihat status unit `Available`, `Held`, `Occupied`, `Unavailable` dan rentang tanggal terkait sesuai izin.
- Unit yang sedang `Verified` ditahan sampai `Completed` atau `Expired`; expiry melepaskan hold dan membuat unit tersedia kembali bila tidak ada hold/tenancy lain.
- Pemeriksaan overlap berlaku pada sisi server/database; dua booking bersamaan untuk unit/rentang yang sama tidak dapat sama-sama berhasil.
- Perubahan manual ke unavailable/occupied ditolak jika bertentangan dengan booking/tenancy aktif, kecuali alur override berizin dan diaudit.
- Marketplace hanya menampilkan status/ketersediaan yang aman untuk dipublikasikan, tidak mengungkap informasi penghuni.

- **B5 (P1) Multi-organisasi/portofolio agen** — Hubungan representasi pemilik dan consent eksplisit.

## Epic C — Marketplace

### C1 — Halaman pencarian listing (P0, 4–6 ED)

**Dependensi:** B1–B4, B3 approved listing contract.

**Kriteria penerimaan:**
- Pengunjung tanpa login dapat mencari listing approved/published berdasarkan area Jakarta, tipe kos/kontrakan, harga, fasilitas dan ketersediaan.
- Kombinasi filter, pagination dan sorting tervalidasi server-side; query invalid tidak menghasilkan error server atau membocorkan data draft.
- Hasil hanya mencakup listing aktif yang lolos review admin dan unit yang tersedia sesuai filter; listing paused/rejected/pending tidak pernah muncul.
- URL pencarian dapat dibagikan dengan parameter filter non-sensitif; empty/loading/error state tersedia dan layout dapat digunakan di mobile.
- Hasil menunjukkan alamat lengkap, harga/periode, tipe, fasilitas inti, foto dan status ketersediaan publik.

### C2 — Detail listing publik (P0, 2–4 ED)

**Dependensi:** B1–B4.

**Kriteria penerimaan:**
- Halaman detail menampilkan data publik approved: alamat lengkap, deskripsi, harga/periode, fasilitas, aturan, foto, kapasitas dan status/ketersediaan.
- Field internal, identitas pemilik, dokumen, data penghuni dan informasi booking tidak ada pada response HTML/API publik.
- Listing yang diturunkan, paused, belum approved, atau tidak ada memberi status 404/halaman unavailable tanpa konten privat.
- CTA mengajukan sewa mengarah ke proses login/pengajuan; foto memiliki alt text dan tampilan responsif.

- **C3 (P1) Favorit dan saved search** — Hanya bagi pengguna autentikasi.

### C4 — Review pra-publikasi dan laporan listing (P0, 4–6 ED)

**Dependensi:** A4, A5, B3.

**Kriteria penerimaan:**
- Admin/moderator dengan permission khusus melihat antrean pending review dengan filter, pagination, preview field publik/media dan perubahan sejak versi sebelumnya.
- Admin dapat approve atau reject dengan alasan; hanya approval yang mengubah listing menjadi published. Request duplikat/stale tidak menimpa keputusan baru.
- Keputusan dan perubahan status tercatat append-only dengan admin, timestamp, alasan dan listing version.
- Perubahan material setelah approval masuk antrean review; konten baru tidak tampil sebelum approve.
- Pengguna dapat melaporkan listing published dengan kategori/alasan; admin dapat hide/restore dengan alasan dan audit.
- Admin tidak otomatis dapat melihat dokumen identitas atau data tenancy saat memoderasi listing.

**Estimasi total B1–B4 + C1–C2 + C4: 24–39 ED**, belum termasuk autentikasi/organisasi dasar A1–A5, QA lintas browser, dan desain visual.

## Epic D — Pengajuan dan booking

Estimasi berikut adalah **engineering-days (ED)** sesuai asumsi di awal dokumen. Rentang memasukkan implementasi dan unit/integration test, tetapi belum termasuk semua review manusia, acceptance QA, desain, atau debugging integrasi. Estimasi perlu direview setelah desain/API disepakati.

### D1 — Formulir pengajuan terstruktur (P0, 3–5 ED)

**Dependensi:** A2, B1–B4, listing published.

**Kriteria penerimaan:**
- Pencari terautentikasi dan email terverifikasi dapat mengirim tanggal masuk, durasi, profil minimum, dan persetujuan privasi yang memiliki versi/waktu.
- Server menolak tanggal tidak valid, listing tidak aktif, unit tidak tersedia, atau pengajuan duplikat aktif sesuai aturan satu pengajuan aktif per pencari-unit.
- Pengajuan tersimpan dengan status `Submitted`, pemohon, unit, tenant scope, waktu dan snapshot data penting; pencari dapat melihat hanya pengajuan miliknya.
- Submit berulang dengan idempotency key yang sama tidak membuat pengajuan ganda; error validasi tampil dekat field terkait.

### D2 — Persyaratan, unggah dan review dokumen identitas (P0, 5–8 ED)

**Dependensi:** A4, B1, D1, G3/storage privat.

**Kriteria penerimaan:**
- Pemilik/manager dapat mengatur jenis dokumen yang diminta per properti; aturan berlaku untuk pengajuan baru dan perubahan berikutnya tidak mengubah berkas lama.
- Pengunggah hanya dapat mengunggah jenis/ukuran file yang diizinkan; object disimpan privat dan tidak memiliki URL publik/permanen.
- Applicant melihat status unggah/review sendiri; pemilik/manager yang berwenang pada properti dapat membuka dokumen lewat URL singkat dan mencatat `Accepted`/`Rejected` beserta alasan dan audit.
- Anggota tanpa izin, tenant lain, serta admin tanpa akses break-glass ditolak; semua percobaan akses sensitif tercatat tanpa menulis isi dokumen ke log.
- Dokumen pengajuan ditolak/kedaluwarsa dijadwalkan dihapus 30 hari setelah status akhir; uji memastikan penghapusan metadata/object dan kegagalan job dapat dicoba ulang.

### D3 — Inbox dan keputusan pengajuan pengelola (P0, 4–6 ED)

**Dependensi:** A4, D1, D2, F2/F3 dasar.

**Kriteria penerimaan:**
- Pengelola hanya melihat pengajuan milik properti dalam scope aksesnya; daftar dapat difilter/pagination menurut status.
- Pengelola dapat meminta informasi tambahan, menyetujui pengajuan, atau menolak dengan alasan; transisi hanya diizinkan dari status yang valid.
- Pengajuan tidak dapat disetujui jika dokumen wajib belum direview/diterima.
- Pencari melihat status, alasan/pesan yang aman, dan langkah berikutnya; perubahan status memicu notifikasi yang tidak menyertakan dokumen/PII sensitif.
- Aksi ganda atau request stale tidak menimpa keputusan terbaru dan memberi respons konflik yang dapat dipahami.

### D4 — Ringkasan syarat, kontrak dan persetujuan dua pihak (P0, 6–9 ED)

**Dependensi:** D3, D2, storage privat, A5.

**Kriteria penerimaan:**
- Pengelola menyiapkan snapshot syarat minimum: unit, tanggal mulai/durasi, harga dan periode, deposit, biaya lain yang disepakati, serta versi kontrak privat.
- Kedua pihak yang terautentikasi dapat melihat versi yang sama dan memberikan persetujuan eksplisit; simpan identitas pemberi persetujuan, timestamp, dan versi syarat/dokumen.
- Status `Verified` tercapai hanya jika pengajuan telah disetujui pengelola dan kedua pihak menyetujui syarat serta kontrak.
- Perubahan material pada syarat atau penggantian kontrak membuat persetujuan sebelumnya tidak berlaku; kedua pihak harus menyetujui versi baru.
- Pengguna hanya dapat mengakses kontrak booking yang terkait dengannya; unduhan menggunakan akses privat sementara.
- Setiap transisi memiliki audit dan notifikasi; aksi idempotent tidak mencatat persetujuan duplikat.

### D5 — Konfirmasi penerimaan pembayaran dan selesaikan booking (P0, 3–5 ED)

**Dependensi:** D4, E1, A5. Menggunakan kontrak status/`expires_at` yang disepakati di D4; implementasi scheduler D6 dapat berjalan paralel.

**Kriteria penerimaan:**
- UI menyatakan pembayaran dilakukan di luar platform; tidak ada status yang menyiratkan verifikasi gateway.
- Pemilik/manager berizin hanya dapat mencatat pembayaran diterima untuk booking `Verified` yang belum kedaluwarsa menurut `expires_at` (meskipun job expiry belum berjalan); catat jumlah/referensi opsional, aktor, waktu dan catatan.
- Booking menjadi `Completed` hanya setelah konfirmasi pemilik/manager berhasil; tindakan ini membuat/menautkan tenancy tanpa membuat tenancy ganda.
- Booking `Completed`, `Expired`, atau sudah dibatalkan tidak dapat dikonfirmasi ulang; endpoint idempotent dan perubahan status/audit konsisten.
- Unit tidak dapat dialokasikan ke booking lain pada rentang tanggal yang sama; respons konflik tidak membocorkan data pemesan lain.

### D6 — Masa berlaku Verified dan pelepasan slot (P0, 5–8 ED)

**Dependensi:** A4, D4, scheduler/queue, F2/F3; implementasi memakai kontrak transisi terminal `Completed` dari D5, tanpa menunggu UI D5 selesai.

**Kriteria penerimaan:**
- Pemilik/manager mengatur masa berlaku default organisasi; dapat dioverride per properti dan per booking dengan validasi nilai/rentang.
- Tenggat booking dihitung dan disimpan saat `Verified`; perubahan kebijakan selanjutnya tidak mengubah tenggat booking yang sudah berjalan.
- Applicant dan pengelola melihat tenggat; pengingat dikirim sebelum tenggat sesuai konfigurasi notifikasi.
- Jika belum `Completed` pada tenggat, worker mengubah status menjadi `Expired`, mencatat alasan/waktu, dan melepaskan slot secara atomik.
- Race antara job expiry dan konfirmasi pembayaran hanya menghasilkan satu status akhir yang sah; retry job aman dan tidak membuka slot untuk booking yang telah `Completed`.
- Booking `Expired` tidak dapat dipulihkan lewat aksi biasa; pihak dapat memulai pengajuan/booking baru.

**Estimasi total D1–D6: 26–41 ED** (belum termasuk desain UX, review legal/kebijakan, QA eksploratif, integrasi email/storage awal, serta tiket fondasi lintas-epic). Ini berpotensi memakan beberapa sprint untuk tim 1–2 orang; kalibrasi estimasi wajib setelah spike storage privat dan model tenancy/availability.

## Epic E — Penghuni dan keuangan manual

- **E1 (P0) Catat tenancy/penghuni** — Hubungkan penyewa, unit, tanggal, harga dan ringkasan syarat.
- **E2 (P0) Deposit dan generator tagihan berulang** — Catat deposit; scheduler membuat tagihan sewa bulanan pada tanggal masuk tenancy (tanggal 29–31 bergeser ke hari terakhir bulan) dan mendukung tagihan satu kali; periode unik/idempotensi mencegah invoice ganda.
- **E3 (P0) Catat pembayaran/pengembalian manual** — Deposit dan sewa, aktor/waktu/metode/referensi, aturan kewajiban bukti dapat dikonfigurasi per organisasi/properti, koreksi melalui reversal yang diaudit.
- **E4 (P0) Portal tagihan penyewa** — Hanya data penyewa terkait; label jelas bahwa pencatatan manual.
- **E5 (P1) Rekap operasional** — Tingkat hunian, tunggakan dan aktivitas; akses sesuai organisasi.
- **E6 (P2) Payment gateway** — Adapter, webhook terverifikasi/idempotent, rekonsiliasi dan refund; keputusan provider terpisah.

## Epic F — Keluhan, notifikasi, administrasi

- **F1 (P0) Keluhan/perawatan** — Buat, lampiran privat, assign, prioritas, komentar dan status.
- **F2 (P0) Notifikasi dalam aplikasi** — Daftar, status baca, akses per penerima.
- **F3 (P0) Email transaksional** — Template ID/EN untuk verifikasi, undangan, pengajuan, tagihan dan keluhan.
- **F4 (P0) Dashboard admin/moderator** — Laporan listing, aksi moderasi dan audit; least privilege.
- **F5 (P1) Pusat preferensi notifikasi** — Pilih notifikasi non-kritis dan kanal yang diizinkan.

## Epic G — Kualitas dan keamanan rilis

- **G1 (P0) Uji otorisasi lintas tenant** — Negatif untuk setiap objek dan endpoint privat.
- **G2 (P0) Uji alur e2e MVP** — Listing → pengajuan → tinjau → booking → tenancy → tagihan → keluhan.
- **G3 (P0) Keamanan unggahan dan retensi dokumen** — Pemeriksaan file, akses privat, simpan dokumen selama tenancy, hapus dokumen tenancy 30 hari setelah tenancy berakhir dan dokumen pengajuan ditolak/kedaluwarsa 30 hari setelah status akhir.
- **G4 (P0) Observability dan alert** — Error, latensi, job gagal, akses dokumen; redaksi data sensitif.
- **G5 (P0) Backup dan uji pemulihan** — Backup terenkripsi, restore diuji, RPO/RTO disetujui.
- **G6 (P1) Accessibility dan responsive QA** — Keyboard, screen reader, kontras dan viewport utama.

## Rencana sprint awal (8 × 2 minggu)

Rencana indikatif untuk 1–2 engineer; mengasumsikan keputusan produk cepat dan bantuan QA/desain dari tim produk. Estimasi perlu dikalibrasi setelah sprint pertama. Targetnya pilot terbatas pada akhir minggu 16, bukan peluncuran nasional.

| Sprint | Hasil yang dapat didemokan | Tiket fokus |
|---|---|---|
| **1 — Fondasi** | Aplikasi berjalan di staging, login/verifikasi email, role dan organisasi awal, logging dasar | A1, A2, A3, A5; kerangka A4 |
| **2 — Properti** | Pemilik mengelola properti/unit; foto tersimpan; organisasi membatasi akses data | Selesaikan A4; B1, B2; mulai G1 |
| **3 — Listing & moderasi** | Draft listing dikirim untuk review; admin approve/reject; listing approved dapat dilihat di marketplace | B3, C2, C4; model status review |
| **4 — Cari & ajukan** | Pencarian/filter pilot Jakarta; pencari login, unggah dokumen privat, kirim pengajuan | C1, D1, D2; G3 dasar |
| **5 — Tinjau & sepakati** | Pengelola meninjau identitas/pengajuan; kedua pihak menyetujui ringkasan dan kontrak; status Verified dan expiry tercatat | D3, D4, D6; notifikasi F2/F3 dasar |
| **6 — Booking & tenancy** | Pemilik mengonfirmasi pembayaran manual sebelum tenggat atau booking expire dan slot terbuka; tenancy tercatat | D5, E1; transaksi/idempotensi; G2 mulai |
| **7 — Tagihan & layanan penyewa** | Deposit dan tagihan bulanan otomatis; pencatatan pembayaran/pengembalian; tenant melihat tagihan; keluhan berfungsi | E2, E3, E4, F1; notifikasi F2/F3 |
| **8 — Hardening & pilot** | Moderasi operasional, akses lintas tenant tervalidasi, backup/restore, responsive QA, onboarding hingga <50 unit | F4, G1–G6; perbaikan blocker dan persiapan pilot |

### Gerbang penerimaan pilot

- Hanya listing yang disetujui admin muncul di marketplace; perubahan material kembali ke review.
- Pengajuan melewati review identitas, persetujuan syarat dua pihak (`Verified`), pembayaran luar platform, lalu `Completed`; jika tenggat habis, `Expired` melepaskan unit dengan aman.
- Tagihan bulanan tidak terduplikasi saat job diulang; tanggal 29–31 jatuh tempo di hari terakhir bulan terkait.
- Dokumen identitas/kontrak privat tidak dapat diakses lintas tenant dan mengikuti jadwal retensi yang disepakati.
- Alur inti lolos uji e2e dan akses negatif, backup berhasil dipulihkan, notifikasi penting bekerja, serta admin dapat menangani review listing.

### Pengendalian cakupan dan risiko jadwal

- Jika kapasitas mendekati satu engineer, sederhanakan dashboard/reporting dan hindari saved search, analitik, payment gateway, serta otomasi onboarding.
- Fitur P1/P2 tetap di luar jalur kritis 16 minggu kecuali buffer tersedia.
- Demo dan review penerimaan dilakukan setiap sprint; tiket tanpa kriteria penerimaan atau keputusan kebijakan belum siap dikerjakan.
- Sprint 8 berfungsi sebagai hardening/pilot terbatas; jika pekerjaan P0 belum stabil, geser pilot alih-alih mengurangi pemeriksaan keamanan dokumen atau akses tenant.

Setiap ticket perlu dilengkapi estimasi, owner, definisi desain/API, kriteria QA, serta keputusan dependensi sebelum masuk sprint.

Lihat [Development Plan](development-plan.md) untuk jadwal sprint, kapasitas, jalur kritis, cut line MVP dan gerbang kesiapan pilot.
