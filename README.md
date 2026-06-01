
# POS Web Starter — PT Kencana Lima Delapan

Tema hijau tua & emas, dukungan multi-store, role (kasir/spv/admin/superadmin/spv_warehouse), POS layar sentuh, diskon per item, retur via alur approval, laporan & ekspor CSV, struk HTML & ESC/POS, 2FA TOTP (opsional), placeholder QRIS, audit log, filter laporan per lokasi & tanggal.

## Cara Pasang
1. Buat database `pos_db`, import `sql/pos_schema.sql`.
2. Edit `config/config.php` untuk kredensial MySQL dan opsi lain.
3. Taruh folder `public/` sebagai webroot (atau atur BASE_URL).
4. Login awal **email** `admin@example.com` **password** `admin123` (ganti segera).
5. Tambah **produk** dan **stok** (table `inventory`) per cabang.
6. Kasir hanya melihat produk & stok dari cabangnya. SPV Warehouse dapat melihat stok semua cabang.

## Fitur kunci
- **Manajemen inventaris real-time** (stok berkurang saat transaksi).
- **Laporan** dengan filter lokasi & tanggal, ekspor **CSV** (PDF via FPDF opsional).
- **Multi-lokasi & pembatasan per user** (kolom `branch_id`).
- **Alur pembatalan/retur**: kasir ajukan → SPV setujui → Admin override (endpoint mudah ditambah).
- **Cetak struk**: HTML siap cetak + **ESC/POS raw** (lihat `print_escpos.php`), kompatibel QZ Tray.
- **2FA TOTP**: isi `totp_secret` pada user untuk mengaktifkan.
- **Integrasi QRIS**: isi key di `config/config.php`, gunakan endpoint gateway Anda di `pos_pay.php` (tanda `method==='qris'`).

## Catatan
- **PDF**: Jika ingin PDF native, drop-in `FPDF.php` ke folder `lib/` lalu panggil dari `receipt.php`/report exporter.
- **SSO/LDAP/WebAuthn**: disiapkan sebagai placeholder integrasi.
- **Security**: CSP aktif, CSRF token, sanitasi dasar. Tambahkan rate-limit di level server/reverse proxy.
- **Struk 58mm**: tampilan HTML sudah sempit; sesuaikan CSS & ESC/POS template sesuai brand Anda (logo PNG sudah dipakai).
