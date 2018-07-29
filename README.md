# cli-app

Module cli tools untuk bekerja dengan aplikasi. Module ini langsung terinstall ketika
tools diinstall.

## Perintah

Di bawah ini adalah perintah-perintah yang dilayani oleh module ini:

```
mim app init
mim app config
mim app install (module[ ...]) | -
mim app module
mim app remove (module[ ...]) | -
mim app server
mim app update (module[ ...]) | -
```

Nilai `-` pada perintah `install`, `update`, dan `remove` berarti melakukan aksi
tersebut ke semua module yang teridentifikasi. Contohnya, untuk meng-update semua
module yang terinstall, jalankan perintah `mim app update -`.