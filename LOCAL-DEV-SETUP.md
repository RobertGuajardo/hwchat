# HWChat — Local Development Setup

## Prerequisites

- Docker Desktop (installed ✅)
- PHP 8.4 (for running the built-in server locally)
- Git

## First-Time Setup

### 1. Create the docker folder

Place the `docker/` folder and `docker-compose.yml` in your project root:

```
hwchat/
├── docker/
│   └── init-db.sh
├── docker-compose.yml
├── config.local.php
├── ... (existing project files)
```

### 2. Start the database

```bash
cd C:\Projects\hwchat
docker compose up -d
```

This will:
- Pull the `pgvector/pgvector:pg16` image (PostgreSQL 16 + pgvector)
- Create the `hwchat` database with user `hwchat`
- Run all migrations from `migrations/` in order
- Enable the pgvector extension

First run takes a minute. Subsequent starts are instant.

### 3. Set up local config

```bash
copy config.local.php config.php
```

This points your app at the Docker database on port 5433.

**Important:** If you already have a config.php with production credentials,
rename it first: `rename config.php config.production.php`

### 4. Verify the database

```bash
docker exec -it hwchat-db psql -U hwchat -d hwchat -c "\dt"
```

You should see all your tables listed.

### 5. Start the PHP dev server

```bash
php -S localhost:8000
```

Open `http://localhost:8000/dashboard/` in your browser.

## Day-to-Day Usage

```bash
# Start the database
docker compose up -d

# Stop the database (data persists)
docker compose down

# Check if it's running
docker ps

# Connect to the database directly
docker exec -it hwchat-db psql -U hwchat -d hwchat

# Run a new migration
docker exec -it hwchat-db psql -U hwchat -d hwchat -f /docker-entrypoint-initdb.d/migrations/017_analytics_schema.sql

# View table structure
docker exec -it hwchat-db psql -U hwchat -d hwchat -c "\d chat_analytics"

# Nuke the database and start fresh (re-runs all migrations)
docker compose down -v
docker compose up -d
```

## Running New Migrations

Since the init script only runs on first startup, new migrations need to be run manually:

```bash
docker exec -it hwchat-db psql -U hwchat -d hwchat -f /docker-entrypoint-initdb.d/migrations/017_analytics_schema.sql
```

Or connect interactively and paste the SQL:

```bash
docker exec -it hwchat-db psql -U hwchat -d hwchat
```

## Deploying to Production

After testing locally:

1. SSH into the VPS: `ssh root@192.249.120.89`
2. Pull the latest code
3. Run the new migration against production:
   ```bash
   psql -U hwchat -d hwchat -f migrations/017_analytics_schema.sql
   ```
4. Restart services:
   ```bash
   systemctl restart ea-php82-php-fpm ea-php83-php-fpm httpd
   ```

## Notes

- Docker PostgreSQL runs on port **5433** to avoid conflicts
- Data persists in a Docker volume — `docker compose down` keeps your data
- `docker compose down -v` deletes the volume and resets the database
- The init script only runs when the volume is empty (first start or after `-v`)
