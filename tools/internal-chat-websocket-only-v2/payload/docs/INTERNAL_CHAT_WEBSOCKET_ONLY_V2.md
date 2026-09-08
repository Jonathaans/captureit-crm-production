# Internal Chat WebSocket-Only V2

Versi ini menghapus seluruh interval polling pesan, typing, sidebar, widget,
dan unread badge yang sebelumnya dipakai sebagai fallback ketika Reverb putus.

## Perilaku

- Pesan/edit/hapus/read/typing dan unread invalidation diterima dari private
  Reverb channels.
- HTTP tetap dipakai untuk transaksi tersimpan dan satu kali sinkronisasi
  setelah event atau reconnect. Ini bukan polling karena tidak memiliki timer.
- Saat WebSocket putus, badge berubah menjadi `Offline`; data tidak melakukan
  polling otomatis sampai koneksi pulih.
- Setelah reconnect, halaman melakukan satu kali resync untuk mengejar event
  yang mungkin terlewat.

## Instalasi

```powershell
php tools/apply_internal_chat_websocket_only_v2.php
cd packages\Webkul\Admin
npm run build
cd ..\..\..
php artisan optimize:clear
php tools/check_internal_chat_websocket_only_v2.php
```

Jalankan Reverb pada port yang dikonfigurasi, misalnya lokal:

```powershell
php artisan reverb:start --host=127.0.0.1 --port=6001 --debug
```

Di browser, badge harus `Live`, koneksi Socket harus `101`, dan endpoint
`poll`, `sidebar-summary`, serta `unread-summary` tidak boleh muncul berulang
berdasarkan timer. Request sinkronisasi tunggal setelah event adalah normal.
