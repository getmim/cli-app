# cli-app

Module cli tools untuk bekerja dengan aplikasi. Module ini langsung terinstall ketika
tools diinstall.

## Perintah

Di bawah ini adalah perintah-perintah yang dilayani oleh module ini:

```
mim app config
mim app env (production|development|testing|...)
mim app gitignore
mim app init
mim app install (module[ ...]) | -
mim app list
mim app module
mim app remove (module[ ...]) | -
mim app server
mim app update (module[ ...]) | -
```

Nilai `-` pada perintah `install`, `update`, dan `remove` berarti melakukan aksi
tersebut ke semua module yang teridentifikasi. Contohnya, untuk meng-update semua
module yang terinstall, jalankan perintah `mim app update -`.

## Config Callback

Masing-masing module boleh mendaftarkan callback yang akan dipanggil ketika
konfigurasi aplikasi di regenerasi. Untuk mendaftarkan fungsi tersebut, silahkan
tambahkan data seperti di bawah pada konfigurasi module:

```php
return [
    'callback' => [
        'app' => [
            'config' => [
                'Class::method' => true
            ]
        ]
    ]
];
```
Fungsi `Class::method` akan di panggil dengan parameter config yang sudah terbentuk.

```php
Class{
    static function(object &$config, string $base): void{

    }
}
```

Class handler harus bisa berdiri sendiri karena fungsi ini akan di panggil di dalam
scope cli, dan bukan scope aplikasi.