# Proforma, invoice DP, dan invoice pelunasan

## Alur finance

**Semua hasil generate dari quote masuk proforma terlebih dahulu**, termasuk DP, penuh/tanpa DP, dan pelunasan. Tidak ada penerbitan invoice langsung dari quote. Admin Sales memindahkan proforma ke Invoice saat event sudah deal/oke atau pembayaran sudah diterima.

| Tindakan | Hasil | Pembayaran / SPK |
| --- | --- | --- |
| Generate dari quote | Proforma DP, Penuh, atau Pelunasan sesuai pilihan | Belum ada invoice, payment, atau SPK baru. |
| Admin Sales: **Event sudah deal — pindahkan ke Invoice** | Invoice diterbitkan berstatus **UNPAID** | Tidak mencatat penerimaan uang. SPK dapat dibuat dari invoice. |
| Admin Sales: **Pembayaran diterima — pindahkan ke Invoice** | Invoice dan payment diterbitkan bersama, berstatus **PAID** | Memerlukan nominal, tanggal uang masuk, metode, serta izin mencatat pembayaran. |
| Pembayaran diterima setelah invoice terbit | Catat payment pada invoice yang sudah ada | Status menjadi PARTIAL atau PAID sesuai nominal aktual. Tidak memindahkan proforma lagi. |

Contoh penawaran Rp10 juta dengan DP Rp3 juta: buat proforma DP Rp3 juta → Admin Sales memindahkan menjadi invoice DP → proforma pelunasan Rp7 juta dibuat saat event akan berjalan atau sudah lewat → Admin Sales memindahkan menjadi invoice pelunasan. Total kedua invoice Rp10 juta. Jika DP sudah diterima saat konversi, invoice DP langsung PAID; jika event baru disetujui, invoice DP masih UNPAID sampai pembayaran dicatat.

Tanpa DP juga wajib lewat proforma penuh Rp10 juta. TOP menentukan pembayaran sebelum/sesudah event; invoice boleh diterbitkan setelah event disetujui meskipun belum dibayar. DP dan pelunasan tetap berbagi satu SPK.

Menu: **Invoices → Proforma** atau tombol **Buat Proforma** pada quote. Pilih jenis proforma, nominal DP bila diperlukan, TOP, serta tanggal jatuh tempo. Di detail proforma, Admin Sales memilih dasar pemindahan. Nomor proforma dan riwayatnya tetap tersimpan serta terhubung ke nomor invoice; siapa, kapan, dan alasan pemindahan dicatat.

Pelunasan diterbitkan manual sesuai perjanjian, bukan otomatis berdasarkan tanggal event. Proforma pelunasan tersedia setelah invoice DP dibayar. Tanggal penerimaan tidak boleh di masa depan atau sebelum proforma, dan periode keuangan tertutup tetap dilindungi. Proforma dapat dicetak/disimpan sebagai PDF melalui dialog cetak browser.

## Konsistensi data

- Proforma disimpan dalam tabel terpisah `proforma_invoices`, sehingga tidak ikut dihitung sebagai invoice aktual di Financial Report.
- Konversi proforma dan penerbitan invoice dibuat dalam satu transaksi; mode sudah dibayar juga mencatat payment di transaksi tersebut. Jika salah satunya gagal, seluruh konversi dibatalkan. Mengirim ulang konfirmasi tidak membuat invoice/payment kedua.
- Pada mode sudah dibayar, nominal penerimaan harus sama dengan nominal proforma. Untuk pembayaran bertahap, pindahkan dengan dasar event deal lalu catat pembayaran aktual pada Invoice. Untuk perubahan nominal atau detail, pengguna berizin memakai **Revisi Transaksi** agar quotation, proforma, dan invoice terkait tetap selaras; lihat [panduan revisi](linked-document-revisions.md). Proforma yang telah dikonversi tidak dapat dibatalkan melalui tombol ini.
- Nomor proforma memakai seri `PF`; invoice tetap memakai seri `INV`. Nomor invoice tidak digunakan saat proforma diterbitkan.
- Nilai penawaran, Bill To person/company, produk, jumlah peralatan, dan TOP disimpan saat proforma dibuat. Invoice DP dan pelunasan memakai snapshot tersebut. Perubahan melalui **Revisi Transaksi** memperbarui snapshot dan seluruh dokumen aktif secara atomik, dengan pratinjau dan riwayat sebelum/sesudah.
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
| Buat proforma dan pindahkan ke Invoice karena event deal/oke | `invoices.generate` |
| Pindahkan ke Invoice sekaligus catat pembayaran | `invoices.generate` **dan** `invoices.payment` |
| Batalkan proforma | `invoices.event-status` |
| Cetak proforma | `invoices.print` |
| Buat / buka SPK | Izin `work-orders` yang sudah ada |

