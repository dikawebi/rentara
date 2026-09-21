# Rentara — Brand Guideline v1.0

> Working brand. Verifikasi domain, trademark, dan social handle sebelum peluncuran publik.

## Brand Foundation

**Rentara** adalah platform untuk mengelola dan menemukan properti sewa. Nama ini menggabungkan makna rental dengan ritme yang dekat bagi pasar Indonesia, sehingga sesuai untuk kost, kontrakan, apartemen, rumah sewa, kios, dan ruko.

### Tagline

> **Kelola properti. Temukan hunian.**

Tagline ini merangkum dua sisi Rentara: pengelolaan properti untuk pemilik/pengelola dan penemuan hunian untuk calon penyewa.

### Positioning

Rentara membantu pemilik dan pengelola mengurus unit, penyewa, kontrak, tagihan, pembayaran, dan perawatan dalam satu platform; kemudian mempertemukan properti tersedia dengan calon penyewa melalui marketplace.

### Personality

- Terpercaya
- Rapi
- Praktis
- Hangat
- Modern

## Logo

Assets:

- `rentara-logo-primary.svg` untuk header, landing page, dokumen, dan email.
- `rentara-logo-mark.svg` untuk favicon, PWA, avatar, dan mobile header.

Mark membentuk hunian dengan garis ruang di bagian dalam. Bentuk diagonal memberi rasa pergerakan: dari unit kosong menuju hubungan sewa yang tertata.

### Usage rules

- Gunakan logo primary minimal 120px lebar di digital.
- Gunakan mark minimal 24px di digital.
- Beri clear space minimal setara lebar garis dalam mark di setiap sisi.
- Jangan mengubah rasio, warna, atau orientasi logo.
- Gunakan reversed logo putih bila berada di background navy.

## Color System

| Token | Hex | Penggunaan |
|---|---|---|
| Rentara Navy | `#16324F` | Logo, sidebar, heading utama |
| Rentara Blue | `#2563EB` | CTA utama, link, active state |
| Rentara Teal | `#0F766E` | Accent, konfirmasi, okupansi |
| Cloud | `#F8FAFC` | Background aplikasi |
| Ink | `#172033` | Teks utama |
| Slate | `#64748B` | Teks sekunder |
| Border | `#E2E8F0` | Divider dan input border |
| Success | `#15803D` | Lunas, selesai, aktif |
| Warning | `#B45309` | Menunggu, akan jatuh tempo |
| Danger | `#B91C1C` | Tunggakan, penolakan, destructive action |

Gradient logo:

```css
linear-gradient(135deg, #2563EB 0%, #0F766E 100%)
```

## Typography

Primary font untuk wordmark dan heading utama:

```css
font-family: 'Manrope', 'Plus Jakarta Sans', Inter, system-ui, sans-serif;
```

Gunakan **Manrope 800** untuk wordmark lowercase `rentara`, dengan letter spacing sedikit rapat. Gunakan Manrope 600–700 untuk heading. Untuk isi, tabel, dan form, gunakan Inter atau Plus Jakarta Sans 400–600 agar tetap mudah dibaca.

| Style | Size | Weight | Usage |
|---|---:|---:|---|
| H1 | 28px | 700 | Judul halaman |
| H2 | 22px | 700 | Section utama |
| H3 | 16px | 600 | Card/panel heading |
| Body | 14px | 400 | Isi dan table text |
| Label | 12px | 600 | Form label/badge |
| Caption | 12px | 400 | Metadata |

## UI Rules

- Primary button: Rentara Blue, teks putih, radius 8px, tinggi minimal 40px.
- Secondary button: surface putih, border abu muda, teks navy.
- Cards: surface putih, border `#E2E8F0`, radius 14px, padding 16–20px.
- Status selalu memakai teks dan warna; jangan mengandalkan warna saja.
- Dashboard desktop memakai sidebar navy; tenant portal fokus mobile-first.
- Semua UI copy menggunakan Bahasa Indonesia yang jelas dan membantu.

Contoh:

- “Tagihan berhasil diterbitkan.”
- “Bukti pembayaran menunggu verifikasi.”
- “Laporan perbaikan diterima.”

## CSS Tokens

```css
:root {
  --brand-navy: #16324F;
  --brand-blue: #2563EB;
  --brand-teal: #0F766E;
  --surface: #FFFFFF;
  --background: #F8FAFC;
  --foreground: #172033;
  --muted-foreground: #64748B;
  --border: #E2E8F0;
  --success: #15803D;
  --warning: #B45309;
  --danger: #B91C1C;
}
```

## Implementation Note

Nama brand harus berasal dari konfigurasi aplikasi, bukan hardcoded di semua view:

```php
'brand' => [
  'name' => env('APP_BRAND_NAME', 'Rentara'),
  'tagline' => env('APP_BRAND_TAGLINE', 'Kelola properti. Temukan hunian.'),
]
```
