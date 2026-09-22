# Quote Sales Owner Search v1

## Tujuan

Field **Sales Owner** pada Create Quote dan Edit Quote menggunakan pencarian nama/email, bukan daftar `<select>` yang memuat semua user.

## User yang dapat dipilih

Hanya user berstatus aktif dengan salah satu role berikut yang muncul dan dapat disimpan:

- Administrator
- Sales Admin
- SuperAdministrator
- Sales User

Pemeriksaan dilakukan pada endpoint pencarian dan kembali divalidasi saat quote disimpan. Mengubah `user_id` secara manual dari browser tidak dapat melewati aturan tersebut.

## Perilaku pencarian

- Pencarian dimulai setelah pengguna mengetik minimal 2 karakter.
- Pencarian mencocokkan nama atau email.
- Permintaan ditunda 300 ms agar tidak mengirim request pada setiap ketukan dengan cepat.
- Maksimal 20 hasil per pencarian.
- Hasil menampilkan nama, role, dan email.

## Kompatibilitas quote lama

Sales Owner lama tetap ditampilkan saat membuka Edit Quote, termasuk bila user tersebut sekarang tidak aktif atau rolenya sudah tidak termasuk daftar yang diizinkan. Nilai lama boleh dipertahankan tanpa perubahan, tetapi jika Sales Owner diganti, pilihan baru wajib memenuhi aturan aktif dan role di atas.

Pada quote yang sudah diarsipkan/read-only, field Sales Owner ikut terkunci. Mekanisme khusus untuk koreksi Bill To tetap tidak berubah.

## Database dan deployment

Fitur ini tidak menambah tabel atau kolom, sehingga tidak memerlukan migration. Sesudah source code dirilis, jalankan cache clear aplikasi sesuai prosedur deployment yang berlaku.

## Pemeriksaan

```bash
php artisan test --compact tests/Unit/QuoteSalesOwnerSearchTest.php
vendor/bin/pint --dirty
```

Uji manual yang disarankan:

1. Buka Create Quote dan ketik 1 karakter; aplikasi belum mengirim hasil.
2. Ketik 2 karakter dari nama atau email; hasil yang sesuai muncul.
3. Pastikan user aktif dari keempat role dapat dipilih.
4. Pastikan role lain dan user nonaktif tidak muncul serta ditolak bila ID dikirim manual.
5. Buka quote lama; Sales Owner lama tetap terlihat.
6. Buka quote read-only; Sales Owner tidak dapat diubah.
