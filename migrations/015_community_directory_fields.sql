-- Migration 015: Community Directory Fields
-- Run with: psql -U hwchat -d hwchat -f migrations/015_community_directory_fields.sql
-- Then restart PHP: systemctl restart ea-php82-php-fpm ea-php83-php-fpm

-- Add structured fields for the community directory
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS school_district TEXT,
    ADD COLUMN IF NOT EXISTS community_tagline TEXT;

-- Backfill from the existing master prompt directory data
UPDATE tenants SET school_district = 'Argyle ISD', community_tagline = 'Award-winning agrihood with working farm' WHERE id = 'hw_harvest';
UPDATE tenants SET school_district = 'Northwest ISD', community_tagline = 'Nature-inspired living' WHERE id = 'hw_treeline';
UPDATE tenants SET school_district = 'Northwest ISD', community_tagline = 'Farm-to-table community' WHERE id = 'hw_pecan_square';
UPDATE tenants SET school_district = 'Denton ISD', community_tagline = 'Multigenerational resort-style living' WHERE id = 'hw_union_park';
UPDATE tenants SET school_district = 'Georgetown ISD', community_tagline = 'Hill Country living near Austin' WHERE id = 'hw_wolf_ranch';
UPDATE tenants SET school_district = 'Prosper ISD & Celina ISD', community_tagline = 'Boutique community' WHERE id = 'hw_lilyana';
UPDATE tenants SET school_district = 'Alvin ISD', community_tagline = 'Houston-area community off Hwy 288' WHERE id = 'hw_valencia';
UPDATE tenants SET school_district = 'Alvin ISD', community_tagline = 'Coastal-inspired near Texas Medical Center' WHERE id = 'hw_pomona';
UPDATE tenants SET school_district = 'Clear Creek ISD', community_tagline = 'Modern ranch-inspired lakeside living' WHERE id = 'hw_legacy';
UPDATE tenants SET school_district = 'Denton ISD', community_tagline = '3,200-acre community centered around Pilot Knob' WHERE id = 'hw_landmark';
UPDATE tenants SET school_district = 'Celina ISD', community_tagline = 'Nature-inspired with 7-mile Trailway' WHERE id = 'hw_ramble';
UPDATE tenants SET school_district = 'Georgetown ISD', community_tagline = 'Honey-inspired community near SH-130' WHERE id = 'hw_melina';

-- Store cross-referral groupings in global_settings as JSON
INSERT INTO global_settings (key, value) VALUES ('cross_referral_groups', '{
  "by_location": {
    "Near Fort Worth / Denton": ["hw_harvest", "hw_treeline", "hw_pecan_square", "hw_landmark"],
    "Near Dallas / Frisco / Prosper": ["hw_union_park", "hw_lilyana", "hw_ramble"],
    "Near Austin": ["hw_wolf_ranch", "hw_melina"],
    "Near Houston": ["hw_pomona", "hw_valencia", "hw_legacy"]
  },
  "by_lifestyle": {
    "Agrihood / farm lifestyle": ["hw_harvest", "hw_pecan_square"],
    "Nature / trails / outdoors": ["hw_treeline", "hw_ramble", "hw_landmark"],
    "Resort-style amenities": ["hw_union_park", "hw_pomona"],
    "Hill Country living": ["hw_wolf_ranch", "hw_melina"]
  }
}')
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW();
