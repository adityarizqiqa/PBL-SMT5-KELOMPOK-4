# SmartPark — database backend

Folder ini berisi bootstrap Laravel 12, konfigurasi environment/database, dan
lima migration schema SmartPark. Migration ditulis menggunakan anonymous
migration dan Schema Builder Laravel, dengan target MySQL **8.0.16+** dan InnoDB.

## Dependency PHP

Prasyarat: PHP **8.2+** dan Composer. Jalankan dari folder `backend`:

```sh
composer install
```

Dependency `laravel/framework` menyediakan class `Illuminate`, termasuk
`Illuminate\Database\Migrations\Migration`, Schema Builder, dan facades.
Composer membuat `vendor/autoload.php`; folder `vendor` tidak dimasukkan ke Git.
Simpan `composer.lock` di repository agar versi dependency konsisten.

Jika editor masih menampilkan `Undefined type` setelah instalasi selesai,
reload workspace agar language server PHP mengindeks ulang `backend/vendor`.

Bootstrap aplikasi sudah tersedia melalui `artisan`, `bootstrap/app.php`,
konfigurasi di `config`, dan direktori runtime di `storage`/`bootstrap/cache`.
Perintah Artisan sudah dapat dijalankan dari folder `backend`.

## Environment dan setup database

File `.env` lokal diabaikan Git. Template untuk anggota tim tersedia dalam
`.env.example`. Pada setup baru, jalankan dari folder `backend` (PowerShell):

```powershell
composer install
Copy-Item -LiteralPath ".env.example" -Destination ".env"
php artisan key:generate
```

