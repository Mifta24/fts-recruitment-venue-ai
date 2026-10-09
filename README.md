# FTS Recruitment Venue AI

Situs interaktif untuk **venue yang disewakan sebagai tempat acara rekrutmen**: job fair, walk-in interview, hari asesmen (CBT), talent briefing, dan campus hiring. Penyelenggara (tim HR, agensi, kampus) menjelajahi venue langkah demi langkah seperti tahapan dalam proses rekrutmen, mengobrol dengan **AI Venue Coordinator**, mengecek tanggal dan harga, lalu mengirim permintaan booking. Tim venue mengelola semuanya lewat panel admin.

Satu aplikasi bisa melayani beberapa venue. Setiap venue punya halaman sendiri di `/{venueSlug}`.

## Fitur

### Sisi penyelenggara
- **Alur rekrutmen sebagai navigasi**: bar di bawah header berisi enam langkah bernomor (01 Beranda, 02 Ruang, 03 Fasilitas, 04 Informasi venue, 05 Booking acara, 06 Tim venue). Langkah yang sudah dilewati ditandai selesai, langkah aktif menyala, dan perpindahan halaman ditandai tirai yang menutup dan membuka. Di ponsel, bar menjadi strip yang bisa digeser.
- **Tipe ruang**: Job Fair Hall, Walk-in Interview Suites, Assessment Lab (CBT), Briefing Auditorium, dan Skyline Boardroom. Tiap tipe punya kapasitas (kandidat dan rekruter), luas, jumlah ruang breakout dan booth rekruter, lantai, susunan tempat duduk, suasana, perlengkapan, dan tarif harian.
- **Booking per hari**: acara mencakup setiap hari dari tanggal mulai sampai tanggal selesai (inklusif); acara satu hari berarti mulai dan selesai di tanggal yang sama. Permintaan online sampai 31 hari.
- **Paket diskon**: diskon multi-hari (mulai 3 hari) dan kampanye rekrutmen (mulai 10 hari) per venue, diterapkan di satu tempat sehingga wizard, AI Coordinator, dan total booking selalu sama. Kru teknis (AV, proktor CBT, registrasi) bisa ditambahkan per hari.
- **Tiga bahasa**: Indonesia (`id`), Inggris (`en`), dan Jepang (`ja`), dipilih lewat parameter `?lang=`.
- **Chat dengan AI Venue Coordinator**
  - Tahu di langkah mana penyelenggara berada (ruang atau fasilitas yang sedang dilihat, form booking yang sedang diisi).
  - Menjawab dalam bahasa yang ditulis pengguna.
  - Menampilkan kartu ruang (tata letak, kapasitas, total dengan diskon) langsung di dalam chat.
- **Permintaan booking**: wizard lima langkah (tanggal, peserta, ruang, data penyelenggara termasuk perusahaan dan jenis acara, ringkasan). Hari yang sudah penuh dicoret di kalender dan rentang tidak bisa melewatinya (data dari `GET /{venueSlug}/reservation/availability`). Hasilnya nomor referensi dan tautan WhatsApp, telepon, atau email ke tim venue.

### Desain
Tema "Talent Hall": indigo pekat, kuning marigold, dan aksen koral di atas kertas berwarna lavender. Font Plus Jakarta Sans dan DM Mono. Kartu membulat, tombol berbentuk pil, dan foto venue (job fair, auditorium, lab komputer, ruang wawancara) dari Unsplash. Panel admin memakai palet yang sama.

### Sisi admin (`/admin`)
- Dashboard ringkasan: permintaan booking yang menunggu lebih dari 24 jam, handover terbuka, acara yang mulai dalam 7 hari, dan utilisasi 30 hari ke depan. Panel admin bisa dipakai dari ponsel.
- CRUD **tipe ruang** (termasuk susunan tempat duduk, add-on kru teknis, dan foto).
- CRUD **knowledge items**, yaitu basis pengetahuan yang menjadi sumber jawaban coordinator (aturan venue, fasilitas, katering, transportasi, FAQ).
- **Inventori dan harga** per tipe ruang (Spaces → Inventory): buka rentang tanggal, atur jumlah ruang yang disewakan dan harga per hari. Jumlah tidak bisa diturunkan di bawah yang sudah dibooking. Tampilan bulanan berupa kalender.
- **Pengaturan venue** (khusus `owner`): kontak, jam buka dan tutup, bahasa dan zona waktu, diskon paket, dan status halaman publik (draft atau published).
- Daftar **booking** dengan penyelenggara, perusahaan, jenis acara, dan ubah statusnya. Alurnya `pending` → `confirmed` atau `cancelled`, dan `confirmed` → `cancelled`. `cancelled` bersifat final (ruangnya dikembalikan ke inventori). Tipe ruang yang masih punya booking aktif tidak bisa dihapus.
- **Handover**: percakapan yang diserahkan coordinator ke staf. Staf bisa membalas langsung dan menandainya selesai.
- Peran pengguna per venue: `owner` dan `staff`.

