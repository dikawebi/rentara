# Product Requirements Document — Rentara

**Status:** Draft untuk validasi  
**Pasar awal:** Pilot Jakarta, Indonesia; web responsif, Bahasa Indonesia dan Inggris

**Target pilot:** 3–4 bulan; kurang dari 50 unit pada tahap awal

## 1. Ringkasan

Rentara adalah platform untuk mengelola kos dan kontrakan sekaligus menemukan hunian. Pengelola menangani properti, unit, penghuni, tagihan, pembayaran yang dicatat manual, permintaan perawatan, serta pengajuan calon penyewa. Penyewa mengakses informasi hunian dan kewajiban sewanya. Pencari menjelajahi listing, mengajukan hunian, dan mengikuti proses sampai booking dikonfirmasi. Platform mendukung pemilik tunggal, tim pengelola, serta agen/perusahaan yang mengelola properti untuk banyak pemilik.

## 2. Masalah dan peluang

- Informasi unit, ketersediaan, penghuni, dan pembayaran sering tersebar di spreadsheet, chat, atau catatan manual.
- Calon penyewa kesulitan membandingkan listing yang lengkap dan terbaru.
- Proses permintaan sewa dan serah-terima status tidak konsisten.
- Pemilik, staf, agen, penyewa, dan admin membutuhkan akses berbeda terhadap data sensitif.

## 3. Tujuan dan metrik

**Tujuan MVP**

1. Pengelola dapat membuat properti/unit dan menjaga status ketersediaannya.
2. Pencari dapat menemukan listing dan mengirim pengajuan terstruktur.
3. Listing baru ditinjau admin sebelum tayang; pengelola dapat meninjau pengajuan, menyetujui/menolak, dan mengonfirmasi booking melalui alur status yang jelas.
4. Pengelola dan penyewa dapat melihat tagihan serta status pembayaran yang dicatat manual.
5. Keluhan/perawatan dan komunikasi status tercatat; notifikasi dikirim melalui email dan dalam aplikasi.
6. Dokumen identitas penyewa dapat diunggah dengan akses sangat terbatas.

**Metrik awal:** listing aktif dan lengkap; waktu dari pengajuan sampai keputusan; rasio pengajuan yang diproses; tingkat hunian unit; tagihan yang tercatat lunas; jumlah keluhan yang ditutup; keberhasilan verifikasi email; serta laporan penyalahgunaan listing. Baseline dan target numerik ditetapkan setelah pilot.

## 4. Pengguna dan peran

- **Pemilik:** mengelola properti sendiri, anggota, unit, penghunian, tagihan dan laporan.
- **Pengelola/staf:** melakukan tugas operasional sesuai izin pemilik/organisasi.
- **Agen/perusahaan:** mengelola beberapa portofolio pemilik dengan batas tenant dan delegasi akses eksplisit.
- **Penyewa aktif:** melihat kontrak/ringkasan masa sewa, tagihan, status pembayaran, dan mengirim keluhan.
- **Pencari:** mencari/filter listing, menyimpan favorit, verifikasi email, mengajukan sewa, mengunggah identitas, dan mengikuti status.
- **Admin/moderator:** menangani laporan, moderasi listing, akun, dan dukungan; tidak memiliki akses rutin ke dokumen identitas.

## 5. Ruang lingkup

### MVP

- Akun, verifikasi email, profil, Bahasa Indonesia/Inggris.
- Organisasi/portofolio; undangan anggota dan peran dasar; properti dan unit.
- Listing publik, foto, fasilitas, harga, aturan, alamat lengkap, status ketersediaan, pencarian/filter dan halaman detail; listing hanya tayang setelah disetujui admin.
- Alur pengajuan → peninjauan → persetujuan pemilik/pengelola → persetujuan ringkasan syarat dan kontrak oleh kedua pihak (`Verified`) → pembayaran di luar platform → pemilik/pengelola mengonfirmasi pembayaran diterima (`Completed`). Masa berlaku memiliki default organisasi, dapat dioverride per properti atau booking, dan hanya dapat diubah oleh pemilik/manager; jika pembayaran tidak dikonfirmasi sampai tenggat, status menjadi `Expired` dan slot dibuka kembali.
- Verifikasi penyewa melalui email dan unggah dokumen identitas yang dapat ditentukan pemilik/pengelola per properti; pemeriksaan manual. Dokumen disimpan selama masa sewa, lalu dihapus 30 hari setelah tenancy berakhir; dokumen pengajuan yang ditolak/kedaluwarsa dihapus 30 hari setelah status akhir.
- Data penghuni, ringkasan masa sewa, lampiran kontrak, deposit, dan tagihan berulang yang dibuat otomatis tiap periode; pembayaran deposit/sewa dicatat manual. Kewajiban unggah bukti pembayaran dapat dikonfigurasi per organisasi/properti.
- Keluhan/perawatan, komentar/status, notifikasi email dan dalam aplikasi.
- Moderasi laporan listing dan audit aktivitas penting.

