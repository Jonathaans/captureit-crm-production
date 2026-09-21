# Quote Bill To Identity V1

## Ringkasan

Fitur ini membuat identitas `Bill To` quotation fleksibel tanpa melepaskan relasi internal ke Contact Person.

- `Contact only`: PDF menampilkan nama orang yang memesan.
- `Company only`: PDF hanya menampilkan nama perusahaan.
- `Company + Contact`: PDF menampilkan perusahaan dan `Attn: nama contact`.
- Dropdown Bill To menampilkan `Nama Contact — Nama Company` dan dapat dicari memakai keduanya.
- Nama Bill To disimpan sebagai snapshot saat quotation disimpan agar PDF lama tidak ikut berubah ketika master Contact atau Company diedit.
- Nama penanda tangan client dan company dapat disesuaikan sebelum quotation disimpan.
- Quotation lama tetap dapat dicetak melalui fallback ke relasi Contact/Company yang sudah ada.

## Instalasi lokal

```bash
php artisan migrate
php artisan optimize:clear
php artisan test --compact tests/Unit/QuoteBillToIdentityTest.php
php artisan test --compact tests/Unit/QuoteTermsAndConditionsPdfTest.php
php artisan serve
```

Uji tiga format pada halaman Create/Edit Quote, simpan quotation, lalu unduh PDF dan periksa bagian `Bill To` serta tanda tangan di halaman terakhir.

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

Migration hanya menambah lima kolom nullable/default pada tabel `quotes`; data transaksi lama tidak dihapus.