### Notifikasi email
- **Ke staf** (semua anggota aktif venue): permintaan booking baru dan handover baru.
- **Ke penyelenggara** yang memberi alamat email: tanda terima permintaan, lalu pemberitahuan saat booking dikonfirmasi atau dibatalkan, dalam bahasa yang dipakai (`id`, `en`, `ja`). Yang memilih WhatsApp atau telepon dihubungi langsung oleh staf.
- Email dikirim lewat queue, jadi worker harus jalan (`composer dev` sudah menyertakannya; di produksi jalankan `php artisan queue:work`). Bawaannya `MAIL_MAILER=log`.

## Cara kerja AI Venue Coordinator

Kode ada di [app/Services/Coordinator/](app/Services/Coordinator).

- **[CoordinatorService](app/Services/Coordinator/CoordinatorService.php)** menjalankan satu giliran percakapan lewat endpoint chat yang kompatibel dengan OpenAI (LM Studio atau Ollama) dengan loop tool-calling (maksimal 6 putaran). Seluruh giliran dibatasi 85 detik supaya muat dalam batas 100 detik Cloudflare.
- **[VenueCoordinatorTools](app/Services/Coordinator/VenueCoordinatorTools.php)** berisi alat yang bisa dipanggil model. Semua fakta venue, ruang, harga, dan ketersediaan harus lewat sini.

  | Tool | Fungsi |
  |---|---|
  | `search_knowledge` | Mencari di knowledge base venue |
  | `search_spaces` | Mencari tipe ruang sesuai tanggal, jumlah kandidat dan rekruter, dan ruang breakout, lengkap dengan ketersediaan dan total harga setelah diskon |
  | `get_space_detail` | Detail satu tipe ruang, termasuk susunan tempat duduk, lantai, dan paket diskon |
  | `check_availability` | Cek ketersediaan dan total harga (dengan diskon dan kru teknis) untuk tanggal tertentu |
  | `create_booking_request` | Membuat permintaan booking |
  | `request_human_handover` | Menyerahkan percakapan ke staf |

- **[ContentGuard](app/Services/Coordinator/ContentGuard.php)** adalah pengaman deterministik: pesan kasar, seksual, atau ilegal dijawab dengan balasan baku tanpa sampai ke model.
- **Harga tanpa sumber ditolak**: balasan yang memuat angka harga yang tidak berasal dari tool akan ditantang.
- **Handover**: alasan yang didukung adalah `special_request`, `complaint`, `custom_setup`, `negotiated_rate`, `unusual_cancellation`, `payment_issue`, dan `low_confidence`.
- **Cakupan**: coordinator hanya membahas venue ini. Ia tidak menyaring kandidat, menulis iklan lowongan, atau memberi saran HR, hukum, dan gaji.

## Teknologi

- PHP 8.3+ (dikembangkan di 8.4), Laravel 13
- SQLite sebagai database bawaan. Session, cache, dan queue memakai driver `database`
- Vite 8 dan Tailwind CSS 4, dengan JavaScript vanilla di `resources/js/`
- LLM lokal lewat HTTP (endpoint kompatibel OpenAI)
- PHPUnit 12 untuk tes, Laravel Pint untuk format kode

## Memulai

### Prasyarat
PHP 8.3+ dengan ekstensi SQLite, Composer, dan Node.js 20.19+ (atau 22.12+). Untuk chat AI, perlu server LLM yang kompatibel dengan OpenAI.

### Instalasi

```bash
composer setup
```

Perintah itu menjalankan `composer install`, menyalin `.env.example` ke `.env`, membuat `APP_KEY`, migrasi, `npm install`, dan `npm run build`.

Isi data demo (hanya jalan di environment `local` dan `testing`):

```bash
php artisan db:seed
```

Seeder membuat venue demo **FTS Recruitment Venue** (slug `fts-recruitment-venue`) di SCBD, Jakarta Selatan: lima tipe ruang, inventori 180 hari, diskon multi-hari 10% dan kampanye 20%, serta knowledge base (jam operasional, paket, pembatalan, registrasi, Wi-Fi, CBT, katering, transportasi, FAQ).

