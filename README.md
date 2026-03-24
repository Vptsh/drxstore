# DRXStore v2.0.0 — Complete Pharmacy Management System
**Developed by Vineet | psvineet@zohomail.in**

---

## 🚀 Quick Start (Termux / XAMPP / Any PHP Server)

1. Extract `drxstore/` to your web root
2. Open browser → `http://localhost/drxstore/`
3. The **Setup Wizard** launches automatically on first run
4. Complete 4 steps: Store Info → Admin Account → Storage → Confirm
5. Login with your credentials

---

## 📋 All Features

### Admin Portal
| Module | Features |
|---|---|
| Dashboard | Live stats, 7-day chart, alerts, top medicines, recent sales |
| Medicines | CRUD, search, dosage forms, HSN, GST%, rack location |
| Import CSV | Bulk import from CSV with preview & duplicate detection |
| Batches/Stock | Full management, expiry tracking, status filters |
| Stock Adjustment | Add/Remove with reason & audit trail |
| Suppliers | CRUD, auto-create portal accounts, email credentials |
| Purchase Orders | Create POs, notify supplier by email, receive |
| New Sale / POS | Cart, customer select, batch picker, live stock/MRP |
| Payment Methods | Cash, UPI (ref no), Cheque (no+bank+date), Card, Credit |
| Discounts | Percentage or flat, per-sale application |
| Sales History | Date/customer filter, full invoice view & print |
| Returns | Process returns, restock, refund tracking |
| Customers | CRUD, purchase history, total spent |
| Ledger | Income/expense/return entries with date filter |
| Analytics | 12-month chart, top medicines, top customers, payment breakdown |
| Expiry Report | Expired/30d/90d/good tier with stock info |
| Users | Add/edit/toggle, role-based (admin/staff) |
| Settings | Store info, currency, thresholds, password change |

### Supplier Portal
- Dashboard with order summary
- View purchase orders, update status (confirmed → shipped → received)
- Contact store via message form (notified by email)
- Profile & password management
- Admin can create supplier accounts and auto-email credentials

### Customer / Patient Portal
- Register with **email verification required**
- View purchase history with printable invoices
- Submit return requests (store notified by email)
- Profile management
- Forgot password → contact `psvineet@zohomail.in`

### Security
- BCrypt password hashing
- CSRF tokens on all forms
- Brute-force protection (5 attempts → 15 min lockout)
- Session timeout (2 hours)
- XSS-safe output everywhere
- Data directory protected from web access

---

## 🔧 Setup Requirements
- PHP 7.4+
- Web server (Apache/Nginx/Termux httpd)
- Write permission on `data/` folder: `chmod 755 data/`
- `mail()` function for email features (optional)

---

## 🗄️ Storage Options
- **JSON** (default) — No setup, works on Termux immediately
- **MySQL** — Select during setup wizard, same interface

---

## 📁 Structure
```
drxstore/
├── index.php           ← Router (all pages via ?p=pagename)
├── config/app.php      ← Bootstrap & constants
├── helpers/
│   ├── JsonDB.php      ← JSON storage engine
│   └── functions.php   ← All utility helpers
├── views/
│   ├── layout_admin.php
│   └── layout_portal.php
├── assets/css/app.css
├── assets/js/app.js
├── data/               ← Auto-created, all JSON data stored here
└── public/
    ├── admin/          ← All admin pages
    ├── supplier/       ← Supplier portal pages
    ├── customer/       ← Customer/patient portal pages
    └── setup/          ← First-launch wizard
```

---

## 📧 Contact
**Developer:** Vineet  
**Email:** psvineet@zohomail.in  

---

*DRXStore v2.0.0 — Built with ❤️ by Vineet*
