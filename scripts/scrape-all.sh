#!/bin/bash
# scrape-all.sh — Run the universal WP scraper for every Hillwood community
# Usage: bash scrape-all.sh [--dry-run]
#
# Run from the project root:
#   cd /home/rober253/hwchat.robertguajardo.com
#   bash scripts/scrape-all.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SCRAPER="$SCRIPT_DIR/scrape-wp-universal.php"
LOG_DIR="$SCRIPT_DIR/../logs"
mkdir -p "$LOG_DIR"

DRY_RUN=0
if [ "$1" = "--dry-run" ]; then
    DRY_RUN=1
    echo "=== DRY RUN — showing what would be scraped ==="
    echo ""
fi

# Tenant ID | Site URL | Description
COMMUNITIES=(
    "hw_harvest|https://www.harvestbyhillwood.com|Harvest - Argyle, TX"
    "hw_treeline|https://www.treelinebyhillwood.com|Treeline - Justin, TX"
    "hw_pecan_square|https://www.pecansquarebyhillwood.com|Pecan Square - Northlake, TX"
    "hw_union_park|https://www.unionparkbyhillwood.com|Union Park - Little Elm, TX"
    "hw_wolf_ranch|https://www.wolfranchbyhillwood.com|Wolf Ranch - Georgetown, TX"
    "hw_lilyana|https://www.lilyanabyhillwood.com|Lilyana - Celina, TX"
    "hw_valencia|https://www.valenciabyhillwood.com|Valencia - Manvel, TX"
    "hw_pomona|https://www.pomonabyhillwood.com|Pomona - Manvel, TX"
    "hw_legacy|https://www.legacybyhillwood.com|Legacy - League City, TX"
    "hw_landmark|https://www.landmarkbyhillwood.com|Landmark - Denton, TX"
    "hw_ramble|https://www.ramblebyhillwood.com|Ramble - Celina, TX"
    "hw_melina|https://www.melinabyhillwood.com|Melina - Georgetown, TX"
    "hw_parent|https://www.hillwoodcommunities.com|Hillwood Communities (parent)"
)

TOTAL=${#COMMUNITIES[@]}
SUCCESS=0
FAILED=0
SKIPPED=0

echo "============================================"
echo "  HWChat — Batch KB Scraper"
echo "  $(date)"
echo "  $TOTAL communities to process"
echo "============================================"
echo ""

for entry in "${COMMUNITIES[@]}"; do
    IFS='|' read -r TENANT_ID SITE_URL DESCRIPTION <<< "$entry"

    echo "--- [$((SUCCESS + FAILED + SKIPPED + 1))/$TOTAL] $DESCRIPTION ---"
    echo "    Tenant: $TENANT_ID"
    echo "    URL:    $SITE_URL"

    if [ $DRY_RUN -eq 1 ]; then
        echo "    [DRY RUN] Would scrape: php $SCRAPER $TENANT_ID $SITE_URL"
        echo ""
        continue
    fi

    # Quick check: is the WP REST API reachable?
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$SITE_URL/wp-json/wp/v2/pages?per_page=1" 2>/dev/null || echo "000")

    if [ "$HTTP_CODE" = "000" ] || [ "$HTTP_CODE" = "403" ] || [ "$HTTP_CODE" = "404" ]; then
        echo "    ⚠️  SKIPPED — WP REST API not accessible (HTTP $HTTP_CODE)"
        echo "    (May need 'Show in REST API' enabled on ACF fields)"
        SKIPPED=$((SKIPPED + 1))
        echo ""
        continue
    fi

    LOGFILE="$LOG_DIR/scrape_${TENANT_ID}_$(date +%Y%m%d_%H%M%S).log"
    echo "    Scraping... (log: $LOGFILE)"

    if php "$SCRAPER" "$TENANT_ID" "$SITE_URL" 100 2>&1 | tee "$LOGFILE"; then
        echo "    ✅ Done"
        SUCCESS=$((SUCCESS + 1))
    else
        echo "    ❌ Failed — check log"
        FAILED=$((FAILED + 1))
    fi

    echo ""
done

echo "============================================"
echo "  COMPLETE"
echo "  Success: $SUCCESS"
echo "  Failed:  $FAILED"
echo "  Skipped: $SKIPPED (API not accessible)"
echo "============================================"

if [ $SKIPPED -gt 0 ]; then
    echo ""
    echo "For skipped sites, ensure 'Show in REST API' is enabled"
    echo "on ACF field groups in each site's WP admin."
fi
