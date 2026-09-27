# Nusantara Booking - Backend API Service

Official documentation for setup, local environment configuration, and development deployment for the **Nusantara Booking API** project. This project runs fully on **Docker Compose** and utilizes a **Makefile** to simplify routine CLI commands.

## 🛠️ Tech Stack & Requirements

* **Framework:** Laravel 11 (PHP 8.2 FPM)
* **Database:** MySQL 8.0
* **Web Server:** Nginx
* **Containerization:** Docker & Docker Compose
* **Node.js:** v20 (Included inside the container)
* **Prerequisites on Local Machine:**
  * [Docker Desktop / Docker Engine](https://www.docker.com/products/docker-desktop/)
  * [Git](https://git-scm.com/)
  * [Make Utility](https://www.gnu.org/software/make/) *(Optional but highly recommended)*

---

## 🚀 First-Time Setup Guide

Follow these steps to set up and run the project on a local development machine:

### 1. Clone the Repository
```bash
git clone https://github.com/rahadian/capstone_project_backend.git
cd nusantara_booking
```

### 2. Set Up the Environment File (`.env`)
Copy `.env.example` into the `code/` directory:
```bash
cp code/.env.example code/.env
```

> **Important Note:** Ensure the database configuration in `code/.env` matches the values defined in `docker-compose.yml`:
> ```env
> APP_URL=http://localhost:8555
> APP_TIMEZONE=Asia/Jakarta
> 
> DB_CONNECTION=mysql
> DB_HOST=nusantara_booking-db
> DB_PORT=3306
> DB_DATABASE=nusantara_booking_web
> DB_USERNAME=nusantara_booking
> DB_PASSWORD=nusantara_booking123
> ```

### 3. Build & Start Containers
Run the following command to build the Docker image and launch all services (`app`, `worker`, `db`, `nginx`):
```bash
make up-build
```

### 4. Install Composer Dependencies
```bash
make composer-install
```

### 5. Generate Application Key
```bash
make artisan ARGS="key:generate"
```

### 6. Run Database Migrations & Seeders
```bash
make migrate-fresh-seed
```

### 7. Generate Swagger API Documentation
```bash
make artisan ARGS="l5-swagger:generate"
```

---

## 🌐 Endpoints & Port Mapping

Once the containers are running, you can access the application at the following endpoints:

| Service | Port / URL | Description |
| :--- | :--- | :--- |
| **Laravel API** | `http://localhost:8555/api` | Base Web Server / API URL |
| **Swagger UI** | `http://localhost:8555/api/documentation` | Interactive API Documentation |
| **MySQL DB** | `localhost:33068` | Access DB via DBeaver / TablePlus / Navicat |

---

## 📋 Makefile Commands Reference

This project includes a `Makefile` to avoid typing long `docker compose exec` commands manually.

### 🐳 Container Management

| Command | Description |
| :--- | :--- |
| `make up` | Start all containers without rebuilding |
| `make up-build` | Start containers and force rebuild images |
| `make down` | Stop and remove all containers |
| `make restart` | Restart all containers |
| `make ps` | View status of running containers |
| `make logs` | View real-time logs for all containers |
| `make logs-worker` | View logs specifically for the Queue Worker container |
| `make shell` | Open a bash shell inside the app container (`www` user) |
| `make shell-worker` | Open a bash shell inside the worker container |

### ⚡ Laravel & Artisan

| Command | Description |
| :--- | :--- |
| `make migrate` | Run database migrations |
| `make migrate-fresh` | Drop all tables and re-run migrations |
| `make migrate-fresh-seed` | Reset DB, re-run migrations, and run seeders |
| `make seed` | Run database seeders |
| `make cache-clear` | Clear config, route, view, and application cache |
| `make artisan ARGS="<command>"` | Run custom Artisan command (e.g., `make artisan ARGS="route:list"`) |

### 📦 Composer & NPM

| Command | Description |
| :--- | :--- |
| `make composer-install` | Install PHP dependencies from `composer.json` |
| `make composer-require ARGS="<package>"` | Install a new PHP package (e.g., `make composer-require ARGS="laravel/sanctum"`) |
| `make composer-dump` | Refresh and optimize class autoloading |
| `make npm-install` | Install Node package dependencies |
| `make npm-build` | Build frontend assets via Vite |
| `make npm-build-watch` | Run frontend build in watch mode |

---

## 📁 Project Structure

```text
├── code/                   # Application Source Code (Laravel)
│   ├── app/                # Models, Controllers, Middleware
│   ├── config/             # Configuration files
│   ├── database/           # Migrations, Seeders, Factories
│   └── routes/             # api.php, web.php
├── nginx/                  # Nginx Configuration
│   └── conf.d/             # Server block definitions
├── php/                    # PHP Settings (local.ini, php-fpm.conf)
├── mysql/                  # Database Init scripts & My.cnf
├── Dockerfile              # Custom PHP 8.2 FPM Image Definition
├── docker-compose.yml      # Container Service Orchestration
└── Makefile                # CLI Shortcut Commands
```

---

## 💡 Troubleshooting & Tips

1. **Permission Denied on `storage` or `bootstrap/cache`:**
   Reset directory permissions inside the container:
   ```bash
   make shell
   # Inside the container shell:
   chmod -R 775 storage bootstrap/cache
   ```

2. **Port Conflicts:**
   If ports `8555` or `33068` are already occupied on your host machine, modify the port mapping under the `ports:` section in `docker-compose.yml`.