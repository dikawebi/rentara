# Security & Access Document — Rentara

## 1. Tujuan dan prinsip

Melindungi akun, data tenant, pembayaran yang dicatat, dan terutama dokumen identitas. Terapkan least privilege, default-deny, pemisahan tenant, minimisasi data, audit yang dapat ditinjau, dan akses privat sementara.

## 2. Identitas dan autentikasi

- Verifikasi email wajib untuk pencari yang mengajukan dan pengelola yang memublikasikan/mengelola data.
- Password disimpan dengan algoritme hash adaptif kuat; token sesi aman, rotasi/revokasi saat logout atau perubahan kredensial; cookie `HttpOnly`, `Secure`, `SameSite`.
- Proteksi brute force dan credential stuffing; rate limit dan penundaan progresif. MFA direkomendasikan wajib untuk admin dan tersedia bagi pemilik/pengelola.
- Pemulihan akun memakai token sekali pakai, berumur pendek, dan tidak membocorkan keberadaan akun.

## 3. Otorisasi dan batas akses

Periksa izin di server pada setiap request dan setiap objek (mencegah IDOR). Role dasar: `platform_admin`, `organization_owner`, `manager`, `staff`, `tenant`, `applicant`. Role harus dibatasi dengan scope organisasi/properti dan aksi, bukan hanya nama role. Hak `platform_admin` default false dan hanya diberikan melalui provisioning operasional tepercaya—bukan dari input registrasi atau profil.

| Data/aksi | Pemilik/berizin | Penyewa terkait | Pencari terkait | Admin |
|---|---|---|---|---|
| Properti/unit & listing | Kelola dalam scope | Lihat informasi hunian sendiri | Lihat field publik | Moderasi; akses operasional tercatat |
| Pengajuan | Tinjau dalam scope | Tidak otomatis | Buat dan lihat pengajuan sendiri | Dukungan sesuai kebutuhan |
| Dokumen identitas | Pemilik/pengelola berwenang dalam scope, akses terbatas | Unggah, lihat status, unduh dokumen sendiri | Unggah dan status sendiri | Tidak ada akses rutin; break-glass tercatat |
| Kontrak booking | Pemilik/pengelola berwenang dalam scope | Lihat/unduh kontrak booking sendiri | Hanya jika bagian dari pengajuan sendiri | Tidak ada akses rutin; break-glass tercatat |
| Tagihan/pembayaran | Kelola dalam scope | Lihat tagihan sendiri | Tidak ada | Dukungan terbatas dan tercatat |
| Keluhan | Kelola dalam scope | Buat dan lihat sendiri | Tidak ada | Akses dukungan sesuai kebutuhan |

Undangan anggota harus dicakup pada organisasi/properti, dapat dicabut, kedaluwarsa, dan meninggalkan audit trail. Agen yang mewakili pemilik membutuhkan hubungan/consent eksplisit; jangan mengasumsikan akses lintas organisasi.

## 4. Perlindungan data dan unggahan

- Klasifikasi: publik (listing yang disetujui), internal (operasional), sensitif (kontak, keuangan, keluhan), sangat sensitif (dokumen identitas).
- Enkripsi in transit dan at-rest; kunci dikelola melalui layanan KMS/secret manager.
- Dokumen identitas dienkripsi dengan application key sebelum disimpan di disk privat tanpa URL publik; unduhan sebagai attachment hanya setelah pemeriksaan applicant atau owner/manager organisasi terkait. Jaga `APP_KEY`/`APP_PREVIOUS_KEYS` saat rotasi. Unggah, review, unduhan, dan purge dicatat pada audit event tanpa isi/PII dokumen.
- Batasi dokumen ke JPEG/PNG/PDF dan ukuran 5 MB; object key acak, image di-encode ulang, PDF tidak dirender inline. Jangan simpan isi dokumen di log, analytics atau cache publik.
- Dokumen identitas aplikasi ditolak/kedaluwarsa dihapus otomatis 30 hari setelah status akhir melalui scheduler. Dokumen penyewa aktif disimpan selama masa sewa dan harus dihapus 30 hari setelah tenancy berakhir saat alur tenancy tersedia. Dokumentasikan dasar pemrosesan, proses penghapusan/ekspor, serta penanganan insiden sesuai kewajiban hukum yang berlaku.

## 5. Keamanan aplikasi dan operasi

- Validasi input, output encoding, proteksi CSRF/XSS/SQL injection, kebijakan CORS ketat, CSP, TLS, security headers.
- Rate limit login, OTP/email, pencarian, pembuatan pengajuan dan unggah; deteksi spam/abuse.
- Secret tidak masuk repo; dependency scanning, patch rutin, akses produksi minimum, MFA admin dan audit perubahan peran.
- Log audit append-only untuk perubahan role, akses dokumen, status booking, tagihan/pembayaran, moderasi dan tindakan admin. Tidak memuat isi sensitif.
- Backup terenkripsi, uji restore, prosedur respons insiden dan pencabutan sesi/kredensial.

## 6. Risiko penting dan kontrol

- **Kebocoran lintas tenant/IDOR:** authorization per objek, tenant scope wajib, uji negatif lintas organisasi.
- **Dokumen identitas terekspos:** enkripsi aplikasi, disk privat di luar web root, endpoint unduh berotorisasi, minimisasi, audit, dan retensi/penghapusan.
- **Booking ganda:** transaksi atomik/constraint unit dan uji konkurensi.
- **Penyalahgunaan admin/staf:** scope akses, MFA, audit, tinjau anggota berkala. Hanya pemilik/pengelola berwenang dapat meninjau dokumen identitas; persetujuan syarat dan konfirmasi penerimaan pembayaran harus mencatat aktor/waktu.
- **Listing palsu/penipuan:** verifikasi email, laporan, moderasi, jejak perubahan; verifikasi pemilik dan mekanisme sengketa perlu kebijakan produk.
- **Listing diterbitkan sebelum review atau setelah perubahan:** backend hanya mengembalikan status `Approved` ke marketplace; perubahan material properti, unit atau foto me-reset listing ke draft untuk diajukan ulang. Route foto publik juga memeriksa status listing dan relasi foto.
- **Pencatatan pembayaran palsu:** pembayaran manual ditandai sebagai catatan pengelola, aktor/waktu/bukti tercatat, koreksi melalui reversal/audit bukan penghapusan diam-diam.
- **Perubahan syarat setelah persetujuan:** simpan versi/snapshot ringkasan dan kontrak; perubahan material membatalkan persetujuan terdahulu dan meminta persetujuan ulang kedua pihak.
- **Eksposur alamat properti:** alamat lengkap memang ditampilkan publik berdasarkan keputusan produk; batasi field alamat ke listing yang dipublikasikan dan jangan mengekspos data kontak/catatan internal. Nonaktifkan alamat publik saat listing diturunkan.
- **Salah konfigurasi shared hosting:** web root hanya ke `public/`; `.env`, source, backup dan dokumen privat tidak berada di direktori publik; gunakan isolasi akun provider, HTTPS, permission file minimum, backup terpisah dan uji akses langsung ke path file.
- **Perubahan syarat setelah persetujuan:** simpan versi/snapshot ringkasan dan kontrak; perubahan material membatalkan persetujuan terdahulu dan meminta persetujuan ulang kedua pihak.

## 7. Kriteria keamanan sebelum rilis

Uji akses lintas tenant untuk seluruh API; uji role matrix termasuk dokumen privat; uji signed URL kedaluwarsa; validasi unggahan; pemeriksaan dependency/secrets; prosedur backup/restore dan insiden; review kebijakan retensi serta persetujuan privasi.
