<?php
/**
 * Cecilian XO API Client — Rebuilt v2
 *
 * Changes from v1:
 *   - Smart sort: LLM passes sort_by instead of hardcoded status-first
 *   - Full pagination: fetch_all flag loops all pages (fixes cheapest/expensive queries)
 *   - Rich formatForChat: includes photo, gallery, floor plan, virtual tour, plan name
 *   - Segmentation filter: duplexes, rear-load, zipper lots vs standard single-family
 *   - Placeholder image filtering: skips XO placeholder images
 *   - Homesite price fix: uses homesite_price when listing_price is "-"
 *
 * API Endpoints:
 *   GET {base_url}/homes?project={slug}&price_range_max=X&builder=X&page=N
 *   GET {base_url}/homesites?project={slug}&price_range_max=X&builder=X&page=N
 *
 * Response shape:
 *   { "count": 42, "next": "url|null", "results": [ ...properties ] }
 */

class CecilianXO
{
    private string $baseUrl;
    private string $projectSlug;

    /** Max pages to fetch when fetch_all is true (safety cap) */
    private const MAX_PAGES = 10;

    public function __construct(string $baseUrl, string $projectSlug)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->projectSlug = $projectSlug;
    }

    /**
     * Search for available properties based on criteria from the LLM.
     *
     * @param array $criteria
     *   price_max         int|null     Max price
     *   beds_min          int|null     Min bedrooms
     *   baths_min         float|null   Min bathrooms
     *   builder           string|null  Builder name filter
     *   type              string|null  'home', 'homesite', or null for both
     *   status            string|null  'move in ready', 'construction', 'available'
     *   stories           int|null     Number of stories
     *   sqft_min          int|null     Min square footage
     *   sqft_max          int|null     Max square footage
     *   sort_by           string|null  'price_asc'|'price_desc'|'status_first'|'sqft_desc'|'sqft_asc'
     *   fetch_all         bool         Fetch all pages (required for cheapest/most expensive queries)
     *   segmentation_type string|null  'duplex'|'rear'|'zipper'|'standard'
     * @param int $limit  Max results to return
     * @return array  { properties: [...], total_homes, total_homesites, total_matching, total_available, error }
     */
    public function search(array $criteria = [], int $limit = 5): array
    {
        $homes = [];
        $homesites = [];
        $homesTotal = 0;
        $homesitesTotal = 0;
        $error = null;

        $type     = $criteria['type'] ?? null;
        $fetchAll = !empty($criteria['fetch_all']) || !empty($criteria['listing_type']) || !empty($criteria['lot_type']);

        try {
            if ($type !== 'homesite') {
                if ($fetchAll) {
                    [$homes, $homesTotal] = $this->fetchAllPages('homes', $criteria);
                } else {
                    $result    = $this->fetchEndpoint('homes', $criteria);
                    $homes     = $result['results'] ?? [];
                    $homesTotal = $result['count'] ?? 0;
                }
            }

            if ($type !== 'home') {
                if ($fetchAll) {
                    [$homesites, $homesitesTotal] = $this->fetchAllPages('homesites', $criteria);
                } else {
                    $result        = $this->fetchEndpoint('homesites', $criteria);
                    $homesites     = $result['results'] ?? [];
                    $homesitesTotal = $result['count'] ?? 0;
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log("CecilianXO API error: " . $error);
        }

        // Combine and client-filter
        $all      = array_merge($homes, $homesites);
        $filtered = array_values($this->applyClientFilters($all, $criteria));

        // Sort (LLM-controlled, default price ascending)
        $this->applySort($filtered, $criteria['sort_by'] ?? 'price_asc');

        // Track total before slicing
        $totalMatching = count($filtered);

        // Count by listing type for the LLM to reference
        $mirCount = 0;
        $ucCount  = 0;
        $hsCount  = 0;
        foreach ($filtered as $prop) {
            $lt = $this->resolveListingType($prop);
            if ($lt === 'move_in_ready')       $mirCount++;
            elseif ($lt === 'under_construction') $ucCount++;
            else                                  $hsCount++;
        }

        // Limit for chat
        $showing = min($limit, $totalMatching);
        $filtered = array_slice($filtered, 0, $limit);

        return [
            'summary'              => "Showing $showing of $totalMatching matching properties. Breakdown: $mirCount move-in ready, $ucCount under construction, $hsCount home sites.",
            'properties'           => array_map([$this, 'formatForChat'], $filtered),
            'total_homes'          => $homesTotal,
            'total_homesites'      => $homesitesTotal,
            'total_matching'       => $totalMatching,
            'move_in_ready_count'  => $mirCount,
            'under_construction_count' => $ucCount,
            'homesite_count'       => $hsCount,
            'total_available'      => $homesTotal + $homesitesTotal,
            'error'                => $error,
        ];
    }

    /**
     * Get all builders active in this community.
     */
    public function getBuilders(): array
    {
        $builders = [];
        try {
            foreach (['homes', 'homesites'] as $endpoint) {
                $result = $this->fetchEndpoint($endpoint, []);
                foreach ($result['results'] ?? [] as $prop) {
                    $name = $prop['builder']['name'] ?? null;
                    if ($name && !isset($builders[$name])) {
                        $builders[$name] = [
                            'name'    => $name,
                            'logo'    => $prop['builder']['logo'] ?? null,
                            'phone'   => $prop['builder']['phone'] ?? null,
                            'website' => $prop['builder']['website'] ?? null,
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("CecilianXO getBuilders error: " . $e->getMessage());
        }
        return array_values($builders);
    }

    // =========================================================================
    // PRIVATE METHODS
    // =========================================================================

    /**
     * Fetch all pages for an endpoint.
     * Returns [all_results, total_count].
     */
    private function fetchAllPages(string $endpoint, array $criteria): array
    {
        $all   = [];
        $total = 0;
        $page  = 1;

        while ($page <= self::MAX_PAGES) {
            $result  = $this->fetchEndpoint($endpoint, $criteria, $page);
            $results = $result['results'] ?? [];
            $all     = array_merge($all, $results);

            if ($page === 1) {
                $total = $result['count'] ?? 0;
            }

            // next URL uses http:// — just check if it exists
            if (empty($result['next'])) {
                break;
            }
            $page++;
        }

        return [$all, $total];
    }

    /**
     * Fetch a single page from an endpoint.
     */
    private function fetchEndpoint(string $endpoint, array $criteria, int $page = 1): array
    {
        $params = [
            'project' => $this->projectSlug,
            'page'    => $page,
        ];

        if (!empty($criteria['price_max'])) {
            $params['price_range_max'] = (int)$criteria['price_max'];
        }
        if (!empty($criteria['builder']) && strtolower($criteria['builder']) !== 'all') {
            $params['builder'] = $criteria['builder'];
        }

        $url = $this->baseUrl . '/' . $endpoint . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError)        throw new Exception("XO API connection error: $curlError");
        if ($httpCode !== 200) throw new Exception("XO API returned HTTP $httpCode for $endpoint");

        $data = json_decode($response, true);
        if (!is_array($data)) throw new Exception("XO API returned invalid JSON for $endpoint");

        return $data;
    }

    /**
     * Apply sort based on LLM-provided sort_by parameter.
     * Default: price_asc (fixes the "cheapest home" bug — no more hardcoded status-first).
     */
    private function applySort(array &$properties, string $sortBy): void
    {
        if ($sortBy === 'random') {
            shuffle($properties);
            return;
        }

        if ($sortBy === 'balanced') {
            // Split into listing type buckets, shuffle each for builder fairness
            $mir = [];  // Move In Ready
            $uc  = [];  // Under Construction
            $hs  = [];  // Home Sites

            foreach ($properties as $prop) {
                $type = $this->resolveListingType($prop);
                if ($type === 'move_in_ready')       $mir[] = $prop;
                elseif ($type === 'under_construction') $uc[] = $prop;
                else                                    $hs[] = $prop;
            }

            shuffle($mir);
            shuffle($uc);
            shuffle($hs);

            // Prioritize move-in ready first, then under construction, then homesites
            $properties = array_merge($mir, $uc, $hs);
            return;
        }

        usort($properties, function ($a, $b) use ($sortBy) {
            switch ($sortBy) {
                case 'price_desc':
                    return $this->extractPrice($b) - $this->extractPrice($a);

                case 'sqft_desc':
                    $aS = $this->extractNumber($a['sqft'] ?? $a['max_sqft'] ?? '0');
                    $bS = $this->extractNumber($b['sqft'] ?? $b['max_sqft'] ?? '0');
                    return $bS - $aS;

                case 'sqft_asc':
                    $aS = $this->extractNumber($a['sqft'] ?? $a['max_sqft'] ?? '0');
                    $bS = $this->extractNumber($b['sqft'] ?? $b['max_sqft'] ?? '0');
                    return $aS - $bS;

                case 'status_first':
                    $order = ['move in ready' => 0, 'construction' => 1, 'available' => 2];
                    $aO    = $order[strtolower($a['status'] ?? '')] ?? 3;
                    $bO    = $order[strtolower($b['status'] ?? '')] ?? 3;
                    if ($aO !== $bO) return $aO - $bO;
                    return $this->extractPrice($a) - $this->extractPrice($b);

                case 'price_asc':
                default:
                    return $this->extractPrice($a) - $this->extractPrice($b);
            }
        });
    }

    /**
     * Apply client-side filters the XO API doesn't support server-side.
     *
     * Listing type categories (Diana's spec):
     *   move_in_ready      — status "move in ready", OR "available" with available_on <= 30 days
     *   under_construction — status "construction", OR "available" with available_on > 30 days or missing
     *   homesite           — type "home site" (from /homesites endpoint)
     *
     * Lot type categories (consumer-friendly labels):
     *   townhome  — segmentation contains ZIPPER or REAR
     *   duplex    — segmentation contains DUPLEX
     *   oversized — segmentation contains ACRE, or lot size >= 80'
     *   standard  — everything else (40', 45', 50', 60' lots)
     */
    private function applyClientFilters(array $properties, array $criteria): array
    {
        return array_filter($properties, function ($prop) use ($criteria) {
            $isHomesite = ($prop['type'] ?? '') === 'home site'
                || (empty($prop['beds']) && empty($prop['baths']));

            // --- Listing Type (Diana's 3-tier categorization) ---
            if (!empty($criteria['listing_type'])) {
                $target = strtolower($criteria['listing_type']);

                if ($target === 'homesite') {
                    // Only homesites
                    if (!$isHomesite) return false;
                } else {
                    // Exclude homesites from home searches
                    if ($isHomesite) return false;

                    $resolvedType = $this->resolveListingType($prop);

                    if ($target === 'move_in_ready' && $resolvedType !== 'move_in_ready') return false;
                    if ($target === 'under_construction' && $resolvedType !== 'under_construction') return false;
                }
            }

            // Legacy: raw status filter (backward compat, prefer listing_type above)
            if (!empty($criteria['status']) && empty($criteria['listing_type'])) {
                $status = strtolower($prop['status'] ?? '');
                $target = strtolower($criteria['status']);
                if ($target === 'move in ready' || $target === 'move-in-ready') {
                    $resolved = $this->resolveListingType($prop);
                    if ($resolved !== 'move_in_ready') return false;
                } elseif ($target === 'construction') {
                    $resolved = $this->resolveListingType($prop);
                    if ($resolved !== 'under_construction') return false;
                } elseif ($status !== $target) {
                    return false;
                }
            }

            // --- Beds (applies to homes and homesites via max_beds) ---
            if (!empty($criteria['beds_min'])) {
                $beds = (int)($isHomesite ? ($prop['max_beds'] ?? 0) : ($prop['beds'] ?? $prop['max_beds'] ?? 0));
                if ($beds > 0 && $beds < (int)$criteria['beds_min']) return false;
            }

            // --- Homesite bypass: skip baths/stories/garage/sqft filters ---
            if ($isHomesite) {
                // Homesites only filter on price (server-side) and beds (above)
                // Fall through to lot type filter below
            } else {
                // Baths
                if (!empty($criteria['baths_min'])) {
                    $baths = (float)($prop['baths'] ?? 0);
                    if ($baths > 0 && $baths < (float)$criteria['baths_min']) return false;
                }

                // Stories (exact match)
                if (!empty($criteria['stories'])) {
                    $stories = (int)($prop['stories'] ?? 0);
                    if ($stories > 0 && $stories !== (int)$criteria['stories']) return false;
                }

                // Garage (greater-than-or-equal)
                if (!empty($criteria['garage_min'])) {
                    $garage = (int)($prop['garage'] ?? 0);
                    if ($garage > 0 && $garage < (int)$criteria['garage_min']) return false;
                }

                // Sqft range
                if (!empty($criteria['sqft_min'])) {
                    $sqft = $this->extractNumber($prop['sqft'] ?? $prop['max_sqft'] ?? '0');
                    if ($sqft > 0 && $sqft < (int)$criteria['sqft_min']) return false;
                }
                if (!empty($criteria['sqft_max'])) {
                    $sqft = $this->extractNumber($prop['sqft'] ?? $prop['max_sqft'] ?? '0');
                    if ($sqft > 0 && $sqft > (int)$criteria['sqft_max']) return false;
                }
            }

            // --- Lot Type (consumer-friendly categories) ---
            if (!empty($criteria['lot_type'])) {
                $resolvedLot = $this->resolveLotType($prop);
                if (strtolower($criteria['lot_type']) !== $resolvedLot) return false;
            }

            return true;
        });
    }

    /**
     * Resolve a property to Diana's listing type category.
     *
     * Move In Ready:       status "move in ready", OR "available" with available_on <= 30 days from today
     * Under Construction:  status "construction", OR "available" with available_on > 30 days OR no date
     */
    private function resolveListingType(array $prop): string
    {
        $isHomesite = ($prop['type'] ?? '') === 'home site';
        if ($isHomesite) return 'homesite';

        $status   = strtolower($prop['status'] ?? '');
        $availOn  = $prop['architectural_submission']['available_on'] ?? null;

        if ($status === 'move in ready') {
            return 'move_in_ready';
        }

        if ($status === 'construction') {
            return 'under_construction';
        }

        // "available" status: use available_on date to determine readiness
        if ($status === 'available') {
            if ($availOn && strtotime($availOn) <= strtotime('+30 days')) {
                return 'move_in_ready';
            }
            return 'under_construction';
        }

        return 'under_construction';
    }

    /**
     * Resolve a property to Diana's consumer-friendly lot type.
     *
     * Townhome:   segmentation contains ZIPPER or REAR
     * Duplex:     segmentation contains DUPLEX
     * Oversized:  segmentation contains ACRE, or lot size >= 80'
     * Standard:   everything else
     */
    private function resolveLotType(array $prop): string
    {
        $seg  = strtolower($prop['segmentation'] ?? '');
        $size = $prop['size'] ?? '';

        if (strpos($seg, 'zipper') !== false || strpos($seg, 'rear') !== false) {
            return 'townhome';
        }
        if (strpos($seg, 'duplex') !== false) {
            return 'duplex';
        }
        if (strpos($seg, 'acre') !== false) {
            return 'oversized';
        }
        // Check lot width >= 80'
        $lotWidth = (int)preg_replace('/[^0-9]/', '', $size);
        if ($lotWidth >= 80) {
            return 'oversized';
        }

        return 'standard';
    }

    /**
     * Format a property for the LLM and widget.
     *
     * Includes all media fields so:
     *   - The LLM can mention photos, floor plans, and virtual tours in its reply
     *   - The widget can render property cards with real images
     *
     * Placeholder images (paths containing "/placeholder") are filtered out.
     */
    private function formatForChat(array $prop): array
    {
        $isHomesite = ($prop['type'] ?? '') === 'home site'
            || (empty($prop['beds']) && empty($prop['baths']));

        // Extract floor plan and virtual tour from nested architectural_submission
        $arch          = $prop['architectural_submission'] ?? [];
        $masterPlan    = $arch['master_plan'] ?? [];
        $plan          = $masterPlan['plan'] ?? [];
        $fpImages      = $plan['floor_plan_images'] ?? [];
        $floorPlanUrl  = $fpImages[0]['source'] ?? null;
        $virtualTour   = $plan['virtual_tour_url'] ?? null;
        $planName      = $plan['name'] ?? null;

        // Filter out placeholder images — they add no visual value
        $gallery = array_values(array_filter(
            $prop['gallery'] ?? [],
            fn($url) => strpos($url, '/placeholder') === false
        ));

        $photo = $prop['listing_photo_url'] ?? null;
        if ($photo && strpos($photo, '/placeholder') !== false) {
            $photo = null;
        }

        // Homesite price fix: when listing_price is "-", use homesite_price
        $rawPrice = $prop['listing_price'] ?? null;
        if (!$rawPrice || $rawPrice === '-') {
            $rawPrice = $prop['homesite_price'] ?? 'Price TBD';
        }

        $formatted = [
            // Identity
            'title'         => $prop['title'] ?? 'Unnamed Property',
            'address'       => $prop['address'] ?? null,
            'type'          => $isHomesite ? 'homesite' : 'home',
            'status'        => $prop['status'] ?? 'available',
            'listing_type'  => $this->resolveListingType($prop),  // move_in_ready, under_construction, homesite
            'lot_type'      => $this->resolveLotType($prop),      // townhome, duplex, oversized, standard
            'segmentation'  => $prop['segmentation'] ?? null,   // "35' DUPLEX", "50' LOT", etc.
            'lot_size'      => $prop['size'] ?? null,            // lot width: "50'", "80'", etc.
            'property_type' => $prop['property_type'] ?? null,   // "Production Lot", "Paper Lot", etc.
            'high_profile'  => $prop['high_profile'] ?? false,

            // Pricing
            'price'         => $rawPrice,
            'homesite_price'=> $prop['homesite_price'] ?? null,  // lot-only price (separate from home+lot)

            // Builder
            'builder'         => $prop['builder']['name'] ?? 'Builder TBD',
            'builder_phone'   => $prop['builder']['phone'] ?? $prop['builder_phone'] ?? null,
            'builder_website' => $prop['builder_website'] ?? $prop['builder']['website'] ?? null,
            'builder_logo'    => $prop['builder']['logo'] ?? null,

            // Media — the LLM can reference these in its reply; widget renders them as cards
            'photo'           => $photo,
            'gallery'         => array_slice($gallery, 0, 3),
            'homesite_photo'  => $prop['homesite_photo'] ?? null,
            'floor_plan_url'  => $floorPlanUrl,
            'virtual_tour_url'=> $virtualTour,
            'plan_name'       => $planName,
        ];

        // Home vs homesite fields
        if ($isHomesite) {
            $formatted['max_beds']  = $prop['max_beds'] ?? null;
            $formatted['max_baths'] = $prop['max_baths'] ?? null;
        } else {
            $formatted['beds']    = $prop['beds'] ?? null;
            $formatted['baths']   = $prop['baths'] ?? null;
            $formatted['sqft']    = $prop['sqft'] ?? $prop['max_sqft'] ?? null;
            $formatted['stories'] = $prop['stories'] ?? null;
            $formatted['garage']  = $prop['garage'] ?? null;
        }

        // Move-in date
        if (!empty($arch['available_on'])) {
            $formatted['available_on'] = $arch['available_on'];
        }

        // Features (top 5)
        if (!empty($prop['features'])) {
            $formatted['features'] = array_slice($prop['features'], 0, 5);
        }

        return $formatted;
    }

    /**
     * Extract numeric price from a property.
     * Handles "$429,570" strings and "-" (no price) gracefully.
     */
    private function extractPrice(array $prop): int
    {
        $price = $prop['listing_price'] ?? $prop['homesite_price'] ?? '0';
        if ($price === '-' || $price === null) $price = '0';
        return (int)preg_replace('/[^0-9]/', '', (string)$price);
    }

    /**
     * Extract a number from a possibly formatted string like "2,450".
     */
    private function extractNumber(string $val): int
    {
        return (int)preg_replace('/[^0-9]/', '', $val);
    }
}
