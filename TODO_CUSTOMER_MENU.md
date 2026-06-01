# TODO - Customer Menu & Transaction

- [ ] Buat UI customer menu: `public/customer_menu.php`
  - [ ] Grid produk berdasarkan branch_id
  - [ ] Add ke keranjang (qty, disc)
  - [ ] Input order_type (dinein/takeaway), table_no, customer_name (opsional), tax, service
  - [ ] Pilih metode bayar: cash/qris
  - [ ] Submit ke endpoint customer

- [ ] Buat endpoint customer transaction: `public/customer_pos_create_order.php`
  - [ ] Tidak menggunakan `auth_required`
  - [ ] Validasi branch_id dari query string
  - [ ] Simpan ke `orders` dan `order_items`
  - [ ] Update stok saat direct payment (cash/qris)
  - [ ] Untuk cash: simpan `cash_transactions` jika ada cash_given
  - [ ] Untuk qris: set payment_method='qris', status='paid'

- [ ] (Opsional) Tambah endpoint open bill untuk customer: `public/customer_pos_open_bill.php`

- [ ] Update sidebar staff agar ada link ke customer menu (untuk testing kiosk): `src/nav/sidebar.php`

- [ ] Testing manual
  - [ ] cash payment
  - [ ] qris payment
  - [ ] cek stok berkurang
  - [ ] cek order muncul di `orders_list.php` sebagai pending (jika open bill) atau paid (jika direct)

