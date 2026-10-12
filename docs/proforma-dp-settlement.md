# Proforma, invoice DP, dan invoice pelunasan

## Alur finance

| Kondisi | Dokumen dan pembayaran | SPK |
| --- | --- | --- |
| Deal dengan DP | Buat **Proforma DP** dari penawaran. Belum ada invoice aktual atau penerimaan uang. | Belum dapat dibuat dari proforma. |
| DP diterima | Buka proforma → **Konfirmasi DP diterima**. Isi nominal, waktu uang masuk, metode, dan referensi. Sistem membuat invoice DP senilai DP beserta payment, berstatus **PAID**. | Dapat dibuat dari invoice DP. |
| Event akan berjalan / sudah lewat | Buat **Invoice Pelunasan** untuk sisa nilai penawaran. Tanggal penerbitan, jatuh tempo, dan TOP diisi sesuai perjanjian. Payment dicatat ketika uang pelunasan diterima. | Memakai SPK yang sama dengan invoice DP. |
| Tanpa DP | Pilih **Tanpa DP**: langsung membuat invoice penuh, awalnya **UNPAID**. Pembayaran sebelum atau sesudah event mengikuti TOP dan jatuh tempo. | Dapat dibuat dari invoice meskipun belum dibayar. |

Contoh penawaran Rp10 juta: proforma DP Rp3 juta → setelah uang masuk, invoice DP Rp3 juta PAID → invoice pelunasan Rp7 juta dibuat terpisah. Total kedua invoice Rp10 juta. Terbitnya invoice pelunasan tidak otomatis menambah penerimaan uang.

Menu: **Invoices → Proforma**. Tombol **Buat dari Penawaran** memakai form yang sama untuk ketiga pilihan. Proforma dapat dicetak atau disimpan sebagai PDF melalui dialog cetak browser.

Pelunasan diterbitkan manual oleh finance saat waktunya sesuai perjanjian; tidak dibuat otomatis berdasarkan tanggal event. TOP dan tanggal jatuh tempo wajib diisi. Tanggal penerimaan DP tidak boleh di masa depan atau sebelum proforma, dan periode keuangan yang sudah ditutup tetap dilindungi.

## Konsistensi data

- Proforma disimpan dalam tabel terpisah `proforma_invoices`, sehingga tidak ikut dihitung sebagai invoice aktual di Financial Report.
- Invoice DP dan payment dibuat dalam satu transaksi. Jika salah satunya gagal, seluruh konversi dibatalkan. Mengirim ulang konfirmasi tidak membuat invoice/payment kedua.
- Nominal penerimaan harus sama dengan DP pada proforma. Untuk perubahan nominal sebelum konversi, batalkan proforma dengan alasan lalu buat ulang. Proforma yang telah dikonversi tidak dapat dibatalkan melalui tombol ini.
- Nomor proforma memakai seri `PF`; invoice tetap memakai seri `INV`. Nomor invoice tidak digunakan saat proforma diterbitkan.
- Nilai penawaran, Bill To person/company, produk, jumlah peralatan, dan TOP disimpan saat proforma dibuat. Invoice DP dan pelunasan memakai snapshot tersebut. Pengeditan penawaran kemudian tidak diam-diam mengubah total kesepakatan atau company pada kedua invoice.
- Sisa yang belum ditagihkan dihitung dari total kesepakatan DP. Invoice pelunasan berisi nilai sisa, bukan total penawaran lagi.
- DP dan pelunasannya memakai satu induk operasional. Membuka/membuat SPK dari salah satunya menuju SPK yang sama; halaman Surat Jalan menampilkan dokumen proyek tersebut. Tidak ada pengeluaran stok otomatis saat invoice diterbitkan.
- Data invoice/SPK lama tidak ditulis ulang. SPK lama yang sudah terhubung ke invoice pelunasan tetap dapat ditemukan. Bila data lama sudah memiliki dua SPK, keduanya tidak dihapus atau digabung otomatis; perlu ditinjau sesuai kondisi dokumen dan stok.
- Koreksi invoice lewat maintenance mendeteksi proforma terkait sebagai dependency agar tautan audit tidak terputus.

