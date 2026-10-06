# Lead ke Quotation dan izin Create Product

Patch: `lead-quote-prefill-product-access-v1.patch`

Dasar patch: `7839647` (main setelah PR #13, sidebar kategori dan warna).
Branch yang disarankan: `fix/lead-quote-prefill-product-access-v1`.

## Perubahan

Generate Quotation dari halaman Lead dan pemilihan **Link to Lead** di Create Quote memakai pemetaan yang sama:

| Data Lead | Isian Quotation |
| --- | --- |
| Title | Project Name (`subject`) |
| Description | Description |
| Contact dan Company terkait | Bill To dan identitas klien |
| Sales Owner | Sales Owner, dengan aturan role dan status aktif yang sudah ada |
| Alamat Lead jika tersedia | Address |
| Alamat Company, lalu Contact | Cadangan jika alamat Lead kosong |
| Expected Close Date | Valid Until, mengikuti perilaku sebelumnya |
| Event Date, Location, Business Unit, Payment Term | Isian yang sama, jika tersedia pada Lead dengan nilai yang sesuai |
| Produk dan deskripsinya | Item Quotation; deskripsi master produk dipakai jika tidak ada deskripsi baris Lead |
| Quantity, Pcs/Day, harga | Quantity, Unit, Unit Price dan jumlah baris |

Alamat tetap opsional. Contact yang sudah dipilih digunakan kembali. Nomor quotation dan project code tetap dibuat oleh sistem. Total dihitung dari rincian produk; nilai perkiraan Lead tidak menggantikan perhitungan tersebut. Stage, source, dan metadata khusus Lead tidak dimasukkan ke kolom Quote yang berbeda makna. Atribut tambahan di luar pemetaan tabel tidak disalin otomatis.

Saat memilih Lead lain, data yang terkait di atas diganti dengan data Lead tersebut. Respons pencarian yang terlambat tidak menimpa pilihan terbaru. Jika permintaan gagal, pilihan sebelumnya dipulihkan. Melepas tautan Lead mempertahankan isi form agar perubahan pengguna tidak hilang. Form menunggu pengambilan data Lead selesai sebelum bisa disimpan.

Jika validasi penyimpanan gagal, pilihan Lead, judul/deskripsi yang diedit, klien, alamat, dan item yang dikirim dipertahankan. Pemeriksaan kepemilikan Lead diterapkan sebelum mengambil data.

Masalah Create Product berasal dari dua izin yang memakai rute sama: **Create Product** tertimpa oleh **Quick Add** pada pemetaan ACL. Sekarang:

| Izin role | Buka Create Product | Simpan form penuh | Quick Add |
| --- | --- | --- | --- |
| Create Product | Bisa | Bisa | Bisa |
| Hanya Quick Add | Tidak | Tidak | Bisa |
| Hanya View atau Edit | Tidak | Tidak | Tidak |
| Administrator dengan permission `all` | Bisa | Bisa | Bisa |

Perbaikan mengikuti izin role, bukan nama akun atau daftar role yang di-hardcode. Tidak ada perubahan aturan arsip/read-only Quote, perhitungan invoice, atau hak akses lainnya.

## 1. Pasang di LOKAL terlebih dahulu

Simpan patch unduhan di folder **Downloads**. Tidak perlu dipindah ke folder proyek.

Buka terminal **PowerShell di VS Code lokal**:

```powershell
cd "C:\Users\Administrator\Documents\laravel-crm-2.2"
git status -sb
git log -1 --oneline
```

Pastikan perubahan sidebar sebelumnya sudah ada. Jika ada perubahan kode lain yang belum disimpan dalam commit, selesaikan atau pisahkan terlebih dahulu. Jangan memakai `git reset --hard`, `git clean`, atau `git add .` untuk mengatasi kondisi ini. Cache PHPUnit dan file LSP tidak perlu dimasukkan ke commit.

Buat branch kerja baru, lalu periksa patch:

```powershell
git switch -c fix/lead-quote-prefill-product-access-v1
git apply --check "$env:USERPROFILE\Downloads\lead-quote-prefill-product-access-v1.patch"
```

Jika pemeriksaan selesai tanpa pesan, lanjutkan:

```powershell
git apply "$env:USERPROFILE\Downloads\lead-quote-prefill-product-access-v1.patch"
php artisan optimize:clear
php artisan test --compact tests/Unit/LeadQuotePrefillTest.php
php artisan test --compact tests/Unit/ProductCreatePermissionTest.php
git diff --check
```

Jika `git apply --check` atau test menampilkan **FAILED / error**, berhenti pada langkah itu dan periksa pesannya. Jika branch sudah ada, jangan membuat ulang atau menimpanya; gunakan branch yang sesuai setelah memeriksa status.

Patch ini tidak menambahkan migration, dependency Composer/npm, atau perubahan bundle aset. Tidak perlu menjalankan `migrate:fresh`, seeder, atau build npm untuk patch ini.

## 2. Tes melalui browser LOKAL

1. Login sebagai sales yang memiliki akses Lead dan Quote. Pilih Lead miliknya yang memiliki judul, deskripsi, klien/company, alamat, dan produk. Klik Generate Quotation. Cocokkan semua isian menurut tabel di atas, simpan, buka kembali, lalu periksa PDF.
2. Di Create Quote, pilih Lead melalui **Link to Lead**. Pilih Lead lain dengan klien, alamat, owner, dan produk berbeda. Pastikan semuanya berganti, termasuk nama dan ID produk yang disimpan. Coba Lead tanpa alamat dan tanpa produk; data Lead sebelumnya tidak boleh tertinggal.
3. Ubah judul, deskripsi, quantity, dan harga sebelum menyimpan. Coba validasi gagal pada satu isian wajib lalu perbaiki; perubahan yang Anda buat harus tetap ada. Deskripsi yang sengaja dikosongkan juga harus tetap kosong.
4. Login sebagai akun non-Administrator dengan izin **Products > Create**. Buat produk percobaan, simpan, buka ulang, dan periksa nama, SKU, harga, serta unit. Ulangi dengan role lain yang memiliki izin sama.
5. Dengan akun yang hanya punya View/Edit, pastikan pembuatan produk tetap ditolak. Jika menggunakan Quick Add, tes juga dengan role yang hanya diberi Quick Add.

## 3. Commit dan push dari LOKAL setelah tes berhasil

Pilih file patch ini saja:

```powershell
git add packages/Webkul/Admin/src/Config/acl.php
git add packages/Webkul/Admin/src/Http/Controllers/Quote/QuoteController.php
git add packages/Webkul/Admin/src/Http/Middleware/Bouncer.php
git add packages/Webkul/Admin/src/Resources/views/quotes/create.blade.php
git add packages/Webkul/Admin/src/Services/LeadQuotePrefillService.php
git add tests/Unit/LeadQuotePrefillTest.php
git add tests/Unit/ProductCreatePermissionTest.php
git add docs/lead-quote-prefill-product-access-v1.md
git diff --cached --stat
git commit -m "fix: prefill lead quotation and honor product create permissions"
git push -u origin fix/lead-quote-prefill-product-access-v1
```

Di **GitHub**, buat Pull Request dengan **base: main** dan **compare: fix/lead-quote-prefill-product-access-v1**. Periksa perubahan dan hasil pengujian sebelum merge.

## 4. VPS sesudah PR di-merge

Gunakan prosedur deployment di `README.production-vps.md` / `README-PRODUCTION.md` yang berlaku untuk server Anda. Ambil backup kode/database sesuai prosedur tersebut. Pull hanya dengan fast-forward setelah memastikan branch dan perubahan lokal VPS aman:

```bash
cd /var/www/captureit-crm
git fetch origin
git branch --show-current
git status -sb
git rev-list --left-right --count main...origin/main
git diff --stat main..origin/main
```

Lanjutkan deployment hanya jika branch VPS adalah `main`, sisi kiri hasil hitungan `0`, dan perubahan lokal tidak bertabrakan dengan file patch. Jangan menghapus file cadangan atau hasil build VPS agar Git menjadi bersih. Terapkan langkah pull, autoload/cache, dan restart layanan sesuai panduan deployment repositori. Patch ini sendiri tidak memerlukan migration atau build npm; jika rilis membawa perubahan lain, ikuti kebutuhan seluruh rilis.

## Batas verifikasi paket

- 49 pemeriksaan backend terisolasi lulus pada PHP 8.3: pemetaan field/item, ACL dan middleware izin, controller Generate/Link to Lead, old input, dan pembatasan kepemilikan. Repositori database menggunakan pengganti dalam memori pada pemeriksaan controller.
- Sintaks PHP/Blade dan render komponen Project Name/Description diperiksa. Komponen Vue asli diuji dengan Happy DOM dan template tes sederhana: pergantian produk/alamat/owner, respons terlambat, kegagalan jaringan, pelepasan tautan, Lead kosong, serta guard saat data masih dimuat.
- File Pest disertakan, tetapi suite Pest aplikasi penuh dan penyimpanan produk ke database CRM belum dijalankan di lingkungan pembuat patch karena dependency aplikasi tidak tersedia. Jalankan perintah test dan tes browser lokal di atas.
- Pint dijalankan pada PHP yang diubah; format lama di luar blok ACL yang diperbaiki dipertahankan agar patch tidak memuat ratusan perubahan spasi. Validator skill repositori masih gagal karena `.github/skills` tidak tersedia pada baseline.
