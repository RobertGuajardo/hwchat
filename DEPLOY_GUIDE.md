# HWChat Deployment Guide — hwchat.robertguajardo.com

## Overview

This sets up HWChat as a separate installation from Bonnie's chatbot. Different subdomain, different database, same server. Bonnie's setup at chat.robertguajardo.com is not touched.

---

## Step 1 — Create Subdomain (InMotion cPanel)

1. Log into cPanel
2. Go to **Domains** → **Subdomains** (or **Domains** depending on cPanel version)
3. Create: `hwchat.robertguajardo.com`
4. Note the document root (e.g., `/home/yourusername/hwchat.robertguajardo.com`)

## Step 2 — Create Database

SSH into the server:

```bash
# Connect to PostgreSQL as the superuser
sudo -u postgres psql

# Create the database and user
CREATE USER hwchat WITH PASSWORD 'pick-a-strong-password';
CREATE DATABASE hwchat OWNER hwchat;

# Enable pgvector
\c hwchat
CREATE EXTENSION vector;

# Grant permissions
GRANT ALL PRIVILEGES ON DATABASE hwchat TO hwchat;

\q
```

## Step 3 — Clone the Codebase

```bash
# Copy everything from the existing installation
cp -r /path/to/chat.robertguajardo.com/* /path/to/hwchat.robertguajardo.com/

# Remove Bonnie's error logs and any cached data
rm -f /path/to/hwchat.robertguajardo.com/error_log
```

## Step 4 — Replace Updated Files

From the build zip, overwrite these files in the new directory:

```
config.php                      ← NEW (replace entirely — update credentials)
api/chat.php                    ← UPDATED (tool-use support)
lib/CecilianXO.php              ← NEW
dashboard/settings.php           ← UPDATED (XO + HubSpot fields)
migrations/006_hillwood_schema.sql  ← NEW
migrations/007_harvest_treeline_tenants.sql ← NEW
setup.sh                         ← NEW (run once)
```

## Step 5 — Update config.php

Open `config.php` and fill in:

- `db_password` / `pg_password` → the password you created in Step 2
- `default_openai_key` → your new OpenAI API key (rotate the old one!)
- `default_anthropic_key` → your new Anthropic API key (rotate the old one!)
- `encryption_key` → any random string

## Step 6 — Run Setup

```bash
cd /path/to/hwchat.robertguajardo.com
bash setup.sh
```

This runs all 7 migrations and creates both the Harvest and Treeline tenants.

## Step 7 — Verify

Open in your browser:

- **Dashboard:** https://hwchat.robertguajardo.com/dashboard
  - Login with: `harvest@hillwoodcommunities.com` / `HWChat2026!`
  - **Change the password immediately**
  
- **Health check:** https://hwchat.robertguajardo.com/api/health.php

- **Widget test:** https://hwchat.robertguajardo.com/index.html
  (Update the tenant ID in the page to `hw_harvest` or `hw_treeline`)

## Step 8 — Populate Knowledge Bases

1. Log into the Harvest dashboard
2. Go to **Knowledge Base**
3. Use the website scraper to scrape `https://www.harvestbyhillwood.com`
4. Repeat for Treeline: `https://www.treelinebyhillwood.com`

## Step 9 — Deploy to WordPress Sites

Drop these on the community WordPress sites:

**Harvest:**
```html
<script src="https://hwchat.robertguajardo.com/widget/robchat.js" data-robchat-id="hw_harvest" defer></script>
```

**Treeline:**
```html
<script src="https://hwchat.robertguajardo.com/widget/robchat.js" data-robchat-id="hw_treeline" defer></script>
```

---

## File Map (what changed vs. original)

| File | Status | What |
|---|---|---|
| `config.php` | REPLACED | New DB credentials, Hillwood origins |
| `api/chat.php` | UPDATED | XO tool-use support, fixed hours label |
| `lib/CecilianXO.php` | NEW | Cecilian XO API client |
| `dashboard/settings.php` | UPDATED | XO, HubSpot, Community fields |
| `migrations/006_hillwood_schema.sql` | NEW | Schema additions |
| `migrations/007_harvest_treeline_tenants.sql` | NEW | Harvest + Treeline tenant setup |
| `setup.sh` | NEW | One-command database setup |

Everything else (widget, bootstrap, Database class, Embeddings class, other dashboard pages, other API endpoints) is unchanged and carries over from the clone.
