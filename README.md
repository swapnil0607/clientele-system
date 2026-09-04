# Clientele Online — B2B Enterprise Client Directory & Showcase

[![PHP 8+](https://img.shields.io/badge/PHP-8.0+-777bb4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![MySQL 8+](https://img.shields.io/badge/MySQL-8.0+-4479a1.svg?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com)
[![Security](https://img.shields.io/badge/Auth-Bcrypt%20Protected-10b981.svg?style=flat-square)](#admin-demo-access)
[![License](https://img.shields.io/badge/License-Portfolio%20Showcase-6366f1.svg?style=flat-square)](#)

An enterprise-grade **Client Brand Directory and Interactive Media Showcase** designed for B2B consultancies, agency holding groups, and enterprise software firms. Provides high-impact brand presentation categorized across 8 major industry verticals, paired with an administrative management portal.

---

## 🌟 Key Capabilities

### 1. Interactive Public Showcase (`index.php`)
- **Industry Vertical Filters**: Seamless tab filtering across Automotive, Banking & Finance (BFSI), Healthcare & Pharma, Technology & SaaS, Retail & FMCG, Media & Entertainment, and Higher Education.
- **Dynamic Brand Cards**: Interactive logos, client case study URLs, metadata badges, and responsive CSS grid layout.
- **Search & Quick Look**: Real-time filtering and category count badges.

### 2. Administrative Control Suite (`/admin/`)
- **Client Management**: Add, update, re-order, or archive client profiles and logos.
- **Category Manager**: Create custom industry domains and assign display priorities.
- **Audit Logging**: Comprehensive action timestamps tracking administrative modifications.
- **Secure Authentication**: Bcrypt password verification and secure session handling.

---

## 🔐 Admin Demo Access

| Account | Username | Password | Role |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `admin` | `Demo@2026!` | Full administrative portal access |
| **Executive** | `swapnil` | `Demo@2026!` | Portfolio management and updates |

---

## 🏗️ System Architecture

```mermaid
graph TD
    User[Client / Recruiter] --> Public[Public Directory / index.php]
    AdminUser[Authorized Staff] --> AdminLogin[Admin Portal / admin/login.php]
    AdminLogin --> AdminDash[Admin Dashboard & CMS]
    Public --> DB[(MySQL 8 Database: demo_clientele)]
    AdminDash --> DB
```

### Relational Database Schema
- `clientele_users`: Administrative user profiles and hashed credentials.
- `categories`: Industry taxonomy with slugs and sort orders.
- `clients`: Brand names, category references, website URLs, logo image paths, and active flags.
- `audit_logs`: Detailed activity audit trail.

---

## 🚀 Quick Start & Local Setup (XAMPP / Apache)

### 1. Clone the Repository
```bash
git clone https://github.com/swapnil0607/clientele-system.git clientele
```

### 2. Database Initialization
Create database in MySQL:
```sql
CREATE DATABASE demo_clientele CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Import Schemas & Sanitized Seeds
```bash
mysql -u root demo_clientele < database/schema.sql
mysql -u root demo_clientele < database/seed_dummy.sql
```

### 4. Run Application
- Public Portal: `http://localhost/clientele/`
- Admin Dashboard: `http://localhost/clientele/admin/login.php`

---

## 🛡️ Security & Sanitization Notice
This repository contains **100% synthetic client names and dummy brand placeholders**. All corporate identities, partner lists, and internal references have been completely fictionalized to ensure full NDA compliance and corporate data confidentiality.
