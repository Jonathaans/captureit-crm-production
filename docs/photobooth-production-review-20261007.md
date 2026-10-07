# Hasil pencocokan katalog 7 Oktober 2026

Sumber: daftar produk dan inventory yang dibaca di VPS, lalu diberikan pemilik. Terdapat 85 produk (ID 1–85) dan 112 master inventory (ID 95–206). Ini pencocokan data, bukan perubahan pada production.

`photobooth-production-mapping-20261007.draft.json` adalah mapping parsial untuk database ini. Jangan digunakan dengan `--apply`. Cakupan Classic Box, pilihan flash, nama perlengkapan, dan satuan TL 120 sudah dikonfirmasi pemilik. Laptop dan stand TV belum dipilih; beberapa master belum didaftarkan; kebutuhan lenticular per varian belum ditentukan. Nilai null meminta pencarian nama; null bukan perintah membuat barang baru. Dengan master saat ini, validator tetap memblokir apply seluruh katalog.

## Produk dan SKU

Dengan urutan ID saat ini, SKU akhir akan PRD-0001 sampai PRD-0085. Semua kategori diisi Photobooth sesuai arahan. Produk ID 83–85 yang sekarang memakai PRD-0001 sampai PRD-0003 akan menjadi PRD-0083 sampai PRD-0085. ID, nama produk, harga, dan snapshot transaksi lama tidak diganti. Dua tahap perubahan SKU menangani benturan nomor tersebut.

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

Total delapan template utama sudah dipetakan ke 32 varian produk. Semua 85 produk masuk penataan SKU/kategori; hanya produk yang dipetakan menerima penggantian template. Produk lain seperti Mirror Booth dan Glambot mempertahankan template masing-masing.

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
| Pemotong / PAPER CUTER | 176 | serialized | 0 |
| Mesin Laminating | 177 | serialized | 0 |
| Monopod / MONOPD | 137 | serialized | 2 |
| Flash YN 560 III | 119 | serialized | 2 |
| iPad | 120 | serialized | 2 |
| Tiang Background | 136 | serialized | 2 |

Jumlah aset adalah jumlah record, bukan jumlah unit yang pasti tersedia untuk tanggal event. Field quantity_on_hand = 0 pada master serialized tidak menunjukkan bahwa asetnya tidak ada. Mounting Camera, Pemotong, Mesin Laminating, dan Magic Clamp memiliki master tetapi belum memiliki record unit aset. Pemilik akan mendaftarkan alat fisik secara manual nanti; qty kebutuhan template tidak digunakan sebagai stok fisik awal.

## Pilihan yang perlu dipastikan pemilik

| Requirement | Pilihan/kondisi di master |
| --- | --- |
| Laptop untuk Take Me Away dan AI Generative | Legion 95, LOQ 96, LOQ RRQ 97, HP 98, MSI 99; masing-masing satu record aset |
| Stand TV | Tripod TV 134 atau Stand TV Cart 135 |
| Lenticular | Pemilik memastikan ini bahan lenticular untuk produk Hologram dan add-on, berbeda dari LENSA FISHEYE CANON 114. Master belum teridentifikasi. Konfirmasi pencatatan lembar dan jumlah pemakaian per cetakan/varian. |

Template saat ini menunjuk satu master inventory per requirement. Jika laptop atau stand yang dipakai berbeda antar template, mapping tunggal tidak cukup dan perlu dukungan pilihan per template sebelum apply.

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

Mapping tidak membuat master maupun stok secara otomatis. Setelah pendaftaran, masukkan ID sebenarnya dan jalankan preview ulang. Barang yang belum tersedia tetap dianggap kebutuhan template, sehingga apply ditolak sampai lengkap. Koreksi invoice dapat diuji terpisah tanpa menunggu pendaftaran barang.

Lensa Hologram bukan lensa kamera dan tidak menggunakan inventory 114. Draft mengubah rencana tracking menjadi quantity dengan satuan lembar untuk bahan lenticular; satuan dan pemakaian per hasil cetak masih perlu konfirmasi pemilik. Angka 1 pada definisi template sementara belum merupakan jumlah final bagi paket 100/200/300 prints maupun produk Hologram tanpa jumlah cetak. Selesaikan perhitungan ini sebelum apply; jangan hanya melengkapi ID master lalu menjalankan apply.

Lenticular tetap wajib pada Hologram Photobooth. Pada paket lain, penggunaannya melalui add-on opsional. Product ID untuk add-on belum tersedia dalam daftar produk dan belum dipetakan; tidak membuat produk jual baru dengan harga tebakan.
