-- Migration 016: Extended widget color system
-- Adds per-tenant color controls for each widget UI element

ALTER TABLE tenants
  ADD COLUMN IF NOT EXISTS color_header_bg       text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS color_header_text     text DEFAULT '#ffffff',
  ADD COLUMN IF NOT EXISTS color_secondary       text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS color_quick_btn_bg    text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS color_quick_btn_text  text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS color_user_bubble     text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS color_ai_bubble_border text DEFAULT NULL;

-- Backfill from existing accent colors
UPDATE tenants SET
  color_header_bg        = accent_gradient,
  color_header_text      = '#ffffff',
  color_secondary        = ai_accent,
  color_quick_btn_bg     = 'transparent',
  color_quick_btn_text   = accent_color,
  color_user_bubble      = accent_gradient,
  color_ai_bubble_border = accent_color;

COMMENT ON COLUMN tenants.color_header_bg        IS 'Header background — hex or gradient';
COMMENT ON COLUMN tenants.color_header_text       IS 'Header text and icon color';
COMMENT ON COLUMN tenants.color_secondary         IS 'Secondary brand color — quick reply buttons, card links';
COMMENT ON COLUMN tenants.color_quick_btn_bg      IS 'Quick reply button background';
COMMENT ON COLUMN tenants.color_quick_btn_text    IS 'Quick reply button text color';
COMMENT ON COLUMN tenants.color_user_bubble       IS 'User message bubble background';
COMMENT ON COLUMN tenants.color_ai_bubble_border  IS 'AI message bubble left accent border';