### Di luar MVP (kandidat tahap berikutnya)

- Payment gateway/escrow dan pencairan dana.
- Kontrak digital dengan tanda tangan elektronik.
- Aplikasi native, chat real-time, rekomendasi personal, analitik lanjutan.
- Pemeriksaan identitas otomatis/penyedia KYC, pemeriksaan latar belakang, integrasi akuntansi.
- Booking instan dan kebijakan pembatalan/refund otomatis.

## 6. Alur inti

1. Pengelola mendaftar, membuat organisasi, menambahkan properti/unit dan memublikasikan listing.
2. Pencari mencari dan membuka listing; membuat akun atau masuk; memverifikasi email.
3. Pencari mengirim tanggal masuk/durasi, profil, dan dokumen identitas melalui unggahan privat.
4. Pemilik/pengelola yang berwenang meninjau pengajuan dan dokumen identitas; menerima, meminta informasi, atau menolak. Setiap perubahan status memberi notifikasi.
5. Setelah pemilik/pengelola menyetujui pengajuan, ringkasan syarat terstruktur disiapkan dan kontrak dilampirkan. Kedua pihak menyetujui ringkasan dan kontrak; status menjadi `Verified` setelah persetujuan kedua pihak tercatat.
6. Penyewa membayar di luar platform selama masa berlaku yang dapat dikonfigurasi. Pemilik/pengelola mencatat penerimaan pembayaran; status booking menjadi `Completed` setelah konfirmasi tersebut. Jika tenggat lewat tanpa konfirmasi, status menjadi `Expired` dan slot dibuka kembali secara atomik.
7. Sistem membuat tagihan deposit dan sewa bulanan berulang pada tanggal masuk tenancy; untuk tanggal 29–31 yang tidak tersedia dalam suatu bulan, gunakan hari terakhir bulan tersebut. Pengelola dapat membuat tagihan satu kali. Pembayaran dan pengembalian deposit dicatat manual. Penyewa melihat status dan dapat mengirim bukti sesuai kebijakan properti.
8. Penyewa membuat keluhan; pengelola menugaskan/memperbarui status sampai selesai.

## 7. Kebutuhan fungsional utama

- **Properti/unit:** CRUD, beberapa unit per properti, tipe hunian, harga, kapasitas, fasilitas, foto, aturan, alamat/lokasi, kalender ketersediaan dan status draft/published/paused.
- **Pencarian:** kata kunci, area, rentang harga, tipe properti, fasilitas dan ketersediaan; hasil tetap hanya menampilkan listing publik/aktif.
- **Pengajuan/booking:** satu pengajuan aktif per pencari-unit (kebijakan dapat dikonfigurasi); status dan riwayat transisi. `Verified` berarti syarat disetujui kedua pihak dan slot ditahan sampai tenggat; masa berlaku dapat dikonfigurasi. `Completed` berarti pemilik/pengelola telah mengonfirmasi pembayaran luar platform diterima. Jika tidak dikonfirmasi sebelum tenggat, tandai `Expired` dan buka kembali slot secara atomik. Cegah bentrok unit saat konfirmasi maupun kedaluwarsa.
- **Penghuni/masa sewa:** tanggal mulai/akhir, harga dan catatan; data penyewa hanya terlihat oleh pengelola terkait dan penyewa.
- **Tagihan:** jumlah, mata uang IDR, periode, jatuh tempo, status; transaksi tercatat manual oleh peran berizin, disertai aktor/waktu.
- **Keluhan:** kategori, deskripsi, lampiran terbatas, prioritas dan status.
- **Organisasi/izin:** pemilik mengundang anggota; akses dibatasi ke organisasi/properti yang diberikan.
- **Admin:** antrean laporan, moderasi, penangguhan akun/listing, alasan tindakan dan jejak audit.

## 8. Non-fungsional dan asumsi

- Mobile-first, aksesibilitas dasar WCAG 2.1 AA, waktu respons dan ukuran media dioptimalkan.
- Bahasa antarmuka lokalizable; IDR dan zona waktu Asia/Jakarta pada MVP.
- Privasi sejak desain: dokumen identitas tidak pernah menjadi URL publik; retensi/penghapusan mengikuti kebijakan yang disetujui.
- Target ketersediaan, volume, SLA dukungan, kebijakan retensi, kebutuhan kepatuhan dan skala perlu ditentukan sebelum produksi.
- Monetisasi belum diputuskan; setelah pilot, bandingkan langganan pengelola, listing premium, dan komisi sebelum memilih model.
- Pilot awal dibatasi kurang dari 50 unit; onboarding dapat dibantu manual, tanpa memerlukan otomasi import skala besar pada MVP.

## 9. Pertanyaan terbuka

1. Target volume listing/pengguna untuk pilot Jakarta?
2. Kebijakan privasi dan penanganan sengketa lainnya?
3. Apakah agen dapat bertindak atas nama pemilik tanpa akun/consent pemilik?
