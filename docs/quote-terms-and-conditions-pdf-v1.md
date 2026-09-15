# Quote Terms & Conditions PDF V1

Fitur ini menambahkan empat halaman Terms & Conditions setelah isi quotation
pada dokumen yang dihasilkan oleh tombol Print Quote.

## Perilaku

- Halaman quotation yang lama tetap berada di bagian awal dokumen.
- Terms & Conditions selalu dimulai pada halaman baru.
- Lampiran terdiri dari empat section halaman dan memuat 18 pasal.
- Blok persetujuan perusahaan tetap menampilkan Rudy Tinambunan dan
  PT Varbel Anvaya Bersaudara.
- Nama client diambil dari `quote.person.name`.
- Nama perusahaan client diambil dari `quote.person.organization.name`.
- Jika nama client atau organisasi tidak tersedia, PDF menampilkan tanda `-`.
- Footer mencantumkan keanggotaan Asosiasi Rental Indonesia dan nomor anggota
  JK-0006-VII-2026.

Tidak ada perubahan schema database, migration, dependency Composer, atau aset
JavaScript/CSS. Patch ini tidak memerlukan `php artisan migrate` maupun build
npm.

## Pemeriksaan di VPS production

Pemeriksaan berikut tidak memerlukan dependency development:

    php -l tools/check_quote_terms_and_conditions_pdf_v1.php
    php tools/check_quote_terms_and_conditions_pdf_v1.php
    php artisan optimize:clear

Setelah checker lulus, buka satu quote yang memiliki organization dan satu quote
perorangan, lalu unduh Print Quote. Pastikan:

1. isi quotation lama tetap lengkap;
2. Terms & Conditions dimulai setelah halaman quotation;
3. seluruh 18 pasal tampil dalam empat halaman lampiran;
4. nama client dan perusahaan pada halaman persetujuan sesuai quote;
5. quote tanpa organization menampilkan `-` pada nama perusahaan;
6. tidak ada teks terpotong, bertumpuk, atau keluar dari margin.

Jika PHP-FPM memakai OPcache agresif, jalankan setelah verifikasi:

    php artisan optimize
    sudo systemctl reload php8.3-fpm

## Pemeriksaan di development

Jika dependency development/Pest tersedia, jalankan:

    ./vendor/bin/pest tests/Unit/QuoteTermsAndConditionsPdfTest.php
