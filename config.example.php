<?php
/**
 * POS Website Starter - Configuration Template for cPanel Hosting
 * 
 * INSTRUCTIONS:
 * 1. Copy this file as 'config.php' in the root directory
 * 2. Edit the values below with your actual hosting credentials
 * 3. Change all `[YOUR_*]` placeholders to actual values from cPanel
 * 4. Delete the .example from filename
 * 5. NEVER commit config.php to git (it contains sensitive data)
 */

date_default_timezone_set('Asia/Jakarta');

// =====================================================================
// 🔥 CRITICAL: Session & Output Buffering (Prevents header errors)
// =====================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!ob_get_level()) {
    ob_start();
}

// =====================================================================
// 📊 DATABASE CONFIGURATION - EDIT THIS SECTION FOR CPANEL
// =====================================================================
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
// From cPanel > MySQL Databases, copy "Your full MySQL database name"
// Example: yourusername_pos_db
if (!defined('DB_NAME')) define('DB_NAME', '[YOUR_CPANEL_USERNAME]_pos_db');

// From cPanel > MySQL Databases > MySQL Users, copy the username
// Example: yourusername_posuser
if (!defined('DB_USER')) define('DB_USER', '[YOUR_CPANEL_USERNAME]_posuser');

// Password you set when creating the MySQL user in cPanel
if (!defined('DB_PASS')) define('DB_PASS', '[YOUR_MYSQL_PASSWORD]');

// =====================================================================
// 🌐 BASE URL - EDIT THIS FOR YOUR DOMAIN
// =====================================================================
// If app is in root:
// if (!defined('BASE_URL')) define('BASE_URL', 'https://yourdomain.com/');

// If app is in subfolder (e.g., /pos/):
// if (!defined('BASE_URL')) define('BASE_URL', 'https://yourdomain.com/pos/');

// Default (localhost):
if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost/pos-web-starter/');

// =====================================================================
// 🎫 SESSION CONFIGURATION
// =====================================================================
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'pos_session');
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 1800); // 30 minutes

// =====================================================================
// 🏪 APPLICATION NAME & BRANDING
// =====================================================================
if (!defined('APP_NAME')) define('APP_NAME', 'POS Kencana Lima Delapan');

// =====================================================================
// 💰 TAX & BILLING CONFIGURATION
// =====================================================================
// Default tax rate (11% for Indonesia/PPN)
if (!defined('PPN_DEFAULT')) define('PPN_DEFAULT', 0.11);

// =====================================================================
// 💳 PAYMENT GATEWAY CONFIGURATION
// =====================================================================
// QRIS Payment (Sandbox for testing, change to production key when live)
if (!defined('QRIS_KEY')) define('QRIS_KEY', 'sandbox_key');
if (!defined('QRIS_GATEWAY')) define('QRIS_GATEWAY', 'https://sandbox.gateway.com/api');

// =====================================================================
// 🏬 STORE/OUTLET INFORMATION (For Receipts & Reports)
// =====================================================================
if (!defined('STORE_NAME')) define('STORE_NAME', 'PT Kencana Lima Delapan');
if (!defined('STORE_ADDRESS')) define('STORE_ADDRESS', 'Jl. Moh. Toha No. 123, Bandung, Jawa Barat');
if (!defined('STORE_PHONE')) define('STORE_PHONE', '(0274) 555-0123');
if (!defined('STORE_EMAIL')) define('STORE_EMAIL', 'info@kencanalimalapan.com');

// =====================================================================
// 📸 LOGO & BRANDING ASSETS
// =====================================================================
// Logo path for receipts (must be PNG, small size for thermal printer)
// Path relative to project root
if (!defined('LOGO_PATH')) define('LOGO_PATH', __DIR__ . '/public/assets/logo.png');

// =====================================================================
// 👥 DEFAULT USER ROLE
// =====================================================================
// Roles: kasir, spv, admin, superadmin, spv_warehouse
if (!defined('DEFAULT_ROLE')) define('DEFAULT_ROLE', 'kasir');

// =====================================================================
// 🏢 DEFAULT BRANCH ID
// =====================================================================
// Use 1 if only single branch, or set default branch ID for new users
if (!defined('DEFAULT_BRANCH_ID')) define('DEFAULT_BRANCH_ID', 1);

// =====================================================================
// 🔐 SECURITY SETTINGS
// =====================================================================
// Enable/Disable CSRF Protection (recommended: true)
if (!defined('ENABLE_CSRF')) define('ENABLE_CSRF', true);

// Enable/Disable 2FA TOTP (recommended: true for admin accounts)
if (!defined('ENABLE_TOTP')) define('ENABLE_TOTP', true);

// Session cookie secure flag (true for HTTPS only)
if (!defined('SESSION_SECURE')) define('SESSION_SECURE', true);

// Session cookie httponly flag (prevents JS access)
if (!defined('SESSION_HTTPONLY')) define('SESSION_HTTPONLY', true);

// =====================================================================
// ✨ FEATURE TOGGLES
// =====================================================================
// Enable Ticket/Kitchen Display System module
define('ENABLE_TICKET_MODULE', false); // Set to true to enable

// Enable Customer Menu / Self-Order Kiosk
define('ENABLE_CUSTOMER_MENU', true); // Set to false to disable

// Enable Credit/Account system
define('ENABLE_CREDIT_SYSTEM', true);

// Enable Reservations (Dine-in with table management)
define('ENABLE_RESERVATIONS', true);

// =====================================================================
// 📁 FILE PATHS (Usually no need to modify)
// =====================================================================
if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', __DIR__ . '/public/uploads/');
if (!defined('LOGS_DIR')) define('LOGS_DIR', __DIR__ . '/var/logs/');
if (!defined('TEMP_DIR')) define('TEMP_DIR', __DIR__ . '/temp/');

// =====================================================================
// 🔔 EMAIL CONFIGURATION (Optional, for notifications)
// =====================================================================
// If you want email notifications for orders, receipts, etc.
define('MAIL_FROM', 'noreply@yourdomain.com');
define('MAIL_FROM_NAME', 'PT Kencana Lima Delapan');
// SMTP Configuration (optional)
// define('SMTP_HOST', 'smtp.gmail.com');
// define('SMTP_PORT', 587);
// define('SMTP_USER', 'your-email@gmail.com');
// define('SMTP_PASS', 'your-app-password');

// =====================================================================
// ℹ️ ENVIRONMENT DETECTION
// =====================================================================
define('ENVIRONMENT', 'production'); // 'development' or 'production'

// =====================================================================
// 🛠️ DEBUGGING (Set to false in production!)
// =====================================================================
if (!defined('DEBUG_MODE')) define('DEBUG_MODE', false); // Set true for debugging only
if (!defined('SHOW_ERRORS')) define('SHOW_ERRORS', false);

// =====================================================================
// DATABASE CONNECTION FUNCTION (Do not modify)
// =====================================================================
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            if (DEBUG_MODE) {
                die("Connection failed: " . $conn->connect_error);
            } else {
                die("Database connection error. Please contact administrator.");
            }
        }
        
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

// Test connection on development mode only
if (DEBUG_MODE && !function_exists('test_db')) {
    function test_db() {
        $conn = getDBConnection();
        return $conn->ping();
    }
}

?>