## Izin role

Tidak ada bypass berdasarkan nama role. Checkbox izin invoice yang sudah ada dipakai kembali:

| Tindakan | Permission |
| --- | --- |
| Daftar proforma | `invoices` |
| Detail proforma | `invoices.view` |
| Buat proforma / invoice / pelunasan | `invoices.generate` |
| Konfirmasi uang DP masuk | `invoices.payment` |
| Batalkan proforma | `invoices.event-status` |
| Cetak proforma | `invoices.print` |
| Buat / buka SPK | Izin `work-orders` yang sudah ada |

Berikan izin daftar/detail juga kepada pengguna yang perlu membuka dokumen. Hak generate sekarang mengikuti checkbox **Generate Invoice**, termasuk pada URL form/POST langsung. Izin daftar invoice saja tidak lagi memberi hak generate.

## Deploy VPS setelah merge

Jalankan dari folder aplikasi sebagai user deployment. Contoh folder mengikuti README repo; ganti bila instalasi VPS menggunakan folder lain. Pastikan working tree bersih dan backup database berhasil sebelum melanjutkan. Hentikan langkah berikutnya bila sebuah perintah gagal.

```bash
cd /var/www/captureit-crm
git status --short
php artisan down
php artisan crm:backup-managed --database-only
git switch main
git pull --ff-only origin main
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm install --prefix packages/Webkul/Admin
npm run build --prefix packages/Webkul/Admin
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Migration baru: `2026_10_12_030000_create_proforma_invoices_table`. Migration menambah satu tabel; invoice lama tidak dikonversi menjadi proforma. Deploy kode dan migration pada jendela maintenance yang sama. Untuk rollback kode setelah mulai dipakai, pertahankan tabel dan data proforma; jangan menjalankan rollback migration yang menghapus dokumen tersebut.

Setelah deploy, cek role finance, jalankan contoh Rp10 juta/Rp3 juta/Rp7 juta pada data uji, lalu periksa invoice, payment, SPK, Surat Jalan, dan Financial Report. Cek juga skenario tanpa DP dengan TOP sebelum dan sesudah event. Jangan membuat pembayaran uji pada dokumen pelanggan sungguhan.

## Verifikasi pengembangan

- Pengujian baru mencakup proforma tanpa transaksi aktual, DP/pelunasan terpisah, total dan company tetap setelah quote berubah, TOP sebelum/sesudah event, SPK bersama, duplicate submit, rollback transaksi, pembatalan, validasi nominal/tanggal, periode tertutup, migration up/down, cetak proforma, dan izin HTTP.
- Suite regresi mencakup billing, unit/jumlah peralatan, Bill To, export laporan, ACL, SPK, koreksi data, dan equipment Surat Jalan.
- Suite penuh masih memiliki 6 kegagalan dan 16 risky test yang sudah ada pada baseline: assertion QR/alamat quotation serta feature test dengan database instalasi/user yang belum di-seed. Tidak ada kegagalan baru pada suite terarah.
- Suite fitur baru: 24 test lulus. Suite terarah: 71 lulus, 16 risky yang sudah ada.
- Build production Vite, Pint, dan kompilasi Blade berhasil. Aset hasil build lokal tidak disertakan; jalankan build saat deploy sesuai langkah di atas. Validator skills masih gagal karena `.github/skills` yang dirujuk `AGENTS.md` tidak tersedia dalam repo; ini juga terjadi pada baseline.

```bash
php vendor/bin/pest tests/Unit/ProformaInvoiceWorkflowTest.php --compact
php artisan view:cache
php vendor/bin/pint --test --dirty
git diff --check
```
