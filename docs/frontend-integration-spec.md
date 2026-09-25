# Frontend & Integration Specification — Rentara

## 1. Aplikasi dan navigasi

Web responsif, mobile-first, Bahasa Indonesia dan Inggris. Bagian publik: beranda, hasil pencarian, detail listing, masuk/daftar. Bagian terautentikasi:

- **Pencari:** pengajuan saya, detail/status pengajuan, persetujuan syarat dua pihak, profil dan verifikasi.
- **Penyewa:** ringkasan sewa, tagihan/pembayaran, keluhan, profil.
- **Pengelola:** dashboard, organisasi/anggota, properti/unit, listing, pengajuan, penghuni/masa sewa, tagihan, keluhan, laporan.
- **Admin:** moderasi dan laporan dengan akses istimewa yang terkontrol.

## 2. Prinsip UX

- Listing publik menampilkan harga/periode, alamat lengkap, tipe, fasilitas, ketersediaan, syarat inti, foto dan waktu pembaruan. Alamat berasal dari field listing publik yang disetujui, bukan catatan operasional internal.
- Filter pencarian mudah dipakai pada layar kecil; filter terpilih dan jumlah hasil terlihat.
- Formulir panjang bertahap, simpan draft bila memungkinkan, validasi inline, label jelas dan pesan error yang dapat ditindaklanjuti.
- Setiap status pengajuan/booking memiliki label, penjelasan, langkah selanjutnya dan riwayat.
- Aksi sensitif (konfirmasi booking, mengubah pembayaran, menghapus anggota) meminta konfirmasi dan menjelaskan dampak.
- Loading/empty/error state konsisten; skeleton hanya untuk konten yang sesuai. Tampilkan status unggah dan kegagalan retry.
- WCAG 2.1 AA sebagai sasaran: keyboard, fokus, kontras, heading, alt text, form labels dan pengumuman status ke screen reader.

## 3. Kontrak frontend/API

- REST JSON `/api/v1`, kontrak OpenAPI sebagai sumber tipe klien.
- Semua endpoint privat membawa sesi/token sesuai strategi autentikasi; UI menyembunyikan aksi tanpa izin tetapi API tetap sumber otoritas.
- Format error konsisten: `code`, pesan aman untuk pengguna, detail field validation, `requestId`; jangan kirim stack trace.
- Pagination, sorting dan filter terstruktur; server menentukan field publik dan akses.
- Status domain menggunakan enum eksplisit: `Submitted`, `Under Review`, `Info Requested`, `Rejected`, `Approved`, `Verified` (kedua pihak menyetujui syarat; tampilkan tenggat pembayaran), `Payment Pending`, `Completed` (pemilik/pengelola mengonfirmasi pembayaran diterima), dan `Expired` (tenggat lewat; slot dibuka kembali). Frontend tidak mengasumsikan transisi tanpa respons server.
- Perubahan mutasi menangani retry/idempotensi dan memperbarui cache/query terkait setelah respons sukses.

## 4. Halaman/kapabilitas utama

### Marketplace

`GET /listings` dengan query area, tipe, harga, fasilitas, ketersediaan, pagination. `GET /listings/{slug}` hanya mengembalikan listing yang disetujui admin dan published. Listing pengelola memiliki status draft, pending review, approved/published, rejected, atau paused. `POST /listings/{slug}/applications` memerlukan sesi, email verified, unit listing tersebut yang tersedia, tanggal/durasi, dan persetujuan privasi versi tertentu. Setelah pengajuan dibuat, pencari mengunggah jenis dokumen yang diminta properti melalui `POST /my-applications/{id}/identity-documents`; status upload/review terlihat hanya oleh pemilik pengajuan dan pengelola terkait.