Aktifkan izin daftar, detail, dan Generate Invoice pada role Admin Sales. Tambahkan Add Payment hanya jika Admin Sales juga boleh mencatat penerimaan uang. Izin berdasarkan checklist role, bukan nama role. Hak generate sekarang mengikuti checkbox **Generate Invoice**, termasuk pada URL form/POST langsung. Izin daftar invoice saja tidak lagi memberi hak generate.

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

Migration: `2026_10_12_030000_create_proforma_invoices_table` menambah tabel, dan `2026_10_12_133000_add_proforma_conversion_workflow` menambah jenis tagihan serta audit konversi. Migration kedua juga mendukung proforma DP dari versi awal fitur ini; invoice lama tidak dikonversi menjadi proforma. Deploy kode dan migration pada jendela maintenance yang sama. Untuk rollback kode setelah mulai dipakai, pertahankan tabel dan data proforma; jangan menjalankan rollback migration yang menghapus dokumen tersebut.

Setelah deploy, cek role Admin Sales dan finance, jalankan contoh Rp10 juta/Rp3 juta/Rp7 juta pada data uji, lalu periksa invoice, payment, SPK, Surat Jalan, dan Financial Report. Pastikan DP, tanpa DP, dan pelunasan masuk proforma terlebih dahulu. Cek bahwa pemindahan karena event deal menghasilkan UNPAID tanpa payment; pemindahan karena pembayaran diterima menghasilkan PAID dengan satu payment. Ulangi klik pemindahan dan pastikan tidak ada dokumen atau penerimaan ganda. Cek TOP sebelum/sesudah event. Jangan membuat pembayaran uji pada dokumen pelanggan sungguhan.

## Verifikasi pengembangan

- Pengujian baru mencakup proforma tanpa transaksi aktual, DP/pelunasan terpisah, total dan company tetap setelah quote berubah, TOP sebelum/sesudah event, SPK bersama, duplicate submit, rollback transaksi, pembatalan, validasi nominal/tanggal, periode tertutup, migration up/down, cetak proforma, dan izin HTTP.
- Suite regresi mencakup billing, unit/jumlah peralatan, Bill To, export laporan, ACL, SPK, koreksi data, dan equipment Surat Jalan.
- Suite penuh masih memiliki 6 kegagalan dan 16 risky test yang sudah ada pada baseline: assertion QR/alamat quotation serta feature test dengan database instalasi/user yang belum di-seed. Tidak ada kegagalan baru pada suite terarah.
- Suite fitur baru: 32 test lulus. Termasuk HTTP tombol dari quote, peran Admin Sales, pemindahan tanpa pembayaran, larangan memalsukan status PAID, jalur legacy yang wajib proforma, semua jenis tagihan, serta upgrade schema proforma versi awal.
- Build production Vite, Pint, dan kompilasi Blade berhasil. Aset hasil build lokal tidak disertakan; jalankan build saat deploy sesuai langkah di atas. Validator skills masih gagal karena `.github/skills` yang dirujuk `AGENTS.md` tidak tersedia dalam repo; ini juga terjadi pada baseline.

```bash
php vendor/bin/pest tests/Unit/ProformaInvoiceWorkflowTest.php --compact
php artisan view:cache
php vendor/bin/pint --test --dirty
git diff --check
```
