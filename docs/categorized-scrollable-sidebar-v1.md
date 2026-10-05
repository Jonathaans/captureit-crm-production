# Sidebar CRM berkategori, dapat di-scroll, dan warna dapat diatur

Patch: `categorized-scrollable-sidebar-v1.patch`

Branch baru: `feature/categorized-scrollable-sidebar-v1`

Dasar patch: `76c02ff` (main setelah update alamat, delete quote, dan Telegram Lead Won).

**Ini patch gabungan terbaru:** berisi seluruh perubahan sidebar sebelumnya plus pengaturan warna. Karena patch sebelumnya belum dipasang, ganti unduhan lama dengan file ini dan terapkan **satu kali saja**. Pastikan nama unduhan tepat `categorized-scrollable-sidebar-v1.patch` sebelum menjalankan perintah di bawah.

## Hasil perubahan

Warna awal sidebar mengikuti referensi: biru, penanda menu aktif ungu dengan aksen kuning, jarak antar-menu yang lebih rapi, dan nama menu tampil pada desktop. Tombol panah mengecilkan sidebar menjadi ikon. Pilihan ringkas disimpan per akun pada browser tersebut.

Area menu mempunyai scroll sendiri. Bagian workspace dan identitas akun tetap terlihat. Submenu Inventory, Contacts, dan Mail terbuka di dalam sidebar. Di layar kecil, tombol menu membuka panel yang dapat ditutup lewat tombol X, Escape, atau area di luar panel.

| Kategori | Menu utama |
| --- | --- |
| Ringkasan | Dashboard, Dashboard Inventory, Dashboard Sales, Operations Dashboard |
| Penjualan | Leads, Quotes, Activities |
| Keuangan | Invoices, Purchase Orders, Financial Report |
| Operasional | Surat Perintah Kerja, Delivery Orders, Inventory |
| Data Master | Contacts, Products |
| Komunikasi | My Mail, Mail |
| Sistem | Settings, Configuration, Help, Internal Chat Audit |

Menu menggunakan hak akses yang sudah berlaku. Kategori kosong disembunyikan. Menu dari extension yang belum dikategorikan masuk ke Lainnya. Nama menu khusus yang sudah diatur di konfigurasi tetap dipakai. Logo pada header tetap memakai konfigurasi CRM.

## Mengganti warna lewat CRM

Setelah patch dipasang, masuk dengan akun yang memiliki akses **Configuration**, lalu buka **General → Settings → Sidebar Colors**.

| Pengaturan | Fungsi | Warna awal |
| --- | --- | --- |
| Sidebar Background | Latar sidebar | `#385988` |
| Active Menu Background | Latar menu yang sedang dibuka | `#39255d` |
| Accent Color | Garis penanda dan aksen ikon | `#ffc21c` |

Klik kotak warna untuk memilih, lalu klik **Save Configuration**. Setelah halaman dimuat ulang, warna berlaku untuk semua akun, desktop, dan ponsel. Halaman lain yang sudah terbuka perlu di-reload. Pada dark mode, sidebar tetap memakai warna pilihan tersebut. Teks menyesuaikan latar terang/gelap; ikon memakai warna teks jika warna aksen terlalu dekat dengan latarnya. Brand Color tetap mengatur elemen aplikasi lainnya.

Untuk kembali ke warna awal, pilih kembali ketiga kode di atas lalu simpan. Nilai warna lama/tidak valid memakai warna awal masing-masing kolom.

Patch memakai penyimpanan konfigurasi CRM yang sudah ada. Menyimpan warna memperbarui konfigurasi tersebut; tidak menambah atau mengubah struktur tabel. **Untuk patch ini saja tidak perlu `npm run build`, `composer update`, atau migration.**

## 1. Pasang di LOKAL Windows terlebih dahulu

Simpan patch di folder Downloads. Tidak perlu memindahkannya ke folder proyek.

Buka terminal PowerShell VS Code di proyek:

```powershell
cd C:\Users\Administrator\Documents\laravel-crm-2.2
git status -sb
```

