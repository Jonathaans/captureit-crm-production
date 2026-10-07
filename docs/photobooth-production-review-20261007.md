# Hasil pencocokan katalog 7 Oktober 2026

Sumber: daftar produk dan inventory yang dibaca di VPS, lalu diberikan pemilik. Terdapat 85 produk (ID 1–85) dan 112 master inventory (ID 95–206). Ini pencocokan data, bukan perubahan pada production.

Pembaruan pemilik 7 Oktober 2026: ada penambahan produk AI Generative setelah daftar tersebut diambil. Template produk AI Generative baru akan diedit manual oleh pemilik dan tidak ditambahkan ke mapping template otomatis. Seluruh produk yang ada saat preview tetap masuk penomoran SKU/kategori; jumlah akhir dan SKU akhir mengikuti data terbaru, bukan dibatasi 85 produk. Mapping AI Generative 36–37 hanya untuk dua varian yang sudah direview sebelumnya.

`photobooth-production-mapping-20261007.draft.json` sudah mengikuti konfirmasi pemilik, termasuk pilihan manual laptop, Tripod TV sebagai default, dan satu lembar lenticular per cetakan. Jalankan preview dan pengujian lokal sebelum `--apply`. Mapping berisi 27 ID inventory dan delapan pilihan manual yang dinyatakan eksplisit; tidak ada pembuatan master/stock otomatis. Pilihan manual dapat masuk template sebagai kebutuhan wajib, tetapi harus dihubungkan ke inventory sebelum barang dirilis.

## Produk dan SKU

Pada snapshot awal 85 produk, SKU akan PRD-0001 sampai PRD-0085. Produk baru melanjutkan urutan berdasarkan daftar ID terbaru saat preview. Semua kategori diisi Photobooth sesuai arahan. Produk ID 83–85 yang sebelumnya memakai PRD-0001 sampai PRD-0003 akan menjadi PRD-0083 sampai PRD-0085 selama urutan sebelumnya tetap sama. ID, nama produk, harga, dan snapshot transaksi lama tidak diganti. Dua tahap perubahan SKU menangani benturan nomor tersebut.

| Template | Product ID | Cakupan |
| --- | --- | --- |
| Classic | 1–7 | Limited prints, unlimited hours, dan produk utama |
| Classic Box | 46–48, 51–52, 79 | Lite Box, Pro Branded Box, dan Photobox; seluruh varian dikonfirmasi |
| High Angle | 84 | Highangle Photobooth |
| High Angle Box | 49–50 | Varian 6 dan 12 jam |
| Mozaik | 38, 85 | Mosaic Photobooth dan Mozaik Photobooth; kedua produk tetap terpisah |
| Hologram | 39–42 | Varian print dan produk utama |
| Take Me Away | 24–31 | Starter dan Ultimate, seluruh durasi |
| AI Generative | 36–37 | Varian 3 dan 4 jam |
| Additional Lensa Hologram | Belum ada produk bernama demikian | Tidak membuat produk jual baru dengan harga tebakan |

Total delapan template utama sudah dipetakan ke 32 varian produk. Seluruh produk pada preview terbaru masuk penataan SKU/kategori; hanya produk yang dipetakan menerima penggantian template. Produk lain seperti Mirror Booth, Glambot, dan AI Generative baru mempertahankan template masing-masing.

## Inventory yang teridentifikasi

| Requirement | Inventory ID | Tracking | Aset terdaftar |
| --- | --- | --- | --- |
| Probooth | 139 | serialized | 4 |
| Camera 700D | 103 | serialized | 16 |
| Dummy 700D | 121 | serialized | 15 |
| Mini PC | 100 | serialized | 9 |
| Softbox P120 | 131 | serialized | 6 |
| Lighting SL 300 | 125 | serialized | 7 |
| Stand Lighting / TAKARA | 132 | serialized | 20 |
| Mounting Camera | 168 | serialized | 0 |
| Printer DNP | 169 | serialized | 9 |
| Ribbon reguler | 194 | quantity, ROLL | Stok tercatat 7 roll |
| Kabel Roll / PERLENG | 172 | serialized | 31 |
| Keyboard + Mouse / KEY + MOUSE | 179 | serialized | 4 |
| TL 120 | 127 | serialized, unit | 1; satu unit = satu QR |
| Monitor ViewSonic 24 inci | 162 | serialized | 6 |
| Magic Arm / CLAMP ARM | 165 | serialized | 4 |
| Magic Clamp / CLAMP KECIL | 166 | serialized | 0 |
| C-Stand | 133 | serialized | 2 |
| Lensa Wide Canon | 113 | serialized | 4 |
| Softbox Lantern / Lentern | 129 | serialized | 2 |
| TV LG 43 inci | 156 | serialized | 2 |
| Stand TV default / TRIPOD TV | 134 | serialized | 3; alternatif Stand TV Cart 135 |
| Pemotong / PAPER CUTER | 176 | serialized | 0 |
| Mesin Laminating | 177 | serialized | 0 |
| Monopod / MONOPD | 137 | serialized | 2 |
| Flash YN 560 III | 119 | serialized | 2 |
| iPad | 120 | serialized | 2 |
| Tiang Background | 136 | serialized | 2 |

