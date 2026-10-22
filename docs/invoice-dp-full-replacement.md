# Ganti Invoice DP ke Full Payment

Gunakan **Ganti ke Full Payment** pada detail invoice DP ketika pelanggan meminta
satu tagihan penuh dan belum ada pembayaran sah diterima. Invoice pengganti tetap
mengacu **quotation yang sama**. Tidak ada quotation, SPK, Surat Jalan, pembayaran,
atau movement stok baru yang dibuat oleh aksi penggantian.

## Alur pengguna

1. Buka invoice DP UNPAID pada event Confirm, lalu **Ganti ke Full Payment**.
2. Tinjau nomor invoice DP, nominal dokumen lama, nilai penuh kesepakatan,
   TOP, dan jatuh tempo pengganti. Isi alasan serta konfirmasi persetujuan pelanggan.
3. Konfirmasi penggantian. Backend membuat proforma penuh dan memindahkannya
   melalui konversi **event sudah deal**, tanpa mencatat penerimaan uang.
4. Invoice DP menjadi **VOID / Dibatalkan—Diganti Invoice Baru**, saldo aktif nol.
   Invoice full berstatus **UNPAID**. Kedua dokumen memiliki tautan silang,
   alasan, waktu, pengguna, dan tautan ke proforma pengganti.

Untuk kesepakatan Rp10 juta dan DP Rp3 juta tanpa pembayaran, dokumen DP asli tetap
Rp3 juta dengan tagihan aktif nol. Invoice pengganti Rp10 juta, bukan Rp7 juta.
Jika kesepakatan memakai PPh gross-up, nilai transfer netto tetap mengikuti
perhitungan PPh yang sudah ada; nominal invoice penuh dan potongannya tampil
terpisah pada pratinjau.

Pembatalan DP, pembuatan proforma, penerbitan invoice, referensi, dan audit berada
dalam satu transaksi. Kegagalan pada langkah mana pun mempertahankan invoice DP
aktif. Mengirim ulang konfirmasi yang sama mengembalikan invoice pengganti yang
sudah ada, tanpa dokumen atau uang masuk kedua. Ini berbeda dari **Cancel** pada
status event, yang tetap berarti pembatalan event.

## Ketentuan dan sumber nilai

- Wajib memiliki izin `invoices.view`, `invoices.revise`, dan `invoices.generate`.
  Tidak ada bypass berdasarkan nama role; izin payment tidak diperlukan karena
  penggantian tidak mencatat pembayaran.
- Hanya invoice jenis `down_payment`, status `unpaid`, event `confirm`, belum
  pernah diganti, serta memiliki saldo konsisten dengan nominal DP yang diterbitkan.
- Tidak boleh ada pembayaran aktif, `paid_amount` bukan nol, invoice aktif lain,
  atau proforma issued lain pada quotation yang sama. Pembayaran yang masuk
  sesudah pratinjau menyebabkan konfirmasi ditolak tanpa membatalkan DP.
- Periode penerbitan DP dan periode penerbitan pengganti harus terbuka. Pengganti
  terbit pada tanggal konfirmasi; jatuh tempo wajib tanggal itu atau sesudahnya.
- Gunakan snapshot kesepakatan pada proforma asal, termasuk produk, nilai penuh,
  Bill To, dan pajak. Untuk DP legacy tanpa proforma, quotation arsip hanya boleh
  digunakan bila totalnya cocok dengan `quote_total_snapshot`. Ketidakcocokan atau
  snapshot total yang hilang ditolak untuk ditinjau melalui Revisi Transaksi.
- Konfirmasi memeriksa ulang seluruh posisi billing dan penguncian periode.
  Perubahan setelah pratinjau mengharuskan pengguna meninjau data terbaru.

## Operasional dan laporan

Nomor, item, dan nominal asli DP dipertahankan. Proforma DP asal tetap converted
dan tetap menunjuk invoice DP lama sebagai riwayat. Quote tidak ditulis ulang.

SPK dan Surat Jalan lama tetap menunjuk dokumen asal. Membuka SPK atau daftar
Surat Jalan dari invoice full menemukan dokumen proyek yang sama; meminta SPK
dari invoice full mengembalikan SPK yang sudah ada. Tidak ada pengulangan pekerjaan
atau pengeluaran stok. Penyelesaian SPK, return Surat Jalan, dan pemrosesan PO
yang sudah ada tetap dapat berjalan sesuai aturan status/arsip masing-masing.
Dokumen operasional final tetap read-only. Invoice DP VOID tidak menerima payment,
expense, SPK, atau Surat Jalan baru melalui alur invoice.

Laporan resmi mengecualikan nominal DP VOID dari total invoice, piutang, penerimaan,
Top Product, dashboard, serta ekspor. Posisi proyek tetap menjumlahkan biaya
historis pada DP lama dan biaya invoice full tepat satu kali. Misalnya biaya
Rp6 juta pada DP lama menghasilkan posisi proyek: invoice Rp10 juta, diterima
Rp0, piutang Rp10 juta, biaya Rp6 juta, margin Rp4 juta sebelum potongan PPh.
Biaya tetap memakai tanggal transaksi aslinya, termasuk bila penggantian terjadi
pada bulan berbeda.

## Deploy dan pengujian

Setelah merge, ikuti prosedur backup dan maintenance VPS pada README. Deploy kode
bersama migration `2026_10_22_120000_create_invoice_replacements`, jalankan
`php artisan migrate --force`, lalu segarkan cache aplikasi dan Blade. Tidak ada
dependency atau JavaScript baru. Gunakan build Admin yang biasa bila deployment
menjalankan build aset.

Migration menambah dua referensi invoice dan tabel audit `invoice_replacements`.
Rollback migration ditolak setelah audit terisi agar riwayat tidak hilang.
Rollback kode harus mempertahankan tabel, referensi, dan invoice yang sudah terbit.

Regression berada di `tests/Unit/ProformaInvoiceWorkflowTest.php`, grup
`unpaid DP replacement with full payment`: quotation tetap satu, nilai penuh,
UNPAID tanpa penerimaan, snapshot kesepakatan, biaya proyek, SPK/SJ bersama,
klik ulang, rollback setiap tahap, pembayaran sesudah preview, konflik billing,
periode tertutup, legacy snapshot, ACL, tampilan riwayat/PDF, serta migration.

```bash
php vendor/bin/pest tests/Unit/ProformaInvoiceWorkflowTest.php --compact
php artisan view:cache
php vendor/bin/pint --test --dirty
git diff --check
```

Verifikasi pengembangan: 458 unit test lulus (termasuk 33 kasus penggantian),
Pint, kompilasi Blade, dan pemeriksaan whitespace lulus. Empat feature test
Authentication/Help gagal dengan hasil identik pada baseline karena fixture
instalasi/user belum disiapkan; tidak ada kegagalan baru. Validator skills juga
masih gagal pada baseline karena `.github/skills` tidak tersedia. Pengujian memakai
SQLite in-memory; belum ada uji interaktif pada VPS atau uji konkurensi MySQL live.
