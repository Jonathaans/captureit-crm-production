# Format export Financial Report untuk finance

CSV Financial Report berisi satu tabel: header pada baris pertama, lalu satu baris per invoice. Tidak ada judul laporan, ringkasan, rincian kas, posisi proyek, baris kosong, atau kolom biaya/margin/PPH tambahan.

## Urutan kolom

| No. | Kolom | Sumber / arti |
| --- | --- | --- |
| 1 | Project Code | Kode proyek invoice |
| 2 | Business Unit | Nama unit bisnis invoice |
| 3 | No Invoice | Nomor invoice |
| 4 | Jenis Invoice | Jenis penagihan invoice, misalnya DOWN PAYMENT atau FULL PAYMENT |
| 5 | Tanggal Inv | Tanggal terbit invoice, misalnya 3 Oct 2026 |
| 6 | Nama Customer | Identitas pemesan Bill To; mengikuti koreksi nama kontak dan fallback snapshot yang sudah digunakan invoice |
| 7 | Company | Company Bill To, tepat setelah Nama Customer |
| 8 | No. Telepon Pemesan | Nomor dari kontak pemesan; beberapa nomor unik dipisahkan `; ` dalam satu sel |
| 9 | Nama Event | Subject invoice |
| 10 | Tanggal Event | Tanggal event invoice; fallback tanggal quotation jika kosong |
| 11 | Produk | Nama produk unik dari snapshot item invoice |
| 12 | Status Project | Status event invoice |
| 13 | Status Pembayaran | Status pembayaran invoice |
| 14 | Nilai Invoice | Grand total invoice |
| 15 | Nilai Pembayaran | Total pembayaran aktif invoice sampai saat export |
| 16 | Nilai Pembayaran Periode | Pembayaran aktif invoice tersebut dengan tanggal transaksi dalam periode filter |
| 17 | Sisa Hutang | Saldo saat ini sesuai perhitungan laporan: nilai kas setelah PPH dikurangi pembayaran aktif, minimum nol; nol untuk event non-confirm |
| 18 | Sales | Sales pada invoice; fallback sales quotation jika tidak tersedia |

Nomor telepon tidak dikonversi ke angka. CSV memberi awalan apostrof sebagai penanda teks untuk mempertahankan nol awal dan kode negara seperti +62. Saat mengimpor CSV, gunakan tipe teks untuk kolom ini. Kontak quotation hanya dipakai untuk invoice lama tanpa kontak dan tanpa snapshot penerima sendiri; nomor customer lain tidak dipakai untuk mengisi kontak invoice yang kosong. Nomor telepon, tanggal event, dan sales yang tidak tersedia dibiarkan kosong.

Dua kolom pembayaran pada contoh diberi nama berbeda agar total kumulatif dan pembayaran periode dapat dibedakan. Contoh: invoice Oktober Rp1.000 dibayar Rp300 pada Oktober dan Rp200 pada November menghasilkan Nilai Pembayaran Rp500 dan Nilai Pembayaran Periode Rp300 ketika export Oktober. Invoice tersebut tidak muncul di export November, karena pemilihan invoice mengikuti tanggal terbit. Kas November tetap terlihat pada dashboard.

## Perilaku yang dipertahankan

- Filter tahun, bulan, business unit, status event, dan produk tetap berlaku. Export diurutkan dari tanggal invoice tertua; ID invoice menjadi pembeda urutan pada tanggal yang sama.
- Invoice belum terbit, invoice VOID, event Cancel (termasuk ejaan legacy `cancelled`/`canceled`), serta quotation/proforma yang belum menjadi invoice tidak ikut export, termasuk saat memilih semua status event. Pembayaran void tidak ikut kedua kolom pembayaran maupun saldo.
- Pembatalan tidak menghapus dokumen asli. Pembayaran sah dan biaya aktual pada event yang dibatalkan tetap tersedia pada rincian kas di halaman laporan. Filter Cancel menghasilkan CSV header saja karena tidak ada tagihan aktif dari event tersebut.
- Nama file, format CSV pemisah titik koma, dan UTF-8 BOM tetap digunakan. Jika tidak ada invoice yang cocok, export hanya berisi header.
- Dashboard, perhitungan keuangan, export Produk Teratas, export expense terpisah, dan izin export tetap mengikuti alur yang sudah ada.

## Deploy dan pemeriksaan

Tidak ada migration, dependency baru, atau perubahan aset frontend. Setelah merge dan update source di VPS, jalankan:

```bash
php artisan optimize:clear
```

Login dengan izin Financial Report export, pilih filter, lalu pilih Export → Financial Report. Periksa 18 kolom, company di sebelah customer, nomor telepon, tanggal event, sales, serta contoh pembayaran lintas bulan. Export Produk Teratas tetap tersedia terpisah.

Pengujian otomatis mencakup bentuk dan urutan kolom, CSV Unicode/pemisah/kutip/newline, identitas dan kontak pemesan, tanggal/sales fallback, filter dan hasil kosong, urutan invoice, pembayaran lintas periode, PPH, serta invoice/pembayaran void. Fixture SQLite memakai data sintetis, bukan database VPS.
