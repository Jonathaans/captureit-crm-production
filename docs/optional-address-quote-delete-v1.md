# Alamat opsional dan perbaikan Delete Quotation

Patch ini dibuat dari `main` commit `c772463` (setelah PR #10, pembaruan satuan pcs/day).

## Perubahan

- Alamat pada pembuatan/edit Lead dan Quote boleh kosong atau diisi sebagian. Validasi server dan penanda wajib pada form disesuaikan.
- Alamat kosong pada tampilan detail tidak menampilkan `undefined`; label alamat sebagian tidak mengakses kolom yang tidak ada.
- Delete Quote satuan dan massal memakai transaksi database. Kegagalan salah satu penghapusan membatalkan seluruh pilihan.
- Penolakan karena arsip ditampilkan dengan alasan yang jelas. Kesalahan lain dicatat di log aplikasi.
- Hak akses pemilik tetap diperiksa. Quote yang sudah terkait Invoice atau kedaluwarsa tetap read-only sesuai aturan aplikasi yang sudah ada.

Tidak ada perubahan database, dependency Composer/npm, maupun sumber build JS/CSS pada patch ini. Tidak perlu migration atau npm build untuk patch ini saja. Bila release mencakup perubahan lain, ikuti panduan release terkait juga.

## 1. Pasang di LOKAL — PowerShell VS Code

Simpan `optional-address-quote-delete-v1.patch` di folder Downloads. Jalankan satu per satu dari folder proyek:

```powershell
cd C:\Users\Administrator\Documents\laravel-crm-2.2
git status -sb
```

Jika ada perubahan kode aplikasi yang belum di-commit, simpan perubahan itu terlebih dahulu. Jangan gunakan `git reset --hard` atau `git clean`. File `storage/framework/lsp-*.php`, file patch, dan cache test tidak perlu dimasukkan ke commit.

Setelah perubahan kode sebelumnya sudah tersimpan:

```powershell
git fetch origin
git switch main
git pull --ff-only origin main
git switch -c fix/optional-address-quote-delete-v1
git apply --check "$env:USERPROFILE\Downloads\optional-address-quote-delete-v1.patch"
```

Jika `--check` selesai tanpa error, lanjutkan:

```powershell
git apply "$env:USERPROFILE\Downloads\optional-address-quote-delete-v1.patch"
php artisan optimize:clear
php artisan test --compact tests/Unit/OptionalSalesAddressTest.php
php artisan test --compact tests/Unit/QuoteDeletionTest.php
```

Jika salah satu perintah error, hentikan langkah berikutnya dan kirim output error tersebut. Patch memakai `git apply`, bukan `git am`.

## 2. Tes melalui browser LOKAL

Gunakan data percobaan lokal:

1. Buat Lead baru tanpa alamat. Simpan, buka kembali, dan pastikan tersimpan.
2. Buat Quote baru tanpa alamat dengan `Valid Until` di masa depan. Simpan dan buka kembali.
3. Isi hanya kota, kemudian simpan; coba juga kosongkan alamat pada Quote aktif yang sudah ada.
4. Hapus Quote percobaan yang belum menjadi Invoice dan belum kedaluwarsa. Baris harus hilang dari daftar.
5. Coba hapus Quote yang sudah menjadi Invoice atau kedaluwarsa. Harus muncul alasan penolakan; data tetap ada.
6. Untuk hapus massal, pilih satu Quote aktif dan satu Quote terlindungi. Keduanya harus tetap ada jika proses ditolak.

Jika Quote aktif tetap gagal dihapus, catat pesan yang tampil dan kirim bagian error terbaru `storage/logs/laravel.log` dengan data rahasia disamarkan. Perbaikan ini mempertahankan kebijakan arsip; tidak membuka penghapusan dokumen yang dilindungi.

## 3. Commit dan push dari LOKAL

Setelah test otomatis dan browser berhasil, stage hanya file patch ini:

```powershell
git add docs/optional-address-quote-delete-v1.md
git add packages/Webkul/Admin/src/Http/Controllers/Quote/QuoteController.php
git add packages/Webkul/Admin/src/Http/Requests/AttributeForm.php
git add packages/Webkul/Admin/src/Http/Requests/LeadForm.php
git add packages/Webkul/Admin/src/Resources/views/components/attributes/index.blade.php
git add packages/Webkul/Admin/src/Resources/views/components/attributes/view/address.blade.php
git add packages/Webkul/Admin/src/Resources/views/components/form/control-group/controls/inline/address.blade.php
git add packages/Webkul/Admin/src/Services/QuoteDeletionService.php
git add packages/Webkul/Admin/src/Support/OptionalSalesAddress.php
git add packages/Webkul/Attribute/src/Repositories/AttributeValueRepository.php
git add tests/Unit/OptionalSalesAddressTest.php
git add tests/Unit/QuoteDeletionTest.php
git diff --cached --stat
git commit -m "fix: optional sales addresses and atomic quote deletion"
git push -u origin fix/optional-address-quote-delete-v1
```

Buat Pull Request di GitHub:

- Base: `main`
- Compare: `fix/optional-address-quote-delete-v1`

Review perubahan dan hasil test, lalu merge PR. Perubahan belum masuk VPS hanya dengan push.

## 4. Terapkan di VPS setelah PR di-merge

Jalankan di terminal VPS `/var/www/captureit-crm`:

```bash
cd /var/www/captureit-crm
git fetch origin
git branch --show-current
git status -sb
git rev-list --left-right --count main...origin/main
git diff --stat main..origin/main
```

Branch harus `main`, angka kiri harus `0`. Periksa perubahan lokal atau release lain sebelum pull. File build yang dimodifikasi pada deployment sebelumnya jangan dihapus sembarangan.

Jika perubahan masuk hanya patch ini dan tidak ada perubahan kode lokal yang bertabrakan:

```bash
php artisan crm:backup-managed --database-only
```

Pastikan hasilnya `Backup PASS`. Lanjutkan satu per satu:

```bash
php artisan down
git pull --ff-only origin main
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Hentikan bila ada error; kirim pesan error untuk ditangani sebelum lanjut. Tidak diperlukan migration untuk patch ini.

Periksa hasil:

```bash
git rev-list --left-right --count main...origin/main
supervisorctl status
```

Git harus menunjukkan `0 0`. Refresh browser dan pastikan form tidak lagi mewajibkan alamat. Uji penghapusan menggunakan Quote percobaan yang belum menjadi Invoice dan belum kedaluwarsa, dengan akun yang berhak.

## Verifikasi saat menyiapkan patch

- Pemeriksaan perilaku terisolasi PHP/SQLite: alamat kosong/sebagian, format alamat, penghapusan aktif, penolakan Invoice/expired, hak akses, ID hilang, dan rollback kegagalan.
- Pemeriksaan JavaScript untuk alamat null/kosong/sebagian dan salinan nilai form.
- Pemeriksaan sintaks PHP serta kompilasi Blade, dan pengecekan penerapan patch pada checkout bersih.
- Test Pest seluruh aplikasi dan tes browser belum dijalankan di lingkungan pembuat patch karena dependency CRM lengkap tidak tersedia. Jalankan langkah 1–2 di lokal sebelum deployment.
- Pemeriksaan skill repository tidak dapat lulus karena direktori `.github/skills` tidak ada pada baseline; ini bukan perubahan patch.
