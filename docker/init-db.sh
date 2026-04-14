#!/bin/bash
# This script runs on first container startup only (when the data volume is empty).
# It executes all migrations in numerical order to build the full schema.

set -e

MIGRATION_DIR="/docker-entrypoint-initdb.d/migrations"

echo "=== HWChat: Running migrations ==="

# Enable pgvector extension
psql -U hwchat -d hwchat -c "CREATE EXTENSION IF NOT EXISTS vector;"
echo "pgvector extension enabled"

# Run each migration in order
for f in $(ls "$MIGRATION_DIR"/*.sql 2>/dev/null | sort); do
    echo "Running: $(basename $f)"
    psql -U hwchat -d hwchat -f "$f"
done

echo "=== HWChat: All migrations complete ==="