### Menjalankan

```bash
composer dev
```

| Halaman | URL |
|---|---|
| Halaman pembuka | `http://localhost:8000/` |
| Venue demo | `http://localhost:8000/fts-recruitment-venue` |
| Admin | `http://localhost:8000/admin` |

Akun admin demo (hanya untuk lokal, jangan dipakai di produksi):

```
email    : owner@ftsrecruitment.test
password : password
```

### Konfigurasi LLM

```dotenv
LOCAL_LLM_BASE_URL=   # mis. http://127.0.0.1:1234/v1 (LM Studio) atau http://127.0.0.1:11434/v1 (Ollama)
LOCAL_LLM_API_KEY=    # kosongkan jika server tidak memakai autentikasi
LOCAL_LLM_MODEL=      # nama model yang dimuat di server
```

Model harus mendukung function calling. Tanpa konfigurasi ini, halaman venue tetap jalan tetapi chat coordinator tidak bisa menjawab.

### Mencoba coordinator dari terminal

```bash
php artisan coordinator:chat fts-recruitment-venue --locale=id
```

## Endpoint utama

| Method | Path | Keterangan |
|---|---|---|
| GET | `/{venueSlug}` | Langkah 01, beranda |
| GET | `/{venueSlug}/spaces`, `/spaces/{spaceSlug}` | Langkah 02, daftar dan detail tipe ruang |
| GET | `/{venueSlug}/facilities`, `/facilities/{id}` | Langkah 03, fasilitas |
| GET | `/{venueSlug}/info`, `/reservation`, `/staff` | Informasi venue (04), booking acara (05), tim venue (06) |
| GET | `/{venueSlug}/reservation/availability` | Hari yang masih punya ruang kosong, untuk kalender |
| POST | `/{venueSlug}/reservation/quote` | Hitung harga, termasuk diskon paket |
| POST | `/{venueSlug}/reservation` | Kirim permintaan booking |
| POST | `/{venueSlug}/coordinator/start` | Mulai percakapan |
| POST | `/{venueSlug}/coordinator/message` | Kirim pesan ke coordinator |
| GET | `/{venueSlug}/coordinator/history` | Riwayat percakapan |

Endpoint coordinator dan reservasi dibatasi laju (rate limit), dan login admin dibatasi 5 percobaan per menit.

## Struktur proyek

```
app/
  Console/Commands/       coordinator:chat
  Http/Controllers/       halaman publik, chat, reservasi, dan Admin/
  Models/                 Venue, SpaceType, SpaceInventory, Booking, Conversation, ...
  Services/Coordinator/   CoordinatorService, VenueCoordinatorTools, ContentGuard
  Services/Reservation/   ReservationService, ReservationHandover
database/
  migrations/             skema venue, tipe ruang, inventori, knowledge, percakapan, booking, handover
  seeders/                DemoVenueSeeder
resources/
  js/                     stage, narrator, coordinator, reservation, date-picker, sound
  views/                  halaman publik (venue/), admin (admin/), komponen
fts_recruitment_venue_ai_documentation/   dokumen konsep, alur, UI, AI, skema, dan checklist tes
tests/
  Feature/ dan Unit/
```

## Pengujian

```bash
composer test
```

Atau satu berkas:

```bash
php artisan test --compact tests/Feature/CoordinatorChatTest.php
```

Cakupan tes: akses admin, chat coordinator, tool booking (termasuk diskon paket dan filter ruang breakout), permintaan booking (acara satu hari, multi-hari, kampanye), status booking, notifikasi email, inventori dan pengaturan admin, semua langkah alur rekrutmen, dan `ContentGuard`.

## Gaya kode

```bash
vendor/bin/pint --dirty
```

## Deployment

- Jalankan `npm run build` dan `php artisan migrate --force`.
- Set `APP_ENV=production` dan `APP_DEBUG=false`.
- Seeder demo tidak jalan di produksi. Buat akun owner dan venue Anda sendiri, lalu isi `multiday_discount_percent` dan `campaign_discount_percent` bila ingin memberi diskon paket.
- Pastikan server aplikasi bisa menjangkau `LOCAL_LLM_BASE_URL`.
- Jalankan queue worker dan isi `MAIL_*` dengan SMTP asli.
- Karena ada batas 100 detik dari Cloudflare, jangan menaikkan batas waktu respons coordinator melebihi 85 detik.

## Lisensi

Project ini dibangun di atas [Laravel](https://laravel.com), yang berlisensi [MIT](https://opensource.org/licenses/MIT).
