# TODO - Fitur Menu Reservasi

- [ ] Update rencana/konfirmasi scope fitur: dine-in dengan meja, alur status (Booked/Cancelled saja atau tambah Seated/Completed)
- [x] Tambah tabel reservasi ke `sql/pos_schema.sql`

- [x] Buat halaman: `public/reservations.php` (daftar reservasi)
- [x] Buat halaman: `public/reservation_create.php` (form buat reservasi)
- [x] Buat halaman: `public/reservation_detail.php` (detail + update status/cancel)

- [ ] Tambahkan endpoint POST (mis. `public/reservation_cancel.php` dan/atau `public/reservation_update_status.php`) bila diperlukan
- [x] Integrasi sidebar: `src/nav/sidebar.php` (menu Reservasi + role gating + active state)

- [ ] Validasi CSRF untuk semua form/aksi POST (sudah ada di form reservasi)

- [ ] Verifikasi query berdasarkan `branch_id` dan role auth (sudah pakai branch_id di query reservasi)

- [ ] Testing manual: create -> list -> detail -> cancel/status update

