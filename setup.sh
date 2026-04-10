#!/bin/bash
# ============================================================
# HWChat Setup Script
# ============================================================
# Run this ONCE after:
#   1. Creating the hwchat database and user in PostgreSQL
#   2. Enabling pgvector: CREATE EXTENSION vector;
#   3. Updating config.php with your credentials
#
# Usage: bash setup.sh
# ============================================================

set -e

DB_NAME="hwchat"
DB_USER="hwchat"

echo "============================================"
echo "  HWChat — Database Setup"
echo "============================================"
echo ""

# Check if we can connect
echo "→ Testing database connection..."
psql -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1;" > /dev/null 2>&1 || {
    echo "✗ Cannot connect to database '$DB_NAME' as user '$DB_USER'"
    echo "  Make sure the database and user exist:"
    echo "    CREATE USER hwchat WITH PASSWORD 'your-password';"
    echo "    CREATE DATABASE hwchat OWNER hwchat;"
    echo "    \\c hwchat"
    echo "    CREATE EXTENSION vector;"
    exit 1
}
echo "✓ Connected to $DB_NAME"

# Check pgvector
echo "→ Checking pgvector extension..."
psql -U "$DB_USER" -d "$DB_NAME" -c "SELECT extname FROM pg_extension WHERE extname='vector';" | grep -q vector || {
    echo "✗ pgvector extension not installed."
    echo "  Run: psql -U postgres -d $DB_NAME -c 'CREATE EXTENSION vector;'"
    exit 1
}
echo "✓ pgvector is enabled"

# Run migrations in order
MIGRATIONS_DIR="$(dirname "$0")/migrations"

echo ""
echo "→ Running migrations..."

for f in "$MIGRATIONS_DIR"/001_initial_schema.sql \
         "$MIGRATIONS_DIR"/002_scheduler_schema.sql \
         "$MIGRATIONS_DIR"/003_knowledge_base_schema.sql \
         "$MIGRATIONS_DIR"/004_plugin_architecture_schema.sql \
         "$MIGRATIONS_DIR"/005_add_lead_type.sql \
         "$MIGRATIONS_DIR"/006_hillwood_schema.sql \
         "$MIGRATIONS_DIR"/007_harvest_treeline_tenants.sql; do

    if [ -f "$f" ]; then
        BASENAME=$(basename "$f")
        echo "  → $BASENAME"
        psql -U "$DB_USER" -d "$DB_NAME" -f "$f" > /dev/null 2>&1
        echo "    ✓ done"
    else
        echo "  ✗ Missing: $f"
        exit 1
    fi
done

# Add pgvector embedding column (migration 003 has it as a comment, so add it explicitly)
echo ""
echo "→ Adding pgvector embedding column to kb_entries..."
psql -U "$DB_USER" -d "$DB_NAME" -c "
    ALTER TABLE kb_entries ADD COLUMN IF NOT EXISTS embedding vector(1536);
    CREATE INDEX IF NOT EXISTS idx_kb_entries_embedding ON kb_entries USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);
" > /dev/null 2>&1
echo "✓ Embedding column ready"

echo ""
echo "============================================"
echo "  ✓ Setup complete!"
echo "============================================"
echo ""
echo "Tenants created:"
psql -U "$DB_USER" -d "$DB_NAME" -c "SELECT id, display_name, community_location, xo_project_slug FROM tenants;"
echo ""
echo "Next steps:"
echo "  1. Update config.php with your API keys and DB password"
echo "  2. Log in to the dashboard at https://hwchat.robertguajardo.com/dashboard"
echo "     Harvest:  harvest@hillwoodcommunities.com / HWChat2026!"
echo "     Treeline: treeline@hillwoodcommunities.com / HWChat2026!"
echo "  3. Change the default passwords immediately"
echo "  4. Scrape community websites to populate knowledge bases"
echo "  5. Drop embed codes on the WordPress sites"
echo ""
