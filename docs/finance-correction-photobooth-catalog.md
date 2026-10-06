# Koreksi finance dan katalog Photobooth

Perubahan kode ini tidak menjalankan koreksi data saat deploy atau migrate. Ketiga command menggunakan preview secara default; hanya `--apply` yang mengubah data. Jalankan preview di lokal dengan salinan database dan review hasilnya sebelum menggunakan data production.

## Pemeriksaan awal tanpa deploy

`tools/review_finance_catalog.php` hanya membaca daftar produk, inventory, jumlah aset, template, dan invoice `INV 2610-0010`. Script dapat dijalankan dari root aplikasi dengan `php tools/review_finance_catalog.php`, atau lewat stdin setelah mengambil file dari commit yang sudah direview. Tidak menampilkan kredensial, billing address, atau bukti pembayaran.

Screenshot menampilkan 85 produk, termasuk produk berkategori kosong. Rencana katalog memakai SEMUA produk saat command dijalankan, mengurutkan ID naik menjadi PRD-0001 dst dan mengisi kategori Photobooth. Review seluruh daftar sebelum apply, termasuk produk tambahan dan Videographer. Harga, product ID, kode/serial aset, snapshot quote/invoice, dan surat jalan lama tetap menggunakan data masing-masing.

## Deploy kode untuk pengujian

Gunakan branch PR, PHP 8.3+, dan database lokal. Setelah perubahan diambil:

```sh
composer dump-autoload -o
php artisan migrate
php artisan test --compact --filter=CrmDataCorrectionsTest
php artisan crm:catalog-review
php artisan crm:invoice-correct 'INV 2610-0010'
php artisan crm:photobooth-catalog
```

Migration hanya membuat tabel audit. Dalam production, gunakan prosedur backup dan maintenance yang biasa dipakai; lakukan apply saat aplikasi dalam maintenance dan worker yang bisa menulis data sudah dihentikan. Seluruh produk/inventory dikunci dalam transaksi selama pembaruan katalog.

## Invoice

Preview menampilkan nominal payment aktual, fingerprint, quote, dan jumlah dokumen terkait. Invoice dengan expense, SPK, surat jalan, PO, invoice pelunasan terkait, atau invoice aktif lain untuk quote yang sama ditolak sampai dependensinya ditinjau. Koreksi tidak menghapus dokumen tersebut secara otomatis.

Apply memerlukan fingerprint terbaru, ID pengguna CRM pelaksana, dan alasan:

```sh
php artisan crm:invoice-correct 'INV 2610-0010' --apply --expect=FINGERPRINT_PREVIEW --actor=ID_USER --reason='Salah generate invoice dan salah input payment'
```

Operasi mengarsipkan seluruh invoice, items dan payments ke `crm_data_corrections`, lalu menghapus ketiganya secara atomik. Payment Received turun mengikuti jumlah payment dan periode paid_at aslinya; nilai invoice aktif juga berkurang. Quote tetap tersedia dan eligibility billing dihitung ulang dari invoice yang tersisa. Nomor invoice lama tetap dicadangkan dalam kedua generator. UI editing biasa tetap tunduk pada aturan dokumen terkunci. Ini command maintenance, bukan tombol hapus umum untuk finance.

Riwayat audit adalah sumber pemulihan manual; tidak ada tombol undo otomatis. Rollback migration menolak membuang tabel audit yang sudah berisi data.

## Pemetaan template dan inventory

Definisi terletak di `packages/Webkul/Admin/src/Config/photobooth-catalog.php`. Delapan template utama wajib terpetakan. Template tambahan Lensa Hologram bersifat opsional pada produk Additional yang sudah ada; Hologram Photobooth sendiri selalu berisi satu lensa. Produk tambahan baru memerlukan data komersial tersendiri dan tidak dibuat dengan harga tebakan.

- Perleng = Kabel Roll: 3 unit serialized pada semua paket utama.
- Ribbon reguler dan Corporated: dua master consumable terpisah, masing-masing 2 roll pada paket yang memakainya.
- Barang tanpa qty dianggap 1 unit/set. TL 120 menggunakan set.
- AI Generative membutuhkan 5 stand lighting; High Angle Box mengikuti daftar tanpa lensa wide.
- Qty template adalah kebutuhan per paket. Membuat master baru tidak membuat unit aset atau stok fisik.

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
    "hologram_lens": {"create": {"code": "LENS-HOLO", "warehouse_id": 1}}
  }
}
```

ID di atas hanyalah contoh. Gunakan hasil pemeriksaan production. Mapping `create` hanya untuk master yang telah dipastikan belum ada; stok awalnya nol dan aset fisiknya harus didaftarkan lewat alur inventory. Tipe master lama tidak dikonversi otomatis.

```sh
php artisan crm:photobooth-catalog --mapping=/path/reviewed-mapping.json
php artisan crm:photobooth-catalog --mapping=/path/reviewed-mapping.json --apply --expect=FINGERPRINT_PREVIEW --actor=ID_USER --reason='Penataan SKU, kategori dan template Photobooth'
```

Semua perubahan SKU, nilai attribute/EAV, kategori, master baru, template dan audit berada dalam satu transaksi. Fingerprint mengikat data sebelum perubahan, mapping, dan definisi template. Perubahan data sejak preview mengharuskan preview ulang.

## Verifikasi

Skenario database di `tests/Unit/CrmDataCorrectionsTest.php` mencakup pengurangan payment, eligibility quote, reservasi nomor invoice, dependensi, preview kedaluwarsa, rollback invoice, benturan SKU/EAV, pemetaan serialized/consumable, preservasi dokumen lama, rollback katalog dan master baru tanpa stok fiktif.

Validasi lokal pengembangan: tujuh skenario tersebut dijalankan pada SQLite in-memory dengan PHP 8.3 dan source Laravel 12.69.1 sesuai composer.lock. Suite aplikasi lengkap dan integrasi MySQL/locking masih perlu dijalankan di lingkungan lokal CRM sebelum merge. Repository saat ini tidak menyertakan `.github/skills`, sehingga `bin/validate-skills.sh` gagal pada pemeriksaan awal yang sudah ada.