Jumlah aset adalah jumlah record, bukan jumlah unit yang pasti tersedia untuk tanggal event. Field quantity_on_hand = 0 pada master serialized tidak menunjukkan bahwa asetnya tidak ada. Mounting Camera, Pemotong, Mesin Laminating, dan Magic Clamp memiliki master tetapi belum memiliki record unit aset. Pemilik akan mendaftarkan alat fisik secara manual nanti; qty kebutuhan template tidak digunakan sebagai stok fisik awal.

## Pilihan yang sudah dikonfirmasi pemilik

| Requirement | Pilihan/kondisi di master |
| --- | --- |
| Laptop untuk Take Me Away dan AI Generative | Pilih manual pada Surat Jalan: Legion 95, LOQ 96, LOQ RRQ 97, HP 98, atau MSI 99, lalu alokasikan/scan QR unitnya. Tidak ada laptop tertentu yang dipilih otomatis. |
| Stand TV | Default Tripod TV 134; boleh ganti ke Stand TV Cart 135 pada Surat Jalan sebelum alokasi. |
| Lenticular | Consumable quantity/lembar, satu lembar untuk satu hasil cetak, mengikuti pesanan. Berbeda dari LENSA FISHEYE CANON 114. |

Pemilihan menggunakan alur Edit Surat Jalan → Inventory Item → simpan → Allocation/scan QR. Selesaikan pilihan master dan jumlah sebelum mulai alokasi. Jika alokasi sudah aktif, release alokasi terlebih dahulu sebelum mengedit requirement. Sistem tetap mengalokasikan aset dari master yang benar dan menjaga agar satu aset tidak dipakai pada dua event.

| Produk/jenis pesanan | Kebutuhan lenticular |
| --- | --- |
| Hologram 100 Prints, ID 39 | 100 lembar per paket; dua paket = 200 lembar |
| Hologram 200 Prints, ID 40 | 200 lembar per paket |
| Hologram 300 Prints, ID 41 | 300 lembar per paket |
| Hologram tanpa jumlah cetak, ID 42 | Isi jumlah cetak pesanan di Surat Jalan; nilai awal 0 berarti belum diisi, bukan bebas kebutuhan |
| Additional Lensa Hologram | Satu lembar × quantity pesanan dalam pcs; 100 pcs = 100 lembar. Product ID add-on belum tersedia dan harus dipetakan setelah dibuat. |

Angka paket 100/200/300 berlaku untuk quantity paket pada pesanan, bukan input quantity lembar pada produk paket. Pada add-on, quantity penjualan adalah jumlah lembar. Jika add-on menggunakan satuan hari, sistem meminta pengisian jumlah di Surat Jalan dan tidak menganggap hari sebagai lembar. Barang serialized seperti kamera tetap dihitung dari kebutuhan alat, bukan jumlah cetak.

## Pendaftaran manual oleh pemilik

Pemilik mengonfirmasi enam master berikut memang belum didaftarkan dan akan disetting manual nanti:

| Master | Tracking | Satuan yang disiapkan |
| --- | --- | --- |
| Tiang Gorden | serialized | unit |
| Gorden Merah | serialized | unit |
| Ribbon Corporated | quantity | roll |
| Baterai Cas 700D | serialized | unit |
| Casan Baterai 700D | serialized | unit |
| Background Putih | serialized | unit |

Mapping menggunakan `{"manual": true}` untuk enam master tersebut, laptop, dan lenticular. Template menyimpan nama, jumlah/satuan, catatan, serta tanda wajib dipenuhi gudang; inventory ID masih kosong. Penataan SKU/kategori dan template dapat diuji tanpa menunggu master baru. Surat Jalan tetap tidak dapat dirilis sebelum requirement tersebut dihubungkan dan dialokasikan sesuai jumlahnya.

Setelah master didaftarkan, hubungkan melalui Edit Product → Equipment Template agar Surat Jalan berikutnya memakai master tersebut. Surat Jalan yang sudah terbuat memiliki snapshot tersendiri, jadi hubungkan juga di Surat Jalan terkait. Jika ingin mengulang command katalog, ganti pilihan manual pada mapping dengan ID baru terlebih dahulu; mengulang mapping manual akan kembali mengosongkan pilihan master pada template.

Lenticular tetap wajib pada Hologram Photobooth. Pada paket lain, penggunaannya melalui add-on opsional. Product ID untuk add-on belum tersedia dalam daftar produk dan belum dipetakan; tidak membuat produk jual baru dengan harga tebakan.
