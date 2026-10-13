# Financial Report: invoice resmi dan pemantauan proforma

Aturan finance: **nilai resmi mengikuti invoice**. Proforma hanya dokumen perantara untuk ditindaklanjuti, sehingga nilainya tidak masuk total invoice, piutang, uang diterima, margin, Top Product, maupun angka ekspor Financial Report.

## Angka resmi

- **Total Invoiced:** nilai invoice confirmed yang terbit pada periode terpilih. Invoice DP hanya menyumbang nominal DP, pelunasan hanya nominal pelunasan. Sisa nilai penawaran tidak diakui. Kartu/kolom Unbilled berbasis quote dihapus dari Financial Report.
- **Cash Received / Expense:** pembayaran dan biaya yang tercatat pada invoice, berdasarkan tanggal transaksi. Transaksi untuk invoice dari bulan lain tetap masuk. Rincian kas pada halaman dan CSV memuat seluruh transaksi tersebut agar bisa direkonsiliasi dengan ringkasan.
- **Receivable:** saldo saat ini dari invoice confirmed yang terbit pada periode terpilih. Ini bukan seluruh piutang perusahaan dan bukan saldo historis penutupan periode. Pembayaran bulan berikutnya mengurangi saldo saat laporan lama dibuka kembali; label menjelaskan hal ini.
- **Cash Margin:** uang diterima dikurangi biaya pada periode transaksi yang dipilih.
- **Posisi Proyek dari Invoice:** pilih proyek yang memiliki invoice confirmed pada periode/filter terpilih, lalu gabungkan seluruh invoice yang sudah terbit untuk proyek tersebut. Total dan margin hanya memakai nilai invoice confirmed. Biaya dari seluruh invoice proyek dihitung satu kali, termasuk biaya pada invoice yang kemudian dibatalkan. Proforma dan sisa nilai quote tidak disertakan. Ini posisi proyek saat ini lintas periode, bukan penutupan bulanan.
- **Invoice Performance:** tetap rincian masing-masing invoice menurut tanggal terbit. Label margin/biaya menjelaskan bahwa nilainya milik invoice tersebut, bukan keseluruhan proyek.
- **Top Product:** produk, unit bisnis, nominal, dan alokasi pembayaran berasal dari snapshot invoice, bukan quote terbaru. Setiap invoice menyumbang nominal pada bulan penerbitannya. DP dan pelunasan proyek yang sama dihitung satu proyek per produk dalam periode terpilih; kuantitas yang ditagihkan ulang tidak digandakan. Pembayaran pada Top Product adalah yang diterima sampai saat ini pada invoice terpilih, bukan kas per bulan.

Company dan kolom PPH tetap ada pada detail ekspor invoice. PPH masih kolom kosong untuk diisi manual, tanpa kalkulasi atau potongan pajak otomatis.

## Pemantauan Caca / Admin Sales

Panel **Proforma Belum Menjadi Invoice** tersedia di Financial Report dan menu Proforma, terpisah dari angka resmi. Panel menghitung nominal dokumen proforma, bukan seluruh nilai penawarannya.

- Hanya status `issued` yang belum terhubung ke invoice. Dokumen converted/cancelled keluar dari pemantauan.
- Jumlah, nilai proforma, dan jumlah yang melewati jatuh tempo proforma.
- Nomor, tanggal, proyek/company, jenis DP/pelunasan/penuh, nominal, umur, pembuat, serta tautan detail untuk ditindaklanjuti.
- Di Financial Report, tampilkan 10 tertua dan tautan ke semua yang tertunda. Panel mengikuti filter unit/produk dari snapshot proforma, tetapi tidak dibatasi bulan/tahun/status event laporan agar dokumen lama tetap terlihat.
- Daftar Proforma dapat difilter `Belum menjadi Invoice`, `Sudah menjadi Invoice`, atau `Dibatalkan`. Daftar tertunda mengurutkan yang tertua lebih dahulu.
- Izin daftar `invoices` diperlukan untuk panel/daftar; `invoices.view` untuk tautan detail. Konversi tetap memerlukan `invoices.generate`, dan pencatatan pembayaran memerlukan `invoices.payment`. Tidak ada bypass berdasarkan nama Caca atau role.
- Panel proforma tidak dimasukkan dalam CSV keuangan resmi. Pemantauan tidak mengubah status atau membuat invoice otomatis.

## Contoh satu event Rp10 juta

Angka kumulatif untuk transaksi dalam satu periode:

| Tahap | Total invoice | Diterima | Piutang invoice | Proforma tertunda (monitor saja) |
| --- | ---: | ---: | ---: | ---: |
| Proforma DP Rp3 juta | 0 | 0 | 0 | 3 juta |
| DP menjadi invoice karena deal | 3 juta | 0 | 3 juta | 0 |
| DP dibayar | 3 juta | 3 juta | 0 | 0 |
| Proforma pelunasan Rp7 juta | 3 juta | 3 juta | 0 | 7 juta |
| Pelunasan menjadi invoice | 10 juta | 3 juta | 7 juta | 0 |
| Pelunasan dibayar | 10 juta | 10 juta | 0 | 0 |

Jika biaya Rp6 juta dicatat pada invoice DP, setelah kedua invoice terbit posisi proyek memiliki total invoice Rp10 juta, biaya Rp6 juta, dan margin Rp4 juta. Sebelum invoice pelunasan terbit, basis nilai resmi tetap Rp3 juta.

## Ekspor dan deploy

CSV Financial Report sekarang memuat ringkasan, rincian kas berdasarkan tanggal transaksi, posisi proyek saat ini, lalu detail invoice berdasarkan tanggal terbit. Label basis periode dicantumkan agar bagian yang berbeda tidak dijumlahkan menjadi satu total. Pemakai parser CSV perlu memperhatikan bagian baru dan label margin invoice yang diperjelas.

Tidak ada migration atau dependency baru. Setelah merge, ikuti backup/maintenance/update source pada panduan VPS, jalankan build Admin untuk style panel baru, lalu segarkan cache:

```bash
npm run build --prefix packages/Webkul/Admin
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Uji dengan data percobaan, bukan dokumen pelanggan: proforma saja, konversi deal tanpa uang masuk, DP/pelunasan, invoice Oktober dibayar November, quote direvisi setelah proforma, pembatalan proforma, dan role yang tidak mempunyai izin invoice. Pastikan nilai resmi tidak berubah hanya karena proforma dibuat.

## Verifikasi

- Sembilan pengujian tambahan meliputi pengecualian proforma dari laporan/ekspor, tahap Rp3 juta/Rp7 juta, biaya proyek gabungan, snapshot setelah perubahan quote, kas lintas bulan, Top Product sesuai bulan invoice, monitoring proforma lama dan filter, izin/link, serta render HTTP halaman.
- Suite penuh: 191 lulus, 6 gagal dan 16 risky yang sama dengan baseline (QR/alamat quote dan fixture instalasi/login/database). Tidak ada kegagalan baru.
- Build production Vite, kompilasi Blade, Pint, dan `git diff --check` berhasil. Warning chunk Vite yang sudah ada tetap muncul.
- Database pengujian memakai SQLite in-memory, bukan data VPS. Halaman diuji melalui HTTP/render Laravel; belum ada pengujian browser interaktif di VPS.
- Validator skills tetap gagal karena `.github/skills` tidak tersedia pada baseline.
