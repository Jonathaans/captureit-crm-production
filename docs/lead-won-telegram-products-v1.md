# Alert Telegram: Lead Won — Siapkan Invoice

Won berarti sales harus membuat Invoice. Alert ini mengambil rincian produk dari Lead, bukan dari Invoice. Tidak ada Invoice yang dibuat otomatis.

## Yang ditambahkan

Placeholder baru: `{%leads.products_detail%}`. Di menu Insert Placeholder tampil sebagai **Detail Produk Lead (Telegram HTML)**.

Setiap produk menampilkan nama, qty, satuan pcs/day, harga per satuan, dan subtotal sebelum pajak/diskon Invoice. Format lama Day × Qty tetap dihitung dengan benar. Nilai Lead tetap ditampilkan terpisah; nilainya bukan total Invoice.

Nama produk diambil dari master produk yang terhubung, sedangkan quantity, unit, dan price dari baris produk Lead. Produk yang sudah tidak ada memakai label `Produk #ID`. Jika Lead tidak punya produk, pesan meminta sales melengkapinya. Daftar panjang diringkas dengan jumlah produk yang belum ditampilkan agar tersedia ruang untuk bagian lain pesan; rincian lengkap tetap di CRM.

Pengiriman webhook mengganti placeholder sebelum encoding JSON, sehingga tanda kutip, backslash, dan baris baru pada data tidak merusak payload. Attribute yang tidak dipakai dalam pesan tidak dihitung.

## 1. Pasang di LOKAL dahulu

Patch dibuat dari main `c772463` (setelah PR #10), dan file perubahannya terpisah dari patch alamat/Delete Quote. Selesaikan commit perubahan sebelumnya sebelum berpindah branch. Jangan memasukkan file `.env`, patch unduhan, cache test, atau LSP ke commit.

Simpan `lead-won-telegram-products-v1.patch` di Downloads. Jalankan di PowerShell proyek lokal:

```powershell
cd C:\Users\Administrator\Documents\laravel-crm-2.2
git status -sb
```

Setelah perubahan kode sebelumnya sudah tersimpan dan tidak ada merge yang tertunda:

```powershell
git fetch origin
git switch main
git pull --ff-only origin main
git switch -c feature/lead-won-telegram-products-v1
git apply --check "$env:USERPROFILE\Downloads\lead-won-telegram-products-v1.patch"
```

Jika `--check` tidak menghasilkan error:

```powershell
git apply "$env:USERPROFILE\Downloads\lead-won-telegram-products-v1.patch"
php artisan optimize:clear
php artisan test --compact tests/Unit/LeadTelegramProductSummaryTest.php
```

Jika ada error, hentikan langkah berikutnya dan kirim outputnya. Test ini memalsukan pengiriman webhook; tidak mengirim pesan ke bot atau mengubah data produksi.

## 2. Commit dan push LOKAL

```powershell
git add packages/Webkul/Automation/src/Helpers/Entity/AbstractEntity.php
git add packages/Webkul/Automation/src/Helpers/Entity/Lead.php
git add packages/Webkul/Automation/src/Services/LeadProductSummary.php
git add tests/Unit/LeadTelegramProductSummaryTest.php
git add docs/lead-won-telegram-products-v1.md
git diff --cached --stat
git commit -m "feat: lead product details for Won Telegram alerts"
git push -u origin feature/lead-won-telegram-products-v1
```

Buat Pull Request dengan **base `main`**, **compare `feature/lead-won-telegram-products-v1`**, kemudian review dan merge.

## 3. Update VPS setelah merge

Dari `/var/www/captureit-crm`, periksa branch dan perubahan terlebih dahulu:

```bash
git fetch origin
git branch --show-current
git status -sb
git rev-list --left-right --count main...origin/main
git diff --stat main..origin/main
```

Branch harus main, angka kiri 0, dan perubahan masuk harus diperiksa. Jika ada perubahan kode lokal yang bertabrakan, jangan menimpanya. Bila release hanya patch ini, tidak perlu migration atau npm build.

Jalankan satu per satu:

```bash
php artisan crm:backup-managed --database-only
```

Setelah muncul `Backup PASS`:

```bash
php artisan down
git pull --ff-only origin main
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Hentikan jika ada error. Periksa Git sudah `0 0` dan aplikasi dapat dibuka.

## 4. Ganti isi alert SETELAH patch aktif di VPS

Buka Settings → Automation → Webhooks, lalu webhook yang digunakan workflow Won. Pada Body `x-www-form-urlencoded`, ganti nilai key `text` dengan satu baris berikut:

```html
<b>🎉 LEAD WON — SIAPKAN INVOICE</b>&#10;&#10;👤 <b>Customer:</b> {%persons.name%}&#10;📝 <b>Project:</b> {%leads.title%}&#10;📱 <b>WhatsApp:</b> {%persons.contact_numbers%}&#10;👨‍💼 <b>Sales Owner:</b> {%leads.user_id%}&#10;&#10;📦 <b>DETAIL PRODUK LEAD</b>&#10;{%leads.products_detail%}&#10;&#10;💰 <b>Nilai Lead:</b> {%leads.lead_value%}&#10;📢 <b>Source:</b> {%leads.lead_source_id%}&#10;📌 <b>Stage:</b> {%leads.lead_pipeline_stage_id%}&#10;&#10;🔔 <b>Tindak lanjut Sales:</b> Buat Invoice berdasarkan quotation yang disetujui, periksa rincian tagihan, lalu kirimkan kepada customer.
```

Pertahankan `parse_mode` = `HTML`, header Content-Type = `application/x-www-form-urlencoded`, endpoint sendMessage, dan chat_id grup yang sudah bekerja. Simpan webhook.

Workflow yang sekarang dipakai tetap **Updated → Stage is equal to Won** dengan action webhook yang sama. Kondisi ini dapat mengirim ulang ketika Lead yang sudah Won diperbarui; patch tidak mengubah aturan trigger menjadi hanya sekali. Pesan lama di Telegram tidak berubah; format baru digunakan pada pengiriman berikutnya.

Jangan memasukkan placeholder baru di VPS sebelum kode patch aktif karena placeholder akan tampil mentah.

## Verifikasi

- Pengujian terisolasi PHP: perhitungan pcs/day, format lama, escaping HTML, produk kosong/hilang, daftar panjang, menu placeholder, pemrosesan data Lead, dan payload/form webhook dengan kutip/baris baru.
- Pemeriksaan sintaks, Laravel Pint, dan penerapan patch pada checkout bersih.
- Pengujian Pest aplikasi lengkap belum dijalankan di lingkungan pembuat patch karena dependency CRM lengkap tidak tersedia. Jalankan test lokal di langkah 1.
- Pengiriman nyata ke bot tidak dilakukan saat menyiapkan patch. Test notifikasi nyata dilakukan setelah pemasangan menggunakan Lead percobaan yang jelas diberi label TEST, bila sesuai kebutuhan Anda.
