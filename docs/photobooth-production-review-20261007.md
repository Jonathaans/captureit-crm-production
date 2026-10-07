# Hasil pencocokan katalog 7 Oktober 2026

Sumber: daftar produk dan inventory yang dibaca di VPS, lalu diberikan pemilik. Terdapat 85 produk (ID 1–85) dan 112 master inventory (ID 95–206). Ini pencocokan data, bukan perubahan pada production.

`photobooth-production-mapping-20261007.draft.json` adalah mapping parsial untuk database ini. Jangan digunakan dengan `--apply`. Array Classic Box sengaja kosong; beberapa inventory masih null; TL 120 masih berbeda satuan. Validator saat ini akan menghentikan apply sampai masalah tersebut selesai. Nilai null meminta pencarian nama; null bukan perintah membuat barang baru.

## Produk dan SKU

Dengan urutan ID saat ini, SKU akhir akan PRD-0001 sampai PRD-0085. Semua kategori diisi Photobooth sesuai arahan. Produk ID 83–85 yang sekarang memakai PRD-0001 sampai PRD-0003 akan menjadi PRD-0083 sampai PRD-0085. ID, nama produk, harga, dan snapshot transaksi lama tidak diganti. Dua tahap perubahan SKU menangani benturan nomor tersebut.

| Template | Product ID | Cakupan |
| --- | --- | --- |
| Classic | 1–7 | Limited prints, unlimited hours, dan produk utama |
| Classic Box | Belum dipilih | Kandidat Lite Box 46–48, Pro Branded Box 51–52, atau Photobox 79 |
| High Angle | 84 | Highangle Photobooth |
| High Angle Box | 49–50 | Varian 6 dan 12 jam |
| Mozaik | 38, 85 | Mosaic Photobooth dan Mozaik Photobooth; kedua produk tetap terpisah |
| Hologram | 39–42 | Varian print dan produk utama |
| Take Me Away | 24–31 | Starter dan Ultimate, seluruh durasi |
| AI Generative | 36–37 | Varian 3 dan 4 jam |
| Additional Lensa Hologram | Belum ada produk bernama demikian | Tidak membuat produk jual baru dengan harga tebakan |

Total tujuh template utama sudah dipetakan ke 26 varian produk. Semua 85 produk masuk penataan SKU/kategori; hanya produk yang dipetakan menerima penggantian template. Produk lain seperti Mirror Booth dan Glambot mempertahankan template masing-masing.

## Inventory yang teridentifikasi

| Requirement | Inventory ID | Tracking | Aset terdaftar |
| --- | --- | --- | --- |
| Probooth | 139 | serialized | 4 |
| Camera 700D | 103 | serialized | 16 |
| Dummy 700D | 121 | serialized | 15 |
| Mini PC | 100 | serialized | 9 |
| Softbox P120 | 131 | serialized | 6 |
| Lighting SL 300 | 125 | serialized | 7 |
| Mounting Camera | 168 | serialized | 0 |
| Printer DNP | 169 | serialized | 9 |
| Ribbon reguler | 194 | quantity, ROLL | Stok tercatat 7 roll |
| Kabel Roll / PERLENG | 172 | serialized | 31 |
| Keyboard + Mouse / KEY + MOUSE | 179 | serialized | 4 |
| Monitor ViewSonic 24 inci | 162 | serialized | 6 |
| C-Stand | 133 | serialized | 2 |
| Lensa Wide Canon | 113 | serialized | 4 |
| Softbox Lantern / Lentern | 129 | serialized | 2 |
| TV LG 43 inci | 156 | serialized | 2 |
| Pemotong / PAPER CUTER | 176 | serialized | 0 |
| Mesin Laminating | 177 | serialized | 0 |
| Monopod / MONOPD | 137 | serialized | 2 |
| iPad | 120 | serialized | 2 |
| Tiang Background | 136 | serialized | 2 |

Jumlah aset adalah jumlah record, bukan jumlah unit yang pasti tersedia untuk tanggal event. Field quantity_on_hand = 0 pada master serialized tidak menunjukkan bahwa asetnya tidak ada. Mounting Camera, Pemotong, dan Mesin Laminating memiliki master tetapi belum memiliki record unit aset; qty kebutuhan template tidak boleh digunakan sebagai stok fisik awal.

## Pilihan yang perlu dipastikan pemilik

| Requirement | Pilihan/kondisi di master |
| --- | --- |
| Laptop untuk Take Me Away dan AI Generative | Legion 95, LOQ 96, LOQ RRQ 97, HP 98, MSI 99; masing-masing satu record aset |
| Flash untuk Take Me Away | Godox V1 Pro 118 atau YN 560 III 119 |
| Stand TV | Tripod TV 134 atau Stand TV Cart 135 |
| Stand lighting | Kandidat TAKARA 132; nama fungsinya perlu dipastikan |
| Magic Arm | Kandidat CLAMP ARM 165 |
| Magic Clamp | Kandidat CLAMP KECIL 166; saat ini belum ada record aset |
| TL 120 | ID 127, serialized dengan satuan unit dan satu record aset. Pastikan apakah satu set berarti satu aset/QR atau beberapa unit. Jangan mengubah unit maupun multiplier secara diam-diam. |
| Lensa Hologram | Tidak ada nama persis tersebut. LENSA FISHEYE CANON 114 tersedia sebagai master; pastikan apakah barang yang dimaksud sama atau berbeda. |

Template saat ini menunjuk satu master inventory per requirement. Jika laptop/flash/stand yang dipakai berbeda antar template, mapping tunggal tidak cukup dan perlu dukungan pilihan per template sebelum apply.

Nama yang belum ditemukan: Tiang Gorden, Gorden Merah, Ribbon Corporated, Baterai Cas 700D, Casan Baterai 700D, Background Putih, dan Lensa Hologram. Cocokkan kemungkinan nama lain sebelum membuat master. Jika benar barang baru, buat master dengan tracking yang sesuai dan stok nol; pendaftaran unit aset maupun stok masuk mengikuti kondisi fisik yang dikonfirmasi pemilik.

Lensa Hologram tetap wajib satu pada Hologram Photobooth. Penggunaan pada paket lain bersifat tambahan opsional, tanpa memasukkannya ke semua template utama.
