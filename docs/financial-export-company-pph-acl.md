# Financial Report export dan ACL menu

## Perubahan

- CSV Financial Report menambahkan kolom `Company` setelah `Customer` dan kolom `PPH` di akhir detail invoice.
- Company mengikuti snapshot Bill To invoice. Invoice lama mengikuti fallback Bill To yang sudah dipakai PDF: quotation, lalu organisasi customer. Identitas yang kosong ditampilkan sebagai `-`.
- `PPH` adalah kolom kosong untuk pengisian manual pada hasil export. Belum ada field PPH tersimpan atau aturan tarif/perhitungan; angka invoice, pembayaran, outstanding, expense, dan profit tidak berubah.
- Relasi company dimuat bersama query laporan agar tidak menambah query per invoice.
- Menu Financial Report sekarang memeriksa izin `invoices.financial-report`, dengan key tampilan `financial-report` tetap dipertahankan.
- Menu Inventory untuk Consumables, Maintenance, Stock Opname, Alerts, dan QA ditambahkan sesuai ACL yang sudah ada.
- Menu induk tetap tersedia sebagai wadah jika hanya submenu yang diizinkan. Tujuannya adalah halaman anak yang diizinkan; izin induk, sibling, edit, delete, dan export tidak ikut diberikan.
- Cache menu mengikuti akun, permission, area, locale, dan URL sehingga hasil akun atau izin sebelumnya tidak terbawa.
- Pemeriksaan `permission_type=all` konsisten antara model user dan bouncer; user tanpa role ditolak.
- Route Mail yang dipakai beberapa folder sekarang memeriksa izin folder yang diminta, bukan izin folder terakhir di konfigurasi ACL.

## Batas pemeriksaan

Nama role `Superadministrator` tidak memberi bypass pada izin custom. Pengujian memakai role dengan nama tersebut dan beberapa variasi nama, bukan akun/database production.

Aturan khusus berikut sudah ada sebelum perubahan dan tetap berlaku:

- Operations Dashboard dan endpoint backup hanya menerima role bernama `Administrator`, ditambah ACL terkait.
- Internal Chat Audit memakai whitelist `INTERNAL_CHAT_AUDIT_ROLES` selain middleware ACL. Default-nya `Administrator,Super Admin,Management,Director`; `Superadministrator` tidak tercantum.

Jika keluhan akun production menyangkut halaman tersebut, ACL yang dicentang saja memang belum cukup. Perubahan kebijakan role khusus perlu ditentukan secara terpisah.

## Verifikasi

```bash
php vendor/bin/pest tests/Unit/AclMenuPermissionsTest.php tests/Unit/FinancialReportExportTest.php tests/Unit/ProductCreatePermissionTest.php --compact --do-not-cache-result
./vendor/bin/pint --test --dirty
git diff --check
```

Suite terarah mencakup snapshot/fallback company, CSV dengan Unicode, tanda kutip, pemisah dan newline, PPH kosong, filter periode/unit/status/product, perubahan role, menu bertingkat, login landing, active menu, serta izin URL langsung.

Suite penuh juga dijalankan pada branch perbaikan dan baseline `907831c`. Keduanya memiliki 6 kegagalan dan 16 risky test yang sama: dua assertion lama pada QR label dan alamat quotation, serta empat feature test yang bergantung pada instalasi/database berisi user. Tidak ada kegagalan baru pada suite terarah.

`bash bin/validate-skills.sh` gagal pada baseline karena direktori `.github/skills` yang dirujuk `AGENTS.md` tidak ada dalam repo. File panduan Pest juga tidak tersedia; pengujian mengikuti pola Pest yang sudah ada di `tests/Unit`.

## Penerapan dan smoke test

Tidak ada migration database atau build aset frontend. Sesudah perubahan di-merge dan di-deploy melalui alur deployment biasa:

```bash
php artisan optimize:clear
```

1. Login sebagai akun dengan role Superadministrator yang memakai permission custom. Cocokkan menu Financial Report dan submenu Inventory dengan checklist role.
2. Coba role dengan hanya satu submenu Inventory atau Settings. Menu tersebut dan halaman setelah login harus dapat dibuka.
3. Matikan izin export/edit lalu akses URL terkait secara langsung. Akses harus ditolak.
4. Ubah izin, muat ulang halaman, lalu logout/login dengan akun lain; menu harus mengikuti akun aktif.
5. Export laporan dengan filter yang dipilih. Periksa `Company`, kolom `PPH` kosong, jumlah baris, dan angka laporan.