Jika ada perubahan kode aplikasi yang belum di-commit, selesaikan/simpan perubahan tersebut dahulu. Jangan menimpa file kerja. File patch unduhan, LSP, dan cache hasil test tidak perlu dimasukkan ke commit.

Bila perubahan tracked hanya `.phpunit.cache/test-results`, kembalikan file cache itu saja:

```powershell
git restore -- .phpunit.cache/test-results
```

Ambil main terbaru dan buat branch baru. Jalankan satu per satu, lanjut hanya jika perintah sebelumnya berhasil:

```powershell
git fetch origin
git switch -c feature/categorized-scrollable-sidebar-v1 origin/main
git apply --check "$env:USERPROFILE\Downloads\categorized-scrollable-sidebar-v1.patch"
git apply "$env:USERPROFILE\Downloads\categorized-scrollable-sidebar-v1.patch"
```

`git apply --check` berhasil bila kembali ke prompt tanpa pesan error. Jika ada error atau branch sudah ada, berhenti dan kirim outputnya; jangan gunakan perintah paksa.

## 2. Periksa di LOKAL

```powershell
php tools/check_standalone_dashboard_navigation_v1.php
php tools/check_sidebar_colors_v1.php
php artisan config:clear
php artisan view:clear
php artisan view:cache
```

Pemeriksaan pertama seharusnya berakhir dengan **39 navigation checks passed**, dan pemeriksaan warna dengan **23 sidebar color checks passed**. Kemudian buka CRM lokal dan tekan **Ctrl + F5**.

Coba langkah berikut:

1. Masuk sebagai Administrator. Nama menu dan kategori tampil di kiri.
2. Scroll di area menu hingga menu terakhir. Isi halaman tidak ikut bergerak saat scroll menu mencapai ujung.
3. Buka Leads, Quotes, Inventory Items, dan Inventory Assets. Menu aktif dan submenu sesuai halaman.
4. Klik panah di panel CRM WORKSPACE. Sidebar menjadi ikon dan ruang halaman ikut melebar. Reload: pilihan ringkas tetap tersimpan.
5. Dalam mode ringkas, klik Inventory. Sidebar terbuka dan submenu dapat dipilih.
6. Coba akun Sales dan akun gudang yang tersedia. Pastikan hanya menu sesuai hak akses yang terlihat.
7. Perkecil browser di bawah 1024 px atau gunakan HP. Buka tombol menu, scroll ke bawah, lalu tutup lewat X, Escape, atau area luar.
8. Coba dark mode dan satu halaman dengan tabel/form panjang. Pastikan sidebar, konten, dan footer tidak saling menutupi.
9. Di Configuration → General → Settings → Sidebar Colors, ubah ketiga warna dan simpan. Buka halaman Leads, lalu reload halaman tersebut: warna pilihan tetap digunakan.
10. Uji satu latar sangat terang dan satu latar gelap. Teks, ikon, menu aktif, dan tombol tutup pada ponsel harus tetap terlihat. Coba juga dark mode.
11. Login akun Sales di browser lain lalu reload. Warna mengikuti pengaturan global tanpa mengubah hak akses menu. Setelah selesai, simpan palet yang ingin digunakan.

`pratinjau-sidebar.html` adalah demonstrasi menggunakan komponen sidebar yang sama, pilihan contoh warna, dan data contoh. Tombol contoh warna hanya mengubah pratinjau, bukan konfigurasi CRM. Tautan di pratinjau tidak membuka data CRM. Pratinjau tidak menggantikan tes pada aplikasi lokal.

## 3. Commit dan push dari LOKAL

Setelah tes lokal berhasil, stage hanya 13 file berikut:

