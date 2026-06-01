# FITUR BARU: Customer Menu dengan Seleksi Cabang/Branch

## Deskripsi
Menambahkan fitur menu khusus untuk customer yang memungkinkan mereka memilih lokasi cabang/branch terlebih dahulu, kemudian melihat menu sesuai dengan cabang yang dipilih, melakukan pemesanan, dan pembayaran.

## Alur Fitur
```
1. Pilih Lokasi Cabang/Branch
   ↓
2. Lihat Menu Sesuai Cabang/Branch  
   ↓
3. Pesan (Tambah Ke Keranjang)
   ↓
4. Pembayaran (Cash/QRIS)
```

## Cabang yang Tersedia
- **Cafe Batu** (ID: 6)
- **De'Onél** (ID: 8) 
- **Oasis Tropis** (ID: 9)
- **Restoran Khas Sunda Mang Dadang** (ID: 7)
- **Pusat** (ID: 1)

## File yang Ditambahkan

### 1. `customer_branch_select.php` (NEW)
**Fungsi:** Halaman awal untuk memilih cabang/branch
**Fitur:**
- Menampilkan grid semua cabang yang tersedia
- Setiap cabang memiliki icon dan warna unik
- Responsive design untuk mobile & desktop
- Alur proses yang jelas dan intuitif
- Navigasi ke halaman menu dengan parameter `branch_id`

**Alur:**
```
customer_branch_select.php 
   ↓ (user klik cabang)
customer_menu.php?branch_id=6 (atau ID cabang lainnya)
```

### 2. `customer_menu.php` (EXISTING - SUDAH ADA)
**Fungsi:** Halaman menu produk sesuai dengan cabang yang dipilih
**Fitur yang Sudah Ada:**
- Menampilkan produk berdasarkan branch_id
- Kategori filter (Semua, Makanan, Minuman, Lainnya)
- Search produk berdasarkan nama/SKU
- Shopping cart dengan quantity & discount
- Pilihan order type (Dine In / Take Away)
- Input nomor meja (optional)
- Input nama customer (optional)
- Pilihan pembayaran: Cash atau QRIS
- Tampil total dengan tax & service charge

**Database yang Digunakan:**
- `branches` - Daftar cabang
- `products` - Produk (dengan branch_id)
- `stock_branch` - Stok per cabang

### 3. `customer_pos_create_order.php` (EXISTING)
**Fungsi:** Endpoint untuk membuat order dari customer menu
**Fitur:**
- Menerima JSON payload dari customer_menu.php
- Validasi branch_id
- Insert ke tabel `orders` dan `order_items`
- Update stok di tabel `stock_branch`
- Return order_id & total untuk konfirmasi

## Alur Data

### Saat Customer Memilih Cabang:
```
customer_branch_select.php
  └─ Query: SELECT id, name FROM branches
  └─ Display semua cabang
  └─ Link ke: customer_menu.php?branch_id=6
```

### Saat Customer Melihat Menu:
```
customer_menu.php?branch_id=6
  ├─ Validate branch_id
  ├─ Query: SELECT products WHERE branch_id=6
  ├─ Query: SELECT quantity FROM stock_branch WHERE branch_id=6
  ├─ Display produk + stok
  └─ Simpan keranjang ke localStorage
```

### Saat Customer Checkout:
```
customer_menu.php → customer_pos_create_order.php
  ├─ POST JSON dengan items, branch_id, payment_method
  ├─ Validate & insert order
  ├─ Update stock_branch (qty - ordered)
  ├─ Create order_items
  └─ Return order_id untuk receipt/print
```

## Database Schema yang Digunakan

### Branches (sudah ada)
```sql
CREATE TABLE branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL
);
```

### Products (sudah ada)
```sql
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT,          -- Produk milik cabang tertentu
  sku VARCHAR(60) UNIQUE,
  name VARCHAR(160) NOT NULL,
  price INT NOT NULL,
  image VARCHAR(200),
  active TINYINT(1) DEFAULT 1,
  category VARCHAR(50) DEFAULT 'other',
  FOREIGN KEY (branch_id) REFERENCES branches(id)
);
```

### Stock Branch (sudah ada)
```sql
CREATE TABLE stock_branch (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  branch_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  UNIQUE KEY (product_id, branch_id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id)
);
```

### Orders & Order Items (sudah ada)
```sql
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL,
  total INT NOT NULL,
  payment_method VARCHAR(40) NOT NULL,
  status ENUM('pending','paid','cancelled') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT NOT NULL,
  price INT NOT NULL,
  discount INT NOT NULL DEFAULT 0,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);
```

## Cara Menggunakan

### Untuk Customer:
1. Akses `customer_branch_select.php`
2. Pilih cabang yang diinginkan
3. Lihat menu produk yang tersedia
4. Tambahkan produk ke keranjang
5. Atur qty, discount, order type (dine in/takeaway)
6. Pilih metode pembayaran (cash/qris)
7. Konfirmasi pembayaran
8. Order berhasil, stok berkurang otomatis

### Untuk Staff/Admin:
1. Pastikan cabang sudah dibuat di `manage_branches.php`
2. Pastikan produk sudah ditetapkan ke cabang yang tepat di `manage_products.php`
3. Pastikan stok per cabang sudah di-set di `manage_stockbranch.php`
4. Monitor pesanan dari customer di `orders_list.php`

## Testing Checklist

- [ ] Akses `customer_branch_select.php` - tampil semua cabang
- [ ] Klik salah satu cabang - redirect ke `customer_menu.php?branch_id=X`
- [ ] Produk yang ditampilkan sesuai dengan branch_id
- [ ] Stok produk sesuai dengan branch_id
- [ ] Tambah produk ke keranjang
- [ ] Update qty & discount
- [ ] Pilih order type & input optional fields
- [ ] Checkout dengan pembayaran cash
- [ ] Checkout dengan pembayaran qris
- [ ] Verifikasi stok berkurang di `manage_stockbranch.php`
- [ ] Verifikasi order muncul di `orders_list.php` dengan status 'paid'
- [ ] Print receipt berfungsi

## Notes

- Customer tidak perlu login untuk menggunakan menu
- Branch selection tidak persisten di akun (berbasis query string & localStorage)
- Stok real-time dari database, update saat checkout berhasil
- Support multi-cabang dengan menu & harga berbeda per cabang
- Responsive design untuk kiosk touchscreen & mobile device
