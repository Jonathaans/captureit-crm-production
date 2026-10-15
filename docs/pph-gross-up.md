# Gross-up PPh 0,5% dan revisi transaksi (PR #32)

Fitur ini dibangun di atas PR #31, memakai Revisi Transaksi, izin `invoices.revise`, kunci periode, pemeriksaan fingerprint, dan riwayat sebelum/sesudah yang sudah ada. Tidak ada perintah penghapusan atau pemindahan pembayaran baru.

## Rumus dan batas penggunaan

DJP menjelaskan pemotongan PPh final 0,5% dari nilai jasa untuk rekanan yang memenuhi ketentuannya, dengan surat keterangan yang berlaku. Tarif ini bukan tarif otomatis untuk semua pelanggan/perusahaan:

https://pajak.go.id/id/berita/bendahara-pemerintah-harus-paham-tidak-semua-jasa-dikenakan-pph-pasal-23

Dari hubungan `netto = bruto − (0,5% × bruto)`, rumus gross-up adalah `bruto = netto / 0,995`. Ini merupakan penurunan matematis dari tarif tersebut; artikel DJP bukan panduan penggunaan CRM.

- Pilihan **Gross-up PPh 0,5%** default **Tidak**. Pilih **Ya — dipotong oleh pelanggan** sesuai kesepakatan dan ketentuan pajak transaksi.
- Dasar netto jasa = subtotal produk − diskon + penyesuaian. Pajak lain/PPN diisi terpisah sesuai nominal yang berlaku; tidak ikut di-gross-up dan tidak dihitung ulang otomatis.
- Perhitungan disimpan oleh server, bukan menerima nominal PPh/total dari browser. Pembulatan dua desimal; pelunasan menerima sisa alokasi pembulatan.
- Grand total adalah bruto termasuk tambahan gross-up. PPh ditampilkan terpisah, lalu **transfer netto** = grand total − PPh.
- Status Paid dan balance mengikuti transfer netto. PPh adalah potongan yang disepakati, bukan penerimaan kas atau bukti bahwa pajak telah disetor. Bukti potong tetap diperoleh dari pelanggan.
- Proforma tidak diakui sebagai invoice/revenue/cash. Saat dikonversi, snapshot PPh ikut ke invoice. Pembayaran proforma harus sebesar transfer netto.
- Financial Report/CSV: nilai invoice bruto, kolom PPH terisi otomatis, penerimaan dari payment asli, outstanding netto. Margin sebelum PPh dipertahankan dan margin setelah PPh ditampilkan terpisah. PPh tidak dibuat sebagai kas keluar/expense otomatis. Dasar komisi tetap kas netto yang diterima, setelah semua tagihan netto lunas.

## Quote baru

1. Masukkan harga produk sebagai nilai netto jasa yang ingin diterima.
2. Pilih Gross-up PPh 0,5% sesuai perjanjian. Periksa bruto, PPh, dan transfer netto.
3. Generate proforma seperti biasa. Nominal dokumen DP pada form proforma adalah **bruto**; preview memperlihatkan PPh dan transfer netto. Persentase DP membagi bruto sesuai persentase.
4. Setelah event deal/pembayaran diterima, pindahkan proforma ke invoice. DP dan pelunasan tetap mempunyai kode proyek yang sama.

## Koreksi INV 2610-0047 dan INV 2610-0048

Uang memang diterima, sehingga jangan memakai Void Pembayaran atau `crm:invoice-void --no-money-received` untuk kasus ini.

1. Setelah deploy, buka salah satu invoice → **Revisi Transaksi**. Role perlu izin Invoices → Revisi Transaksi.
2. Pastikan quote `QT 2610-0056`, proyek `PRJ-2026-00059`, dua invoice tersebut, dan harga penuh jasa netto Rp7.500.000 memang sesuai data.
3. Pilih **Ya — dipotong oleh pelanggan**. Transfer netto DP lunas tetap Rp3.750.000; jangan memasukkan bruto baru sebagai harga produk.
4. Periksa preview:

