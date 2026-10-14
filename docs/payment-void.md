# Koreksi salah input pembayaran

Gunakan **Void Pembayaran** jika satu penerimaan salah dicatat atau tercatat dua
kali, sementara invoice masih merupakan tagihan yang sah. Fitur ini mengoreksi
pencatatan; bukan proses refund/pengembalian uang ke customer.

## Cara admin menggunakan

1. Buka **Invoice**, pilih invoice, lalu bagian **Payment History**.
2. Klik **Void Pembayaran** pada pembayaran yang salah.
3. Periksa nomor invoice, nominal, tanggal, referensi pembayaran, serta saldo dan
   status invoice sesudah koreksi.
4. Isi alasan, centang pernyataan salah pencatatan, lalu klik **Void Pembayaran**.
5. Baris lama tetap terlihat dengan label **VOID · tidak dihitung**, alasan,
   waktu, pengguna, dan nomor audit. Input pembayaran yang benar pada invoice
   yang sama jika ada uang yang benar-benar diterima. Pakai nominal dan tanggal
   penerimaan sebenarnya.

Pengguna membutuhkan izin **Invoices → View** dan **Invoices → Void Pembayaran**
(`invoices.view` dan `invoices.payment-void`). Izin Add Payment saja tidak memberi
izin membatalkan. Role dengan akses `all` mendapat akses seperti aksi lainnya;
role custom perlu diberi izin baru melalui pengaturan Roles.

## Dampak laporan

| Data | Hasil |
| --- | --- |
| Cash Received | Berkurang sebesar pembayaran yang di-void pada periode tanggal pembayaran tersebut |
| Total Invoiced | Tetap, karena tagihan masih berlaku |
| Paid Amount | Dihitung ulang dari pembayaran yang masih aktif |
| Outstanding / sisa tagihan | Dihitung ulang dari nilai invoice dikurangi pembayaran aktif |
| Status invoice | UNPAID bila tidak ada pembayaran aktif; PARTIAL bila belum lunas; PAID bila masih lunas |
| Riwayat pembayaran | Tetap tersimpan, pembayaran VOID dikecualikan dari perhitungan |
| SPK, DO, alokasi, dan stok | Tidak diubah atau dihapus |

Financial Report, rincian arus kas, export, dashboard Finance Sales, Top Product,
dan perhitungan komisi memakai pembayaran aktif. Contoh: pembayaran September
yang salah pada invoice Oktober mengurangi Cash Received September; Total
Invoiced Oktober tetap dan sisa tagihan bertambah.

Proforma yang sudah dikonversi tetap menjadi riwayat konversi. Mengulangi
konversi tidak menghidupkan kembali pembayaran VOID. Invoice DP/pelunasan dan
hubungan ke SPK/DO tetap ada.

Jika invoice juga salah dan perlu dibatalkan, gunakan alur terpisah
[void invoice](invoice-void-completed-event.md). Command tersebut tetap
memeriksa SPK/DO dan dependensi keuangan. Pembayaran yang sudah VOID tidak
dihitung dua kali. Command koreksi lama yang menghapus invoice ditolak ketika
ada riwayat Void Pembayaran.

## Pemeriksaan sebelum perubahan disimpan

Sistem menolak periode tanggal pembayaran atau tanggal terbit invoice yang
terkunci, invoice yang sudah VOID, pembayaran dari invoice lain, koreksi
berulang, saldo pembayaran yang tidak cocok, dan preview yang sudah berubah.
Alasan dan konfirmasi wajib diisi. Pengguna audit diambil dari sesi login.
Snapshot, audit, dan saldo baru disimpan dalam satu transaksi database dengan
penguncian invoice; kegagalan membatalkan seluruh perubahan.

## Deploy

Perubahan membutuhkan migration baru `2026_10_13_150000_create_payment_voids_table`.
Siapkan backup database dan lakukan deploy pada maintenance window singkat.
Setelah PR di-merge, dari folder aplikasi VPS:

```bash
git pull --ff-only origin main &&
composer dump-autoload -o &&
php artisan migrate --force &&
php artisan optimize:clear
```

Migration membuat tabel audit baru dan tidak mengubah pembayaran yang ada.
Aktifkan izin role, lalu refresh/login ulang bila perlu. Uji di staging dengan
invoice contoh sebelum koreksi data produksi.

Rollback migration ditolak jika audit sudah terisi. Setelah ada pembayaran
VOID, rollback kode ke versi yang belum memahami `payment_voids` akan menghitung
kembali pembayaran tersebut. Pertahankan tabel audit dan pengecualian laporan
saat melakukan perbaikan berikutnya.
