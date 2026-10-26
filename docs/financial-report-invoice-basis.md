# Financial Report: invoice resmi dan pemantauan proforma

Aturan finance: **nilai resmi mengikuti invoice**. Proforma hanya dokumen perantara untuk ditindaklanjuti, sehingga nilainya tidak masuk total invoice, piutang, uang diterima, margin, Top Product, maupun angka ekspor Financial Report.

## Angka resmi

- **Total Invoiced:** nilai invoice confirmed yang terbit pada periode terpilih. Invoice DP hanya menyumbang nominal DP, pelunasan hanya nominal pelunasan. Sisa nilai penawaran tidak diakui. Kartu/kolom Unbilled berbasis quote dihapus dari Financial Report.
- **Cash Received / Expense:** pembayaran dan biaya yang tercatat pada invoice, berdasarkan tanggal transaksi. Transaksi untuk invoice dari bulan lain tetap masuk. Rincian kas pada halaman memuat seluruh transaksi tersebut agar bisa direkonsiliasi dengan ringkasan. CSV Financial Report hanya berisi daftar invoice menurut tanggal terbit.
- **Receivable:** saldo saat ini dari invoice confirmed yang terbit pada periode terpilih. Ini bukan seluruh piutang perusahaan dan bukan saldo historis penutupan periode. Pembayaran bulan berikutnya mengurangi saldo saat laporan lama dibuka kembali; label menjelaskan hal ini.
- **Cash Margin:** uang diterima dikurangi biaya pada periode transaksi yang dipilih.
- **Posisi Proyek dari Invoice:** pilih proyek yang memiliki invoice confirmed pada periode/filter terpilih, lalu gabungkan seluruh invoice yang sudah terbit untuk proyek tersebut. Total dan margin hanya memakai nilai invoice confirmed. Biaya dari seluruh invoice proyek dihitung satu kali, termasuk biaya pada invoice yang kemudian dibatalkan. Proforma dan sisa nilai quote tidak disertakan. Ini posisi proyek saat ini lintas periode, bukan penutupan bulanan.
- **Invoice Performance:** rincian masing-masing invoice menurut tanggal terbit, tanpa invoice VOID atau event Cancel. Aturan yang sama berlaku untuk jumlah invoice, status pembayaran, dan CSV. Label margin/biaya menjelaskan bahwa nilainya milik invoice tersebut, bukan keseluruhan proyek.
- **Top Product:** produk, unit bisnis, nominal, dan alokasi pembayaran berasal dari snapshot invoice, bukan quote terbaru. Setiap invoice menyumbang nominal pada bulan penerbitannya. DP dan pelunasan proyek yang sama dihitung satu proyek per produk dalam periode terpilih; kuantitas yang ditagihkan ulang tidak digandakan. Pembayaran pada Top Product adalah yang diterima sampai saat ini pada invoice terpilih, bukan kas per bulan.

CSV terbaru mengikuti [format ringkas tim finance](financial-export-finance-columns.md), termasuk Company dan nomor telepon pemesan. Kolom PPH tidak ditampilkan; perhitungan sisa hutang tetap mengikuti nilai kas yang harus dibayar setelah PPH dan pembayaran aktif.

## Pembatalan invoice

- Untuk invoice duplikat, gunakan **Batalkan Invoice** pada halaman detail. Aksi ini memberi status VOID tanpa mengubah status event; alasan dan riwayatnya tersimpan. Lihat [panduan pembatalan invoice](invoice-cancellation.md) untuk izin, syarat, dan langkah deploy.
- Invoice VOID, termasuk DP yang diganti ke Full Payment, tidak ikut nilai tagihan, piutang, jumlah invoice, rincian invoice, maupun export. Dalam penggantian DP Rp3 juta menjadi Full Payment Rp10 juta, hanya invoice baru Rp10 juta yang dihitung.
- Bila status event diubah menjadi **Cancel**, tagihan dan piutangnya keluar dari laporan. Rincian dan export juga mengecualikannya meskipun filter **Semua status event** dipilih. Ejaan legacy `cancelled` dan `canceled` mengikuti aturan yang sama.
- Dokumen asli dan nominalnya tetap disimpan untuk riwayat; perubahan laporan tidak menghapus invoice. Status Cancel tetap berarti pembatalan event. Gunakan **Ganti ke Full Payment** jika event berlangsung dan hanya jenis tagihannya yang berubah.
- Pembayaran sah yang sudah diterima dan biaya aktual tetap tercatat pada arus kas sesuai tanggal transaksinya. Cancel tidak otomatis berarti uang dikembalikan. Filter **Cancel** digunakan untuk meninjau arus kas event yang dibatalkan, tanpa memasukkan invoice itu kembali ke daftar atau total tagihan.

Contoh: dua invoice confirmed Rp10 juta dan Rp3 juta menghasilkan total Rp13 juta. Jika invoice Rp3 juta dibatalkan, total tagihan dan daftar invoice menjadi Rp10 juta. Bila sebelumnya ada penerimaan Rp1 juta dan biaya Rp500 ribu pada invoice yang dibatalkan, kedua transaksi tersebut tetap ada di rincian kas.

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

CSV Financial Report sekarang hanya memuat header dan satu baris per invoice menurut tanggal terbit, mengikuti format ringkas tim finance. Ringkasan, rincian kas, dan posisi proyek tetap tersedia di halaman laporan. Urutan dan arti kolom serta langkah deploy perubahan export dijelaskan dalam [panduan format export](financial-export-finance-columns.md).

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
