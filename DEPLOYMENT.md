# 🌐 Universal Exam Datesheet Generator - Complete Deployment Guide

This guide covers step-by-step instructions for deploying the **Exam Datesheet Generator** online so that administrators, faculty, and students can access it securely from anywhere.

---

## 📋 Minimum Server Requirements
- **PHP:** `^8.2` or `^8.3` (Extensions: `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `gd`)
- **Database:** MySQL `8.0+` or MariaDB `10.5+`
- **Web Server:** Nginx or Apache
- **Package Manager:** Composer 2.x
- *(Note: Node.js / NPM is **not required** on the production server because UI uses lightweight production CDN bundles).*

---

## 🚀 Option 1: Cloud VPS Deployment (Ubuntu 22.04 / 24.04 LTS) - Recommended
*Best for full control, maximum speed, and custom domains with free Let's Encrypt SSL.*

### Step 1: Install System Packages
SSH into your server as `root` or `sudo` user:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git curl unzip zip
sudo apt install -y php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip php8.3-gd

# Install Composer 2
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Step 2: Setup MySQL Database
```bash
sudo mysql -u root
```
Inside the MySQL shell:
```sql
CREATE DATABASE datesheet_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'datesheet_user'@'localhost' IDENTIFIED BY 'YourStrongPassword123!';
GRANT ALL PRIVILEGES ON datesheet_db.* TO 'datesheet_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 3: Clone Codebase & Install Dependencies
```bash
cd /var/www
git clone https://github.com/your-username/datesheet.git datesheet
cd /var/www/datesheet

# Install production dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Setup Environment File
cp .env.example .env
nano .env
```
Inside `.env`, configure:
```ini
APP_NAME="Exam Datesheet Generator"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=datesheet_db
DB_USERNAME=datesheet_user
DB_PASSWORD=YourStrongPassword123!

QUEUE_CONNECTION=sync
```

Generate application key and run migrations:
```bash
php artisan key:generate
php artisan migrate --force

# Seed official BGNU benchmark data (optional)
php artisan datesheet:demo
```

### Step 4: Configure Storage Permissions & Symlink
```bash
php artisan storage:link
sudo chown -R www-data:www-data /var/www/datesheet
sudo chmod -R 775 /var/www/datesheet/storage /var/www/datesheet/bootstrap/cache
```

### Step 5: Configure Nginx & Free SSL
Copy the provided Nginx configuration:
```bash
sudo cp /var/www/datesheet/nginx-datesheet.conf /etc/nginx/sites-available/datesheet
sudo nano /etc/nginx/sites-available/datesheet
```
*(Replace `yourdomain.com` with your actual domain or VPS IP).*

Activate site and reload Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/datesheet /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Install free SSL via Certbot:
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

### Step 6: Production Optimizations
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## ☁️ Option 2: Cloud PaaS (Railway / Render / Fly.io)
*Best for 1-click deployment directly from your GitHub repository with zero server management.*

1. Push your repository to GitHub (`git push origin main`).
2. Go to [Railway.app](https://railway.app) or [Render.com](https://render.com).
3. Create a **New Project** &rarr; Select **GitHub Repo** &rarr; Select your `datesheet` repository.
4. Add a **MySQL Database** service on the platform.
5. In the Web Service Environment Variables, set:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_KEY` (Generate via `php artisan key:generate --show`)
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (from the MySQL service)
   - `QUEUE_CONNECTION=sync`
6. Build Command: `composer install --no-dev --optimize-autoloader`
7. Start Command: `php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT`

---

## 📁 Option 3: Shared Hosting / cPanel (Hostinger, Namecheap, etc.)
*Best for traditional shared hosting with cPanel and phpMyAdmin.*

1. **Prepare ZIP on Local Machine:**
   - Zip the entire project folder **including** the `vendor/` folder.
2. **Upload to cPanel:**
   - In cPanel File Manager, upload the ZIP to your root directory (e.g. `/home/username/datesheet/`).
   - Extract files.
   - Point your Domain's **Document Root** to `/home/username/datesheet/public/`.
3. **Database Setup:**
   - In cPanel &rarr; **MySQL Databases**, create a new database and user.
   - Assign user with `ALL PRIVILEGES`.
   - In File Manager, edit `.env` and fill in database credentials.
4. **Run Migrations:**
   - In cPanel **Terminal** (or Cron Job):
     ```bash
     php /home/username/datesheet/artisan migrate --force
     php /home/username/datesheet/artisan datesheet:demo
     ```

---

## ⚙️ Maintenance & Updates
Whenever you update code in the future:
```bash
./deploy.sh
```
This automatically updates files, runs new migrations, and clears caches without downtime!