Pengelola berwenang meninjau dokumen dan menyetujui pengajuan; jenis dokumen yang diminta dikonfigurasi per properti. Tampilkan ringkasan syarat terstruktur dan kontrak privat untuk ditinjau; kedua pihak menyetujuinya. Status `Verified` hanya tampil setelah kedua persetujuan tercatat, bersama waktu tenggat. Masa berlaku mengambil default organisasi dengan override opsional per properti/booking; hanya pemilik/manager dapat mengubah pengaturannya. Pembayaran dilakukan di luar platform. UI menyediakan aksi bagi pemilik/pengelola untuk mengonfirmasi penerimaan, yang mengubah booking menjadi `Completed` setelah server berhasil. Bila tenggat lewat, UI menampilkan `Expired` dan slot tidak lagi ditahan.

Dokumen identitas JPEG/PNG/PDF diunggah satu jenis per pengajuan. Pemohon dapat mengganti dokumen yang ditolak; pemilik/manager dapat mengunduhnya sebagai attachment privat dan mencatat diterima/ditolak beserta catatan. Tidak ada URL publik atau preview dokumen identitas.

### Pengelolaan

CRUD properti/unit, upload media, publish/pause, roster penghuni, pengajuan dan transisi status. Hak aksi berasal dari izin API dan scope terpilih; selector organisasi/properti tidak boleh mencampur data.

### Portal penyewa

Tampilkan deposit dan tagihan sewa bulanan yang dibuat otomatis pada tanggal masuk tenancy (tanggal 29–31 disesuaikan ke hari terakhir bulan), serta tagihan satu kali dengan status pembayaran manual; jangan menyebutnya pembayaran terverifikasi gateway. Pengelola dapat mencatat penerimaan dan pengembalian deposit. Form pelaporan pembayaran menampilkan unggah bukti bila diwajibkan oleh kebijakan organisasi/properti; bila opsional, penyewa dapat melewatinya. Pengajuan keluhan menyediakan kategori, uraian, dan lampiran terbatas.

## 5. Integrasi

- **Email:** verifikasi, undangan, perubahan status, tagihan dan keluhan; template bilingual, link bertanda tangan/token aman, unsubscribe untuk email non-transaksional.
- **In-app notifications:** daftar dan status telah dibaca, pagination; hindari data sensitif di teks preview.
- **Object storage:** foto publik melalui CDN setelah moderasi; dokumen identitas hanya melalui alur privat dan signed URL berumur pendek.
- **Peta/geocoding:** opsional; gunakan provider abstraction. Alamat lengkap listing ditampilkan publik; koordinat/hasil geocoding perlu ditinjau agar tidak mengekspos data selain alamat listing yang disetujui.
- **Pembayaran:** tidak ada gateway di MVP. Adapter tahap selanjutnya harus menangani webhook signature, idempotensi, rekonsiliasi, refund dan ledger.
- **Analytics/error tracking:** event minimisasi data; jangan kirim PII, alamat lengkap, token, atau dokumen.

## 6. I18n dan format lokal

Semua teks UI melalui katalog pesan; dukung `id` dan `en`. Tanggal ditampilkan sesuai locale, disimpan sebagai timestamp/timezone yang jelas. Mata uang IDR diformat locale-aware; jumlah disimpan sebagai integer unit terkecil. Alamat dan input nomor telepon mengikuti validasi Indonesia namun tidak mengunci dukungan negara di model data.

## 7. Kriteria penerimaan UI lintas alur

- Pengguna dapat mencari listing dan melihat detail tanpa login.
- Pencari login, verifikasi email, unggah identitas secara privat, mengajukan dan memantau status.
- Pengelola melihat hanya pengajuan dan dokumen dalam scope yang diizinkan, meninjau identitas, lalu memprosesnya.
- Kedua pihak menyetujui syarat untuk mencapai `Verified`; pemilik/pengelola mencatat pembayaran luar platform diterima untuk mencapai `Completed`. Tenggat yang dapat dikonfigurasi tanpa konfirmasi menyebabkan `Expired` dan slot dibuka kembali.
- Penyewa melihat hanya tagihan/keluhannya; pengelola mencatat pembayaran dengan aktor dan waktu.
- Antarmuka berfungsi di viewport ponsel/desktop dan tersedia dalam dua bahasa.
- Status kosong, jaringan gagal, validasi, izin ditolak, kedaluwarsa sesi dan unggah gagal memiliki pengalaman yang dapat dipahami.
