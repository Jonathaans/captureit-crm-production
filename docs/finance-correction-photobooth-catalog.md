# Koreksi finance dan katalog Photobooth

Perubahan kode ini tidak menjalankan koreksi data saat deploy atau migrate. Ketiga command menggunakan preview secara default; hanya `--apply` yang mengubah data. Jalankan preview di lokal dengan salinan database dan review hasilnya sebelum menggunakan data production.

## Pemeriksaan awal tanpa deploy

`tools/review_finance_catalog.php` hanya membaca daftar produk, inventory, jumlah aset, template, dan invoice `INV 2610-0010`. Script dapat dijalankan dari root aplikasi dengan `php tools/review_finance_catalog.php`, atau lewat stdin setelah mengambil file dari commit yang sudah direview. Tidak menampilkan kredensial, billing address, atau bukti pembayaran.

Screenshot menampilkan 85 produk, termasuk produk berkategori kosong. Rencana katalog memakai SEMUA produk saat command dijalankan, mengurutkan ID naik menjadi PRD-0001 dst dan mengisi kategori Photobooth. Review seluruh daftar sebelum apply, termasuk produk tambahan dan Videographer. Harga, product ID, kode/serial aset, snapshot quote/invoice, dan surat jalan lama tetap menggunakan data masing-masing.

Pemilik kemudian menambahkan produk AI Generative dan akan mengedit template produk baru itu manual. Mapping hanya mengubah template pada product ID yang tercantum; produk AI Generative baru ikut penomoran SKU/kategori tetapi template-nya tidak diganti. Selalu ambil preview baru karena penambahan produk atau perubahan template membatalkan fingerprint lama.

## Deploy kode untuk pengujian

Gunakan branch PR, PHP 8.3+, dan database lokal. Setelah perubahan diambil:

```sh
composer dump-autoload -o
php artisan migrate
php artisan test --compact --filter=CrmDataCorrectionsTest
php artisan crm:catalog-review
php artisan crm:invoice-correct 'INV 2610-0010'
php artisan crm:photobooth-catalog --mapping=docs/photobooth-production-mapping-20261007.draft.json
```

Migration membuat tabel audit serta kolom aturan jumlah dan kebutuhan inventory. Default kolom mempertahankan perhitungan dan perilaku dokumen lama; migrate tidak menjalankan penataan katalog atau penghapusan invoice. Dalam production, gunakan prosedur backup dan maintenance yang biasa dipakai; lakukan apply saat aplikasi dalam maintenance dan worker yang bisa menulis data sudah dihentikan. Seluruh produk/inventory dikunci dalam transaksi selama pembaruan katalog.

## Invoice

Preview menampilkan nominal payment aktual, fingerprint, quote, dan jumlah dokumen terkait. Invoice dengan expense, SPK, surat jalan, PO, invoice pelunasan terkait, atau invoice aktif lain untuk quote yang sama ditolak sampai dependensinya ditinjau. Koreksi tidak menghapus dokumen tersebut secara otomatis.

Apply memerlukan fingerprint terbaru, ID pengguna CRM pelaksana, dan alasan:

```sh
php artisan crm:invoice-correct 'INV 2610-0010' --apply --expect=FINGERPRINT_PREVIEW --actor=ID_USER --reason='Salah generate invoice dan salah input payment'
```

Operasi mengarsipkan seluruh invoice, items dan payments ke `crm_data_corrections`, lalu menghapus ketiganya secara atomik. Payment Received turun mengikuti jumlah payment dan periode paid_at aslinya; nilai invoice aktif juga berkurang. Quote tetap tersedia dan eligibility billing dihitung ulang dari invoice yang tersisa. Nomor invoice lama tetap dicadangkan dalam kedua generator. UI editing biasa tetap tunduk pada aturan dokumen terkunci. Ini command maintenance, bukan tombol hapus umum untuk finance.

Riwayat audit adalah sumber pemulihan manual; tidak ada tombol undo otomatis. Rollback migration menolak membuang tabel audit yang sudah berisi data.

## Pemetaan template dan inventory

Definisi terletak di `packages/Webkul/Admin/src/Config/photobooth-catalog.php`. Delapan template utama wajib terpetakan. Template tambahan Lensa Hologram bersifat opsional pada produk Additional yang sudah ada; Hologram Photobooth sendiri wajib memakai lensa lenticular. Produk tambahan baru memerlukan data komersial tersendiri dan tidak dibuat dengan harga tebakan.

- Perleng = Kabel Roll: 3 unit serialized pada semua paket utama.
- Ribbon reguler dan Corporated: dua master consumable terpisah, masing-masing 2 roll pada paket yang memakainya.
- Barang tanpa qty dianggap 1 unit. TL 120 sudah dikonfirmasi satu unit per QR.
- Classic Box dipakai untuk Lite Box, Pro Branded Box, dan Photobox. Flash Take Me Away menggunakan YN 560 III.
- Stand Lighting = TAKARA, Magic Arm = CLAMP ARM, dan Magic Clamp = CLAMP KECIL, sesuai konfirmasi pemilik.
- Laptop untuk Take Me Away dan AI Generative dipilih manual pada Surat Jalan sebelum scan QR. Stand TV default Tripod TV, boleh diganti ke Stand TV Cart sebelum alokasi.
- Lensa Hologram adalah lenticular consumable quantity/lembar: satu lembar per cetakan. Mapping produk 39/40/41 menggunakan 100/200/300 lembar per paket. Produk 42 meminta jumlah pesanan di Surat Jalan. Template add-on menggunakan quantity penjualan dalam pcs, bukan jumlah alat.
- AI Generative membutuhkan 5 stand lighting; High Angle Box mengikuti daftar tanpa lensa wide.
- Dasar jumlah template: `equipment` per paket/unit alat (perilaku lama), `sales` per quantity penjualan pcs, `manual` diisi pada Surat Jalan. Satuan penjualan day tidak dikonversi menjadi lembar; jumlah perlu diisi manual. Membuat master baru tidak membuat unit aset atau stok fisik.

