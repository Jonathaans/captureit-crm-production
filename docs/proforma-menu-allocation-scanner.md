# Menu Proforma dan perbaikan scan alokasi Surat Jalan

Proforma tampil sebagai menu tersendiri pada **Keuangan**, setelah Invoices. Menu ini memakai izin daftar `invoices` yang sudah digunakan halaman Proforma. Hak generate, konversi, dan pembayaran tetap diperiksa secara terpisah.

Pada **Surat Jalan → Manage Allocation Items**, operator dapat langsung scan atau mengetik Asset Code, Barcode, maupun Serial Number lalu menekan Enter / Tambahkan alat. Setiap kode harus mewakili satu alat fisik: permintaan 4 unit dipenuhi dengan 4 kode alat berbeda. Quantity items tetap menggunakan Prepared Quantity.

## Perbaikan

- Scanner baru diinisialisasi setelah Vue selesai memasang halaman. Referensi tampilan tidak lagi menunjuk ke elemen Blade yang telah diganti Vue.
- Scan beruntun masuk antrean berurutan. Input tetap aktif ketika permintaan sebelumnya diproses, dengan jumlah antrean dan hasil per kode yang terlihat.
- Respons yang belum selesai setelah 15 detik ditandai **PERLU DICEK**. Antrean berikutnya tetap diproses dan operator dapat menekan **Coba lagi**. Timeout bukan bukti bahwa server belum menyimpan transaksi; server mengenali alat yang sudah dialokasikan dan tidak menghitungnya dua kali.
- Hasil 20 input terakhir ditampilkan bersama seluruh input yang masih perlu dicek. Menyimpan quantity/reset ditahan selama antrean masih aktif, dan browser memberi konfirmasi ketika hendak meninggalkan halaman dengan antrean tersisa.
- Satu AudioContext dipakai ulang agar bunyi scanner tidak membuat konteks audio baru pada setiap scan.
- Server memilih requirement terbaru di dalam transaksi yang mengunci Surat Jalan. Query scan hanya membaca requirement dengan inventory item yang cocok, bukan memuat semua alat dalam Surat Jalan besar.
- Input manual menggunakan endpoint, permission `delivery-orders.inventory-allocation`, validasi kebutuhan, dan pemeriksaan stok yang sama. Alat non-available, alat pada event lain, kelebihan jumlah, serial number ambigu, serta Surat Jalan non-DRAFT ditolak. Audit actor dan stock movement tetap dicatat.

## Deploy setelah merge

Tidak ada migration baru pada perubahan ini. **Build frontend wajib dijalankan**, karena scanner sekarang berada dalam bundle aplikasi, lalu cache Blade/config disegarkan dan browser di-refresh. Ikuti urutan backup, maintenance, update source, dan restart proses pada `README.production-vps.md`.

Perintah build dan cache, dari folder aplikasi:

```bash
npm install --prefix packages/Webkul/Admin --include=dev
npm run build --prefix packages/Webkul/Admin
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Perubahan lokal pada `public/admin/build` di VPS merupakan hasil build. Cadangkan aset sebelum memulihkan file tracked yang menghalangi update Git; periksa perubahan source dan file lokal lainnya secara terpisah. Jangan mengikutsertakan file environment atau backup server ke commit.

## Pengecekan setelah deploy

1. Login dengan role yang memiliki izin Invoices: pastikan **Keuangan → Proforma** dapat dibuka. Role tanpa izin tersebut tidak mendapat menu Proforma.
2. Gunakan Surat Jalan uji DRAFT dengan permintaan 4 alat dan empat aset AVAILABLE. Scan keempat kode beruntun pada Surat Jalan yang sama: jumlah menjadi 4/4, semua hasil TERSIMPAN, dan baris menjadi COMPLETE.
3. Ulangi salah satu kode: tampil SUDAH TERSIMPAN, tetap 4/4. Ketik kode manual pada Surat Jalan uji lain yang sesuai dan pastikan hasilnya sama dengan scan.
4. Simulasikan koneksi lambat: hasil yang belum terkonfirmasi tetap dapat dicoba ulang tanpa harus pindah Surat Jalan. Scan aset kelima, aset dari event lain, dan serial number ambigu harus ditolak.
5. Periksa alokasi dan stock movement pada data uji. Perubahan ini hanya mengalokasikan alat; pengeluaran fisik tetap mengikuti proses release Surat Jalan.

## Pengujian pengembangan

```bash
php vendor/bin/pest tests/Unit/DeliveryOrderAllocationScanTest.php tests/Unit/AclMenuPermissionsTest.php --compact
node --test tests/JavaScript/allocation-scanner.test.mjs
npm run build --prefix packages/Webkul/Admin
php artisan view:cache
php vendor/bin/pint --test --dirty
```

Pengujian database menggunakan SQLite in-memory, sehingga tidak mengukur contention MySQL atau latency VPS. Pengujian antrean JavaScript menyimulasikan respons lambat/hilang, retry, scan beruntun, duplikasi, dan teardown. Scanner fisik dan tampilan browser tetap perlu smoke test pada perangkat gudang; browser Chromium tidak tersedia dalam lingkungan verifikasi ini.