```powershell
git add packages/Webkul/Admin/src/Config/core_config.php
git add packages/Webkul/Admin/src/Resources/lang/en/sidebar.php
git add packages/Webkul/Admin/src/Services/SidebarThemeService.php
git add tools/check_sidebar_colors_v1.php
git add packages/Webkul/Admin/src/Services/SidebarNavigationService.php
git add packages/Webkul/Admin/src/Resources/views/components/layouts/index.blade.php
git add packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/desktop/index.blade.php
git add packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/mobile/index.blade.php
git add packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/menu.blade.php
git add packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/navigation.blade.php
git add packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/styles.blade.php
git add tools/check_standalone_dashboard_navigation_v1.php
git add docs/categorized-scrollable-sidebar-v1.md
git diff --cached --stat
git diff --cached --check
```

Pastikan yang ter-stage hanya file di atas, kemudian:

```powershell
git commit -m "feat: categorized sidebar with configurable colors"
git push -u origin feature/categorized-scrollable-sidebar-v1
```

## 4. Pull request di GITHUB

Buka:

https://github.com/Jonathaans/captureit-crm-production/compare/main...feature/categorized-scrollable-sidebar-v1?expand=1

- **Base:** `main`
- **Compare:** `feature/categorized-scrollable-sidebar-v1`
- Judul: `Sidebar CRM berkategori dengan pengaturan warna`

Periksa perubahan, lalu merge setelah tes lokal berhasil. Update VPS setelah PR sudah merged.

## 5. Update di VPS

Semua perintah bagian ini dijalankan pada terminal VPS, bukan PowerShell lokal.

```bash
cd /var/www/captureit-crm
git fetch origin
git branch --show-current
git status -sb
git rev-list --left-right --count main...origin/main
git diff --stat main..origin/main
```

Branch harus `main`, angka kiri harus `0`, dan perubahan masuk harus sesuai PR sidebar. Jika ada perubahan tracked `M`/`D` atau commit VPS yang belum di main, berhenti dan periksa dahulu. Pertahankan file backup/untracked yang sudah ada. Bila release memuat perubahan lain, ikuti juga panduan release tersebut.

Buat penanda kode sebelum update:

```bash
git branch "backup/before-sidebar-$(date +%Y%m%d-%H%M%S)"
```

Lanjut satu per satu:

```bash
php artisan down
git pull --ff-only origin main
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan up
```

Jika ada error, berhenti dan kirim outputnya. Setelah berhasil:

```bash
git rev-list --left-right --count main...origin/main
git log -1 --oneline
```

Hasil Git yang diharapkan `0 0`. Buka CRM VPS, Ctrl + F5, lalu ulangi pengecekan menu dengan akun Admin dan Sales. Hasil `0 0` memastikan kode sinkron; tes browser memastikan tampilan bekerja di produksi.

## Verifikasi paket dan batasnya

- 39 pemeriksaan navigasi terisolasi lulus: kategori, ACL, dashboard aktif, submenu, fallback extension, dan urutan landing login.
- 23 pemeriksaan warna terisolasi lulus: nilai awal, konfigurasi tersimpan/diperbarui, warna terang/gelap, ikon, serta fallback nilai tidak valid. Pemeriksaan ini tidak menulis database.
- 14 pemeriksaan DOM/Vue lulus: kompilasi komponen, menu aktif, mode ringkas, penyimpanan preferensi, submenu, konsistensi desktop/mobile, ID unik, dan browser storage yang tidak tersedia.
- Rendering Blade diperiksa dengan warna awal, warna kustom, dan nilai tidak valid. Keenam file Blade baru/berubah berhasil dikompilasi dan diperiksa sintaks; service serta pemeriksaan PHP lolos Pint.
- Patch diperiksa dapat dipasang ke commit dasar tanpa konflik.
- Lingkungan pembuat patch menggunakan runtime PHP terisolasi dan DOM simulasi. Browser Chromium grafis tidak dapat dijalankan di lingkungan tersebut. Belum ada tes visual pada CRM yang sedang berjalan, pengujian modal native/scroll fisik di browser, penyimpanan lewat form Configuration pada database CRM asli, atau tes aplikasi penuh. Lakukan tes lokal di atas sebelum merge/deploy.
- `bin/validate-skills.sh` pada baseline gagal karena direktori `.github/skills` tidak tersedia; patch ini tidak mengubah pengaturan skills.
