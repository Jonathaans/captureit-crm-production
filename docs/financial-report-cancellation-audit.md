# Audit seluruh jalur pembatalan dan Financial Report

Rute halaman `admin/invoices/financial-report` memakai
`FinancialReportController@index`; CSV memakai controller yang sama melalui
`export`. Metode laporan lama pada `InvoiceController` tidak terhubung ke kedua
rute ini. Tidak ada cache nominal atau counter yang perlu dikurangi manual.

## Basis angka dan dampak pembatalan

| Jalur | Sumber/periode | Dampak invoice VOID |
| --- | --- | --- |
| Total invoice | `grand_total` invoice confirmed menurut `issued_at` | Nilai invoice batal dikeluarkan seluruhnya |
| Piutang saat ini | Maksimum nol dari nilai invoice dikurangi PPh dan seluruh pembayaran aktif sampai saat laporan dibuka | Saldo invoice batal tidak terhitung |
| Jumlah/status/rincian invoice dan CSV | Invoice pada periode terbit terpilih | Invoice batal tidak muncul; dokumen asli tetap ada pada daftar invoice untuk riwayat |
| Grafik tahunan | Tagihan menurut bulan terbit, uang masuk menurut `paid_at`, biaya menurut `expense_date` | Tagihan keluar dari bulan terbit asli, bukan bulan pembatalan |
| Posisi proyek | Seluruh invoice terbit untuk proyek yang memiliki invoice confirmed pada filter terpilih | Tagihan/piutang VOID keluar; biaya aktual proyek tetap dihitung satu kali |
| Top Product dan ekspor produk | Snapshot item setiap invoice, alokasi nilai invoice, satu hitungan proyek/produk untuk DP dan pelunasan | Nilai invoice batal keluar; penerimaan produk sampai saat ini berbeda dengan arus kas periode |
| Uang masuk dan rincian kas | Pembayaran aktif pada tanggal transaksi, termasuk invoice yang terbit di tahun/bulan lain | Pembayaran pada invoice VOID dan pembayaran yang telah di-void tidak dihitung |
| Pengeluaran/kategori/ekspor biaya/surplus kas | Biaya aktual pada tanggal transaksi; surplus = penerimaan dikurangi biaya | Biaya aktual tidak dihapus ketika invoice dibatalkan |
| Finance Sales, aging, portfolio, dan CSV penagihan | Invoice confirmed yang belum lunas, setelah PPh/pembayaran aktif | Invoice VOID keluar |
| Komisi | Invoice aktif untuk deal yang telah ditagihkan dan lunas sesuai kebijakan | Invoice VOID tidak menambah nilai deal atau komisi |

Karena basis periode dan PPh berbeda, **Total invoice − Uang masuk periode**
tidak selalu sama dengan **Piutang saat ini**. Pembayaran tahun berikutnya
mengurangi piutang invoice tahun sebelumnya, tetapi tidak menambah uang masuk
pada laporan tahun sebelumnya.

Empat jalur telah ditelusuri: tombol Batalkan Invoice (tanpa pembayaran aktif),
command `crm:invoice-void` untuk pembayaran fiktif, perubahan status event Cancel,
serta penggantian DP ke Full Payment. Event Cancel tetap mempertahankan uang
yang benar-benar diterima; bukan refund. Penggantian DP Rp3 juta ke Full Payment
Rp10 juta menghasilkan total Rp10 juta, bukan Rp13 juta.

## Pemeriksaan baca-saja pada VPS

### Tampilan pada Operations Dashboard

Buka **Operational Dashboard → Audit Financial Report** sebagai Administrator
yang memiliki izin `operations-dashboard`. Pembatasan Administrator berlaku
di backend untuk halaman dan unduhan JSON, meskipun role lain mendapat izin
Operations Dashboard secara tidak sengaja.

1. Pilih tahun, bulan, unit usaha, status event, dan nama produk yang sama dengan
   Financial Report. Isi nomor invoice jika ingin memeriksa dokumen tertentu.
2. Klik **Jalankan Audit**. Membuka dashboard biasa tidak menjalankan rekonsiliasi.
3. Periksa indikator cocok/selisih, seluruh kelompok pemeriksaan, nilai transaksi
   sumber, nilai laporan, dan selisih. Semua pemeriksaan dari command ditampilkan;
   daftar ID/nomor invoice dapat dibuka seluruhnya. Nominal memakai empat desimal.
4. **Unduh Audit JSON Terbaru** menjalankan ulang engine yang sama dengan filter
   yang sama, lalu mengunduh hasil dan waktu pemeriksaan. Jika transaksi berubah
   setelah halaman dibuka, hasil unduhan dapat berbeda dari tampilan sebelumnya.
5. **Riwayat Pembatalan & Void Invoice** memuat `invoice_cancel` dan `invoice_void`
   dari catatan koreksi yang sudah tersimpan: waktu, pelaku/ID pengguna, alasan,
   nominal/status snapshot sebelum tindakan, piutang sebelum, dan saldo sekarang.
   Riwayat mencakup seluruh tanggal tindakan, tidak dibatasi filter periode laporan;
   cari nomor/alasan/pelaku dan gunakan halaman berikutnya untuk catatan lainnya.
   Bukti koreksi tetap tampil ketika invoice atau pengguna asal sudah tidak tersedia.

