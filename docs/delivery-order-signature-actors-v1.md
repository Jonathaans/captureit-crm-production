# Delivery Order Signature Actors V1

## Aturan final

- `Recipient` tetap memakai `recipient_name`, yaitu nama client/perusahaan penerima.
- `Received By` memakai `pic_name` (PIC Event) tanpa fallback ke nama client.
- `Released By` memakai snapshot nama akun yang menekan **Issue Surat Jalan**.

## Audit release

Saat status berubah dari `draft` menjadi `issued`, sistem menyimpan:

- `released_by`: ID akun yang melakukan release.
- `released_by_name`: snapshot nama akun pada saat release.

PDF membaca `released_by_name`. Karena itu nama tidak berubah apabila PDF kemudian dicetak ulang oleh akun lain atau nama user terkait berubah.

Surat Jalan yang sudah berstatus selain `draft` tidak dapat di-release ulang, sehingga snapshot releaser pertama tidak dapat ditimpa.

## Deployment

```bash
php artisan down
php artisan migrate --force
php artisan optimize:clear
php tools/check_delivery_order_signature_actors_v1.php
php artisan up
```

## Catatan data lama

Surat Jalan yang sudah pernah dirilis sebelum migration tidak memiliki snapshot releaser yang dapat dipastikan. Kolom `Released By` tetap berupa garis tanda tangan untuk data lama tersebut; sistem tidak menebak pelakunya dari `Created By` atau akun yang mencetak PDF.
