# Quote Bill To Identity V1

## Ringkasan

Fitur ini membuat identitas `Bill To` quotation fleksibel tanpa melepaskan relasi internal ke Contact Person.

- `Contact only`: PDF menampilkan nama orang yang memesan.
- `Company only`: PDF hanya menampilkan nama perusahaan.
- `Company + Contact`: PDF menampilkan perusahaan lalu nama contact pada baris berikutnya.
- Dropdown Bill To menampilkan `Nama Contact — Nama Company` dan dapat dicari memakai keduanya.
- Nama Bill To disimpan sebagai snapshot saat quotation disimpan agar PDF lama tidak ikut berubah ketika master Contact atau Company diedit.
- Alur Create memiliki pengamanan persistensi agar snapshot Bill To langsung tersimpan pada Quote baru.
- Nama penanda tangan client dan company dapat disesuaikan sebelum quotation disimpan.
- Quotation lama tetap dapat dicetak melalui fallback ke relasi Contact/Company yang sudah ada.
- Quotation yang sudah menjadi Invoice tetap read-only untuk nilai dan item, tetapi identitas Bill To dapat dikoreksi lewat tombol `Save Bill To`.
- Invoice baru menyimpan snapshot mode/nama Bill To dari Quote saat dibuat.
- Invoice lama yang belum memiliki snapshot otomatis membaca snapshot Quote terkait ketika ditampilkan atau dicetak.

## Instalasi lokal

```bash
php artisan migrate
php artisan optimize:clear
php artisan test --compact tests/Unit/QuoteBillToIdentityTest.php
php artisan test --compact tests/Unit/InvoiceBillToIdentityTest.php
php artisan test --compact tests/Unit/QuoteTermsAndConditionsPdfTest.php
php artisan serve
```

Uji tiga format pada halaman Create/Edit Quote, simpan quotation, lalu unduh PDF dan periksa bagian `Bill To` serta tanda tangan di halaman terakhir. Buat Invoice dari Quote tersebut dan pastikan halaman detail serta PDF Invoice memakai susunan Bill To yang sama.

## Deployment VPS

Setelah perubahan sudah digabung ke branch deployment:

```bash
cd /var/www/captureit-crm
git pull --ff-only origin main
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Migration pertama menambah lima kolom nullable/default pada tabel `quotes`. Migration kedua menambah tiga kolom snapshot nullable pada tabel `invoices`. Data transaksi lama tidak dihapus.