Pencocokan otomatis hanya memakai nama/alias yang tepat setelah normalisasi spasi/tanda baca. Nama ambigu, barang tidak aktif, tipe tracking berbeda, atau satuan berbeda menghentikan apply. Tentukan ID secara eksplisit bila nama production berbeda. Satu template dapat digunakan beberapa produk paket:

```json
{
  "templates": {
    "classic": [101, 102],
    "additional_hologram_lens": [109]
  },
  "inventory": {
    "camera_700d": 15,
    "cable_roll": 27,
    "laptop": {"manual": true},
    "hologram_lens": {"create": {"code": "LENS-HOLO", "warehouse_id": 1}}
  }
}
```

ID di atas hanyalah contoh. Gunakan hasil pemeriksaan production. Mapping `create` hanya untuk master yang telah dipastikan belum ada; stok awalnya nol dan aset fisiknya harus didaftarkan lewat alur inventory. Tipe master lama tidak dikonversi otomatis.

Untuk production yang ditinjau pada 7 Oktober 2026, pemilik memilih mendaftarkan Tiang Gorden, Gorden Merah, Ribbon Corporated, Baterai 700D, Charger 700D, dan Background Putih secara manual nanti. Mapping tidak memakai `create`. Pilihan `{"manual": true}` menyimpan kebutuhan wajib dengan inventory ID kosong, sehingga penataan katalog dapat dilakukan sebelum pendaftaran master. Ini bukan penghapusan requirement: Surat Jalan tidak dapat dirilis sampai master, jumlah pesanan, dan alokasinya lengkap. Pilihan yang hilang tanpa `manual` eksplisit tetap menghasilkan error preview.

Setelah pendaftaran, hubungkan master pada Equipment Template di Edit Product dan pada Surat Jalan yang sudah terbuat. Untuk pengulangan command, ganti mapping manual dengan ID aktual terlebih dahulu agar pilihan master hasil edit UI tidak dikosongkan kembali. Master laptop dipilih per Surat Jalan; jangan mengganti master aset atau QR hanya untuk mengelompokkan semua laptop.

Override quantity per produk ditulis di `requirements`, misalnya `"39": {"hologram_lens": {"quantity": 100, "quantity_basis": "equipment"}}`. Preview menampilkan kebutuhan akhir per product ID dan warning untuk pilihan/jumlah manual. Qty manual awal 0 wajib diisi sebelum alokasi; tidak dianggap sebagai kebutuhan nol. Requirement manual yang belum diisi tidak digabung dengan requirement lain yang sudah memiliki jumlah.

Untuk menambahkan produk jual Additional Lensa Hologram nanti, isi harga sesuai kebijakan bisnis, gunakan unit penjualan pcs, lalu buat Equipment Template berisi Lensa Hologram, qty 1, unit lembar, dasar jumlah "Per jumlah pesanan (pcs)", dan hubungkan master lenticular. Alternatifnya tambahkan ID produk pada `templates.additional_hologram_lens` dalam mapping yang telah diperbarui. Tidak ada produk jual baru atau harga tebakan yang dibuat command.

```sh
php artisan crm:photobooth-catalog --mapping=/path/reviewed-mapping.json
php artisan crm:photobooth-catalog --mapping=/path/reviewed-mapping.json --apply --expect=FINGERPRINT_PREVIEW --actor=ID_USER --reason='Penataan SKU, kategori dan template Photobooth'
```

Semua perubahan SKU, nilai attribute/EAV, kategori, master baru, template dan audit berada dalam satu transaksi. Fingerprint mengikat data sebelum perubahan, mapping, dan definisi template. Perubahan data sejak preview mengharuskan preview ulang.

## Verifikasi

Skenario database di `tests/Unit/CrmDataCorrectionsTest.php` mencakup pengurangan payment, eligibility quote, reservasi nomor invoice, dependensi, preview kedaluwarsa, rollback invoice, benturan SKU/EAV, pemetaan serialized/consumable, preservasi dokumen lama, rollback katalog, master baru tanpa stok fiktif, pemilihan manual, perhitungan lenticular, snapshot Surat Jalan, dan penolakan alokasi/rilis untuk kebutuhan yang belum lengkap.

Validasi lokal pengembangan: sepuluh skenario tersebut dijalankan pada SQLite in-memory dengan PHP 8.3 dan source Laravel 12.69.1 sesuai composer.lock. Suite aplikasi lengkap, integrasi MySQL/locking, dan UI browser masih perlu dijalankan di lingkungan lokal CRM sebelum merge. Repository saat ini tidak menyertakan `.github/skills`, sehingga `bin/validate-skills.sh` gagal pada pemeriksaan awal yang sudah ada.
