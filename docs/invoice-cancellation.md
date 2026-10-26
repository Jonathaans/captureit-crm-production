# Pembatalan invoice duplikat

Buka **Invoices → detail invoice → Batalkan Invoice**. Tinjau nomor dan nominal,
isi alasan (minimal 8 karakter, sebaiknya mencantumkan nomor invoice yang benar),
centang konfirmasi, lalu klik **Batalkan Invoice**. Role custom memerlukan izin
`invoices.view` dan **Batalkan Invoice** (`invoices.cancel`). Role dengan seluruh
izin langsung memiliki akses.

Invoice menjadi `void` dan sisa tagihannya nol. Nomor, nominal asli, item,
proforma sumber, biaya aktual, serta dokumen pekerjaan tetap tersimpan. Status
event tidak berubah: pembatalan tagihan duplikat tidak membatalkan event.
Alasan, pengguna, waktu, snapshot, dan hasil tersimpan secara transaksional di
`crm_data_corrections` dengan jenis `invoice_cancel`. Halaman detail dan PDF
menampilkan tanda VOID dan alasan pembatalan. Invoice tidak dapat diaktifkan atau
dibatalkan ulang.

Financial Report memakai pengecualian VOID yang sudah ada. Invoice ini keluar
dari total tagihan, piutang, jumlah/status invoice, rincian invoice, posisi proyek,
grafik pendapatan, Top Product, dan CSV. Dashboard Finance Sales dan komisi juga
mengecualikannya. Contoh: dua invoice Rp3.750.000 menjadi total Rp7.500.000;
setelah salah satunya dibatalkan, hanya Rp3.750.000 yang terhitung. Biaya aktual
tetap dihitung sesuai tanggal transaksi dan pada posisi proyek yang masih aktif.

## Bila pembatalan belum tersedia

Halaman menampilkan alasan penolakan dan tidak menampilkan form konfirmasi jika:

- Invoice bukan UNPAID atau masih memiliki baris pembayaran aktif. Periksa uang
  yang benar-benar diterima. Gunakan **Void Pembayaran** terlebih dahulu hanya
  untuk pencatatan yang salah/duplikat. Pembatalan invoice tidak memindahkan uang
  ke invoice lain dan tidak mencatat refund.
- Ada invoice pelunasan atau proforma aktif yang bergantung pada invoice DP.
  Tinjau dan batalkan dokumen turunannya dahulu. Invoice lain pada quote yang
  sama tidak menghalangi pembatalan invoice duplikat yang berdiri sendiri.
- Ada PO/SPK/surat jalan yang belum selesai/dibatalkan atau alokasi alat aktif.
  Selesaikan alur operasional sesuai kondisi sebenarnya. Pembatalan invoice
  tidak mengubah stok atau menghentikan pekerjaan secara otomatis.
- Invoice terkait alur penggantian DP ke Full Payment, sudah dikoreksi, tanggal
  terbitnya tidak tersedia, atau periode tanggal terbit sudah dikunci.

Jika data berubah setelah halaman dibuka, aksi ditolak dan diarahkan kembali ke
preview terbaru. Pengguna perlu memeriksa ulang angka dan mencentang konfirmasi.
Audit dan perubahan status bersama-sama dibatalkan jika transaksi gagal.

## Deploy

Tidak ada migration, dependency, atau JavaScript baru. Setelah merge dan deploy
source melalui alur VPS yang biasa, segarkan cache:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Aktifkan izin **Batalkan Invoice** pada role custom yang bertanggung jawab.
Verifikasi dengan invoice percobaan: buat dua invoice, batalkan satu, lalu cocokkan
total tagihan/piutang, jumlah invoice, rincian dan CSV Financial Report. Pembatalan
invoice pelanggan dilakukan satu per satu berdasarkan dokumen yang benar.
