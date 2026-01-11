# MSBD Logs

Simple logging helper for WordPress plugin and theme developers with an admin UI to view and manage log files.

---

## 📌 Overview

**MSBD Logs** is a lightweight logging helper for WordPress plugin and theme developers.

It provides a simple PHP API to write log messages and an admin interface to view, filter, and manage log files stored in the WordPress uploads directory.

The plugin is designed to be minimal, dependency-free, and safe for use on production sites.

---

## 🚀 Key Features

- Simple logging function for developers  
- Daily rotating log files  
- Separate log types: `debug` and `attention`  
- Debug logging can be enabled or disabled from admin  
- Admin UI to view and filter log files  
- Secure file handling (capability and nonce protected)  
- No database tables or external services  
- Translation-ready  

---

## 📁 Log Storage Location

All log files are stored in:

```
wp-content/uploads/logs/
```

- The `logs` folder is created automatically on plugin activation  
- An `index.html` file is added to prevent directory browsing  

---

## 🧑‍💻 Developer Usage

```php
msbd_logs_create( 'Log something only when the debug mode is active' );
msbd_logs_create( 'Unexpected issue detected, always log', 'attention' );
```

### Log Types

- **debug** – Logged only when debug mode is enabled  
- **attention** – Always logged  

---

## 🧩 Admin Interface

After activation, a new **MSBD Logs** menu appears in the WordPress admin dashboard.

Administrators can:

- View available log files  
- Filter log files by filename  
- Inspect log file contents  
- Enable or disable debug logging  

Access is restricted to users with the `manage_options` capability.

---

## 📥 Installation

1. Upload the `msbd-logs` folder to `/wp-content/plugins/`
2. Activate via **Plugins**
3. Go to **Admin → MSBD Logs**

---

## ❓ FAQ

**Does this replace WP_DEBUG?**  
No. This is a helper for intentional logging.

**Where are logs stored?**  
`wp-content/uploads/logs/`

**Is it safe for production?**  
Yes. Debug logging can be disabled at any time.

---

## 📝 Changelog

### 1.0.0
- Initial release
- Logging helper
- Admin UI
- Debug toggle
- Secure file handling

---

## 📄 License

GPL v2 or later  
https://www.gnu.org/licenses/gpl-2.0.html