| Dokumen | Bruto | PPh | Transfer netto |
|---|---:|---:|---:|
| Quote | 7.537.688,44 | 37.688,44 | 7.500.000,00 |
| Invoice DP | 3.768.844,22 | 18.844,22 | 3.750.000,00 |
| Invoice pelunasan | 3.768.844,22 | 18.844,22 | 3.750.000,00 |

5. Isi alasan, misalnya “Permintaan client gross-up PPh 0,5%; penerimaan asli tetap”, lalu simpan sekali. Dua invoice tetap **Paid**, dengan sisa netto 0. Nomor/ID invoice, kode proyek, tanggal terbit, dan payment asli dipertahankan. Dokumen diperbarui melalui revisi beraudit, bukan dihapus atau dibuat ulang.
6. Cetak ulang Quote, Proforma, dan Invoice. Buka riwayat revisi serta Financial Report September/Oktober. Penerimaan September tetap Rp3.750.000 pada 30 September 16:22; Oktober tetap Rp3.750.000 pada 12 Oktober 13:23. Tidak perlu input pembayaran ulang.

Jika periode terkait terkunci, saldo pembayaran tidak konsisten, data berubah sejak form dibuka, atau nominal netto baru lebih kecil dari penerimaan, revisi ditolak. Jangan melewati pengaman tersebut dengan SQL langsung. Perubahan produk/tanggal tetap mengikuti aturan peninjauan operasional PR #31; mengaktifkan gross-up saja tidak menambah SPK, DO, atau pergerakan stok.

## Deploy dari VPS yang masih PR #30

PR #31 dan #32 masuk sekaligus lewat `main`. Buat snapshot/backup database melalui panel/alat backup VPS sebelum deploy. Jalankan setelah PR #32 **ready dan sudah merged**, bukan saat masih draft.

Cek lebih dulu:

```bash
cd /var/www/captureit-crm
git fetch origin main
git log --oneline HEAD..origin/main
git diff --name-status HEAD origin/main
git diff --name-only HEAD origin/main -- public/admin/build
```

Untuk PR #31/#32 ini, perintah terakhir seharusnya tidak menampilkan apa pun. Tidak ada perubahan dependency Composer, build Vite, atau alat cleanup. Jangan reset/clean file build lokal atau file cadangan yang terlihat pada `git status`. Git dapat fast-forward sambil mempertahankan perubahan lokal yang tidak bertabrakan.

Simpan cadangan file lokal secara privat (sesi root seperti log VPS):

```bash
cd /var/www/captureit-crm &&
umask 077 &&
CRM_BACKUP_DIR="/root/captureit-backups/$(date +%Y%m%d-%H%M%S)" &&
mkdir -p "$CRM_BACKUP_DIR" &&
git diff --binary > "$CRM_BACKUP_DIR/local-changes.patch" &&
git status --short > "$CRM_BACKUP_DIR/git-status.txt" &&
cp -a .env "$CRM_BACKUP_DIR/env" &&
tar -czf "$CRM_BACKUP_DIR/local-app-files.tar.gz" public/admin/build packages/Webkul/Admin/src/Resources/views tools
```

Ini backup file, bukan backup database. File `.env.before-debug-compose` dan file untracked lain tidak dihapus oleh deploy.

Deploy setelah cadangan siap:

```bash
cd /var/www/captureit-crm &&
php artisan down &&
git pull --ff-only origin main &&
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader &&
php artisan migrate --force &&
php artisan optimize:clear &&
php artisan queue:restart &&
php artisan up
```

Jangan lanjutkan rangkaian bila ada error; simpan pesan error untuk diperiksa. Jika proses berhenti setelah `artisan down`, aplikasi masih dalam maintenance sampai masalah diselesaikan dan `php artisan up` dijalankan. Tidak diperlukan build aset untuk perubahan ini.

Verifikasi:

```bash
php artisan migrate:status
git log -3 --oneline
git status --short
```

Migrasi `2026_10_15_090000_add_commercial_document_revisions` dan `2026_10_15_100000_add_pph_gross_up_to_billing_documents` harus Ran. Cek Create Quote dan Revisi Transaksi menampilkan opsi gross-up. Setelah transaksi dikoreksi, pastikan CSV menampilkan PPH, saldo netto 0, dan tanggal/nominal penerimaan tetap.