Sesuaikan koneksi di `.env` dengan MySQL masing-masing. Konfigurasi lokal yang
sudah diverifikasi menggunakan Laragon:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartpark
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_TIMEZONE="+00:00"
```

`APP_KEY` dihasilkan untuk setiap instalasi dan tidak disimpan di template.
Aplikasi dan koneksi database menggunakan UTC. Cache/session memakai file,
queue memakai sync, dan gambar disimpan di `storage/app/private` melalui disk
local. Konfigurasi ini tidak membutuhkan tabel cache, sessions, atau jobs.

Buat database sebelum migration pada instalasi baru:

```powershell
mysql --host=127.0.0.1 --port=3306 --user=root --execute="CREATE DATABASE IF NOT EXISTS smartpark CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --pretend
php artisan migrate
php artisan migrate:status
```

Jika konfigurasi sebelumnya pernah di-cache, jalankan `php artisan config:clear`
setelah memperbarui `.env`.

## Tabel dan urutan migration

1. `users`: akun ADMIN dan PETUGAS, termasuk admin pembuat akun.
2. `parking_locations`: master lokasi yang dapat dinonaktifkan.
3. `operational_sessions`: petugas, lokasi, shift, serta waktu mulai/selesai.
4. `parking_sessions`: lifecycle parkir dan snapshot checkout berhasil.
5. `checkout_attempts`: setiap percobaan checkout terhadap sesi parkir.

Semua primary key dan foreign key menggunakan BIGINT UNSIGNED. Semua foreign
key memakai ON DELETE RESTRICT dan ON UPDATE RESTRICT. Penonaktifan master
menggunakan `is_active`; identitas historis tidak boleh dihapus atau diganti.

## Constraint database

- Username dan kode lokasi unique, dengan collation `utf8mb4_unicode_ci`
  (perbandingan tidak membedakan huruf besar/kecil).
- `active_nim` dan `active_plate` adalah kolom STORED generated dengan UNIQUE
  index. Nilainya hanya terisi ketika status PARKED; setelah checkout berhasil
  nilainya NULL. Constraint berlaku global di semua lokasi dan mencegah dua
  check-in aktif bersamaan, tetapi mengizinkan riwayat NIM/plat berulang.
- Plat check-in, checkout, dan attempt wajib berbentuk kanonis: huruf besar,
  tanpa spasi biasa dan tanda hubung. Aplikasi menormalisasi sebelum menyimpan;
  database menolak nilai yang belum kanonis. Jangan menyamakan O/0 atau B/8.
- NIM berupa string nonempty tanpa spasi di awal/akhir, sehingga nol di awal
  tidak hilang. Validasi format NIM dan whitespace lain dilakukan aplikasi.
- `successful_parking_session_id` adalah kolom STORED generated dengan UNIQUE
  index: satu sesi hanya dapat memiliki satu attempt SESUAI/VERIFIKASI_MANUAL,
  tetapi dapat memiliki banyak attempt TIDAK_SESUAI.
- Status parking session hanya PARKED, SESUAI, atau VERIFIKASI_MANUAL.
- Ketika PARKED, seluruh data checkout wajib NULL. Ketika selesai, plat,
  waktu, dan operational session checkout wajib terisi.
- Operational session aktif harus memiliki `ended_at = NULL`; sesi nonaktif
  harus memiliki `ended_at` terisi. Penutupan mengubah keduanya dalam satu update.
- Waktu selesai tidak boleh mendahului waktu mulai pada row yang sama.
- Semua confidence/similarity memakai skala 0–100, atau NULL jika tidak tersedia.
  Confidence Python dalam skala 0–1 harus dikonversi sebelum disimpan.
- VERIFIKASI_MANUAL berarti sudah disetujui, bukan pending; `note` wajib terisi
  dan tidak hanya berisi spasi biasa. Validasi seluruh whitespace dilakukan aplikasi.
- `created_at` dan `updated_at` NOT NULL tanpa default; aplikasi mengisinya.
  Gunakan UTC secara konsisten untuk DATETIME, timestamp, dan zona waktu koneksi.
- Kolom generated tidak boleh dikirim sebagai nilai insert/update oleh aplikasi.

## Index

Index aktif NIM/plat digunakan langsung untuk pencarian sesi PARKED melalui
`active_nim` atau `active_plate`. Daftar kendaraan PARKED diurutkan melalui
`(status, checkin_time)`; riwayat lintas status memakai `checkin_time`.
Riwayat attempt per sesi memakai `(parking_session_id, attempted_at)`.

Index FK tersedia secara eksplisit atau melalui leftmost prefix composite
index. Index boolean lokasi dan index result attempt tidak ditambahkan karena
selectivity rendah; tambahkan berdasarkan query laporan nyata. Index
`(status, nim)` dan `(status, checkin_plate)` tidak digandakan karena pencarian
aktif menggunakan generated unique key.

## Aturan lintas tabel untuk tahap aplikasi

FK/CHECK tidak menggantikan otorisasi dan transaksi. Pada implementasi layanan:

- Hash password menggunakan fasilitas Laravel; jangan simpan plaintext.
- Hanya ADMIN yang boleh mengelola akun/lokasi. Admin pertama boleh memiliki
  `created_by = NULL`; akun berikutnya dibuat oleh ADMIN yang tercatat.
- Check-in/checkout hanya menggunakan operational session milik PETUGAS yang
  sedang melakukan aksi, dengan user dan lokasi aktif serta sesi belum berakhir.
  Penonaktifan user juga memblokir aksi dari token/sesi login yang masih ada.
- Waktu transaksi harus berada dalam rentang sesi kerja terkait.
- Checkout mengunci row parking session, memastikan masih PARKED, menyimpan
  attempt, dan memperbarui snapshot/status dalam **satu transaksi**.
- Attempt gagal hanya disimpan di `checkout_attempts`; jangan mengisi snapshot
  checkout atau menutup parking session.
- Attempt berhasil menjadi sumber snapshot checkout: scanned_plate →
  checkout_plate, original_image → checkout_original_image, plate_crop →
  checkout_plate_crop, ocr_confidence → checkout_ocr_confidence, similarity_score
  → similarity_score, attempted_at → checkout_time, operational_session_id →
  checkout_operational_session_id, result → status.
- Constraint satu attempt berhasil tidak otomatis menjamin adanya attempt
  untuk sesi berstatus selesai; konsistensi dua tabel dijaga transaksi aplikasi.
- Jangan membuat attempt baru untuk sesi yang sudah selesai. Perlakukan attempt
  final sebagai catatan audit immutable dalam operasional normal.
- Setelah dipakai transaksi, jangan ubah petugas/lokasi/shift sesi kerja.
  Penutupan sesi kerja tidak menutup kendaraan yang masih PARKED.
- Original/crop disimpan di private storage; database hanya menyimpan path.
  Gunakan path unik per attempt, misalnya
  `plates/checkout/session_10/attempt_21_original.jpg`. Snapshot checkout dapat
  menunjuk file attempt berhasil yang sama tanpa menyalin gambar.
- Jika OCR tidak menemukan exact-match, tentukan parking session target melalui
  kandidat similarity atau fallback pencarian sebelum menyimpan attempt.
  Scan tanpa sesi target tidak dicatat oleh tabel ini.

## Keputusan operasional yang masih terbuka

- Maksimal satu operational session aktif per petugas belum ditegakkan karena
  belum ditetapkan sebagai business rule. Jika disetujui, tambahkan constraint
  unique generated active user dalam migration lanjutan.
- Checkout lintas lokasi belum dibatasi. Kebijakan harus ditentukan sebelum
  membuat layanan checkout.
- Prosedur menonaktifkan lokasi yang memiliki sesi kerja/kendaraan aktif harus
  ditentukan agar kendaraan tetap dapat checkout.
- Nama user/lokasi pada history mengikuti nilai master terbaru. Schema ini
  tidak menyimpan versi nama atau audit seluruh perubahan master.

## Migration Laravel

Gunakan lima migration yang tersedia di `database/migrations`; jangan menambahkan
migration users bawaan Laravel yang kembali membuat tabel `users`.

Laravel juga menggunakan tabel internal `migrations` untuk melacak migration;
itu metadata framework, bukan tabel bisnis tambahan.

Kelima migration sudah berhasil dijalankan pada database lokal `smartpark`
menggunakan MySQL 8.0.30. Status seluruh migration adalah Ran, batch 1.
Rollback mengikuti urutan terbalik: checkout_attempts, parking_sessions,
operational_sessions, parking_locations, users.

## Verifikasi yang sudah dilakukan

- Syntax check lima migration menggunakan PHP 8.3.
- `composer validate --strict` dan `composer check-platform-reqs` berhasil.
- Autoload Migration, Blueprint, DB, dan Schema berhasil menggunakan
  `backend/vendor/autoload.php` dari dependency yang terpasang di backend.
- Kompilasi SQL `up()` dan `down()` menggunakan Schema Builder Laravel 12 dalam
  mode pretend, tanpa koneksi database, melalui script verifikasi sementara
  di luar repository dengan dependency lokal backend.
- Pemeriksaan hasil kompilasi untuk generated unique key, enum status, CHECK
  lifecycle/catatan manual, timestamp NOT NULL, dan semua FK RESTRICT.
- Artisan berhasil boot, APP_KEY berhasil dihasilkan, dan kelima migration
  berhasil dieksekusi menggunakan konfigurasi `.env` lokal.
- Pengujian langsung pada MySQL 8.0.30: 20 CHECK enforced, 8 FK RESTRICT,
  duplicate active NIM/plate, format plat, batas skor, konsistensi lifecycle,
  catatan manual wajib, maksimal satu attempt berhasil, pemakaian ulang
  NIM/plat setelah checkout, dan pembatasan delete history berhasil diverifikasi.
- Seluruh data uji di-rollback; tidak ada akun atau transaksi contoh yang
  tersimpan. Tabel bisnis masih kosong setelah verifikasi.
