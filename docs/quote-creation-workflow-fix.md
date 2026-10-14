# Perbaikan pembuatan Quote dan pemeriksaan alur dokumen

## Penyebab

Form mengizinkan `Valid Until` hari ini, tetapi archive policy sebelumnya membandingkan timestamp tanggal tersebut (pukul 00.00) dengan waktu saat ini. Saat header Quote dibuat lalu item disimpan, item ditolak sebagai arsip kedaluwarsa. Transaksi repository kemudian membatalkan seluruh Quote. Pesan `archive` juga belum ditampilkan pada halaman Create.

## Perubahan

- Quote dan itemnya berlaku sampai akhir tanggal `Valid Until`, mengikuti timezone aplikasi. Penguncian karena kedaluwarsa dimulai pada hari berikutnya.
- Generate Quotation dan Link to Lead menggunakan hari ini apabila tanggal perkiraan closing Lead kosong, tidak valid, atau sudah lewat. Tanggal Lead asli tidak diubah; tanggal closing yang masih akan datang tetap digunakan.
- Tanggal berlaku yang sudah lewat dan daftar item kosong ditolak sebelum penyimpanan. Semua pesan validasi ditampilkan di atas form Create/Edit agar penyebab kegagalan terlihat.
- Penguncian Quote yang sudah dikonversi, invoice final/VOID, dan dokumen operasional final tetap berlaku.

Tidak ada migration baru atau perubahan transaksi lama dalam perbaikan ini.

## Verifikasi otomatis

`tests/Unit/QuoteCreationWorkflowTest.php` memakai request HTTP terautentikasi, repository dan service asli, serta database SQLite sementara. Middleware ACL tetap aktif; hanya pemeriksaan installer dinonaktifkan untuk pengujian.

1. Create Quote serta Generate dari Lead, dengan Valid Until hari ini dan tanggal mendatang.
2. Halaman Generate dari Lead lama, endpoint import data, penyimpanan item, dan relasi Lead–Quote.
3. Batas pukul 23.59.59/00.00 pada timezone Asia/Jakarta dan penguncian Quote setelah konversi.
4. Input tidak valid tidak meninggalkan header, item, atau nilai atribut parsial.
5. Quote Rp10 juta → Proforma DP 30% → penerimaan Rp3 juta → Invoice DP paid → SPK → release → DO.
6. Invoice pelunasan Rp7 juta, dengan SPK yang sama dan tanpa menggandakan DO.
7. Quote → Proforma → persetujuan Admin Sales → Invoice penuh unpaid → SPK → DO, untuk TOP sebelum maupun setelah event.
8. Kuantitas tagihan dua hari tetap menghasilkan satu kamera fisik di DO. Selama masih Proforma, belum ada Invoice, payment, SPK, atau DO.

Pengujian dilakukan pada database sementara, bukan data VPS. Uji regresi Proforma, koreksi pembayaran, penghapusan Quote, dan scanner gudang juga dijalankan terpisah.

## Deploy setelah PR di-merge

Jalankan dari direktori aplikasi di VPS:

```bash
cd /var/www/captureit-crm &&
git pull --ff-only origin main &&
composer dump-autoload -o &&
php artisan migrate --force &&
php artisan optimize:clear
```

Perintah migrate hanya menerapkan migration yang masih pending dari perubahan sebelumnya; PR ini tidak menambah migration.

Setelah deploy, buka ulang halaman Create/Generate Quote. Pilih contact, sales owner, item, serta Valid Until hari ini lalu simpan. Quote harus muncul pada daftar Quote dan pada Lead terkait. Bila ada input yang kurang, penyebabnya tampil di atas form. Lanjutkan dokumen untuk transaksi yang memang akan diproses sesuai persetujuan Admin Sales dan TOP yang disepakati.
