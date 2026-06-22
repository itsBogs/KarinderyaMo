# 🍽️ Karinderya Mo - Philippine Food Delivery System

An authentic Pinoy cuisine food delivery system built to streamline ordering, rider management, and wallet transactions for local karinderyas.

## 📸 App Preview

![Front Page](https://github.com/itsBogs/KarinderyaMo/raw/2322bf39a91dc3856c2ab0c7bfb0acca0831d798/PhCuisineSys/front.jpg)

![Access](https://github.com/itsBogs/KarinderyaMo/raw/2322bf39a91dc3856c2ab0c7bfb0acca0831d798/PhCuisineSys/access.jpg)

![Admin](https://github.com/itsBogs/KarinderyaMo/raw/2322bf39a91dc3856c2ab0c7bfb0acca0831d798/PhCuisineSys/admin.jpg)

![Customer](https://github.com/itsBogs/KarinderyaMo/raw/2322bf39a91dc3856c2ab0c7bfb0acca0831d798/PhCuisineSys/customer.jpg)

![Rider](https://github.com/itsBogs/KarinderyaMo/raw/2322bf39a91dc3856c2ab0c7bfb0acca0831d798/PhCuisineSys/rider.jpg)
---

## 👥 System Roles & Features

### 1. 👑 Owner (Restaurateur)
* **Business Overview:** Monitors overall sales, daily revenue, and system activity.
* **Store Analytics:** Views detailed financial reports and total orders processed.
* **System Control:** Full visibility over the platform's performance and operations.

### 2. 🛠️ Admin (System Manager)
* **Menu Management:** Create, Read, Update, and Delete (CRUD) traditional Pinoy dishes, update prices, change availability status, and upload menu images.
* **User Management:** Oversee profiles, activate, or suspend accounts for Customers, Riders, and Admins.
* **Settings Control:** Customize global site properties (e.g., site name, contact configurations, system theme colors).

### 3. 🛵 Rider (Delivery Fleet)
* **Job Board:** View and accept available delivery orders (`pending` / `accepted`).
* **Delivery Status Tracker:** Update order journeys in real-time (`picked_up` ➡️ `in_transit` ➡️ `delivered`).
* **Earnings Wallet:** Track base pay and delivery amounts per successful transaction.

### 4. 👤 Customer (User)
* **Dynamic Menu Browsing:** Browse authentic Filipino meals sorted by interactive categories (e.g., Pork, Chicken, Gulay).
* **Shopping Cart & Checkout:** Add multiple items to the cart, specify delivery addresses, and choose payment methods.
* **Digital Wallet Integration:** Features a virtual bank-style personal wallet secured by a private PIN code for cash-free payments, deposits, and automated refunds.
* **Cash-on-Delivery (COD):** Alternative standard checkout routing.

---

## 🛠️ Tech Stack Used

* **Frontend:** HTML5, CSS3 (Modern Flexbox/Grid layouts), JavaScript (Dynamic Modal Previews)
* **Backend:** PHP (Plain text test environments, core logic engine)
* **Database:** MySQL / MariaDB (Relational design with Cascade/Set Null constraints and built-in table indexing)
* **Styling Font:** Poppins / Segoe UI

---

## 🚀 Installation & Local Setup

### Prerequisites
1. Download and install [XAMPP](https://www.apachefriends.org/).

### Steps
1. **Set up the Database:**
   * Open XAMPP Control Panel and start **Apache** and **MySQL**.
   * Open your web browser and navigate to `http://localhost/phpmyadmin/`.
   * Select your `karinderya_mo` database, click on the **SQL** tab, paste your database schema script, and click **Go**.

2. **Run the Project:**
   * Open your browser and type the local URL link:
```text
     http://localhost:3000/PhCuisineSys/index.php
     ```

---

## 🧪 Default Test Accounts

| Role | Email | Password |
| :--- | :--- | :--- |
| **Admin/Owner** | `admin@karinderya.com` | `admin123` |
| **Rider** | `rider@karinderya.com` | `rider123` |
| **Customer** | `customer@karinderya.com` | `customer123` |

---
*Prepared by Jayson Bogs J. Ramos*