Hasil hijau berarti laporan cocok dengan transaksi yang tercatat; hasil tersebut
tidak membuktikan tidak ada duplikasi bisnis dengan nomor invoice berbeda.
Snapshot event yang sudah Cancel sebelum VOID juga tidak boleh dianggap sebagai
pengurangan baru dari tagihan Confirm. Dashboard menampilkan penjelasan ini.
Jika filter/nomor salah atau proses audit gagal, halaman menampilkan pesan gagal
dan tidak menampilkan hasil sukses.

Fitur ini tidak menyimpan riwayat eksekusi audit baru. Hasil rekonsiliasi dihitung
saat diminta; riwayat pembatalan berasal dari bukti transaksi yang sudah ada.
Tidak ada perubahan formula laporan atau penulisan data bisnis oleh fitur audit.

Setelah perubahan tampilan di-merge, perbarui source dan cache dari folder aplikasi:

```bash
cd /var/www/captureit-crm
git status --short
git fetch origin
git pull --ff-only origin main
composer dump-autoload --no-dev --optimize
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Pastikan checkout berada di `main` dan perubahan lokal sudah ditinjau sebelum pull.
Tidak ada migration, dependensi Composer baru, atau build frontend untuk tampilan
ini. Pemeriksaan rute unduhan:

```bash
php artisan route:list --name=admin.operations-dashboard.financial-audit
```

### Command terminal

Setelah source yang menambahkan command ini di-merge dan di-deploy, jalankan dari
folder aplikasi menggunakan user deployment biasa:

```bash
php artisan crm:financial-report-audit --year=2026
php artisan crm:financial-report-audit --year=2026 --invoice='NOMOR INVOICE YANG DIBATALKAN'
```

Default mencakup semua bulan, unit, status event, dan produk, sesuai tampilan
laporan tahunan tanpa filter tambahan. Untuk menyamakan dengan filter layar:

```bash
php artisan crm:financial-report-audit --year=2026 --month=10 --business-unit=capture_it --event-status=confirm --product='Photobooth' --invoice='NOMOR INVOICE'
```

Command hanya membaca. Tidak ada opsi apply, pembatalan tambahan, perubahan
status, perbaikan saldo, migration, atau penghapusan data. Jalankan saat input
relatif tenang agar transaksi yang bersamaan tidak mengganggu rekonsiliasi.

Output JSON membandingkan `expected` dari ledger SQL langsung dengan `actual`
dari controller laporan dan CSV yang dipakai aplikasi. Pemeriksaan mencakup
kartu, jumlah/status/ID invoice, rincian, kas, kategori biaya, grafik tahunan,
posisi proyek, dan CSV. Top Product, Finance Sales, serta komisi diverifikasi
melalui regression test terpisah; tidak termasuk perbandingan angka command ini.

- `ok: true`: seluruh pemeriksaan dalam command cocok dalam toleransi 0,0001.
- `ok: false`: ada selisih; command keluar dengan kode gagal. Periksa bagian
  `checks` yang mempunyai `ok: false`, jangan mengubah data secara manual.
- `read_only: true`: pemeriksaan tidak menulis data bisnis.
- Untuk invoice VOID: `included_in_invoice_report` harus `false`, `balance_due`
  nol, serta pemeriksaan `cancelled_invoice.included_in_details` dan
  `cancelled_invoice.included_in_csv` menunjukkan `actual: false`.
- `original_value` tetap menyimpan nominal dokumen asli. Nilai ini bukan
  kontribusi invoice ke total laporan sesudah dibatalkan.
- Jika riwayat koreksi tersedia, `confirmed_value_before_cancellation` dan
  `receivable_before_cancellation` menunjukkan posisi sebelum pembatalan.
  Piutang setelah PPh bisa lebih kecil dari nilai bruto invoice yang dibatalkan.

Simpan output pemeriksaan dan catatan total sebelum/sesudah untuk invoice yang
benar. Screenshot sesudah pembatalan saja tidak membuktikan perubahan pada
invoice tertentu tanpa nomor invoice, filter, dan data sebelumnya.

Total tagihan pada filter yang sama berkurang hanya jika invoice tersebut memang
terhitung sebelumnya: confirmed, terbit pada periode/filter itu, dan belum VOID
atau event Cancel. Membatalkan invoice Prospect atau invoice di luar filter tidak
mengurangi kartu Total invoice yang sudah mengecualikannya. Nilai sebelum koreksi
pada konteks invoice adalah nilai dokumennya; cocokkan periode/filter sebelum
menganggapnya sebagai selisih kartu periode terpilih.

## Regression

Skenario tambahan memeriksa angka sebelum/sesudah melalui aksi HTTP, controller
laporan, CSV invoice/produk/penagihan, dan ledger independen. Cakupan meliputi
bulanan/tahunan, unit/produk/status/kombinasinya, PPh, presisi empat desimal,
pembayaran lintas tahun, biaya pada invoice batal, DP/pelunasan dan penerbitan
ulang, koreksi melalui console, pembatalan event dengan penerimaan sah, dan
penggantian DP. Pengujian juga memverifikasi command tidak menulis data serta
mengembalikan kegagalan jika kartu laporan sengaja dibuat masih menghitung
invoice yang sudah dibatalkan.
