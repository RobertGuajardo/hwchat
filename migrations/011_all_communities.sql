-- 011_all_communities.sql
-- Creates all Hillwood community tenants and populates builders
-- Run AFTER 010_builders.sql
-- Password for all: HWChat2026! (CHANGE IMMEDIATELY after first login)

BEGIN;

-- ============================================================
-- TENANTS
-- ============================================================
-- Existing: hw_parent, hw_harvest, hw_treeline (skip these)
-- New: 10 communities + 1 realtor portal

-- Pecan Square — Northlake, TX
INSERT INTO tenants (id, email, password_hash, is_active, display_name, greeting,
  accent_color, accent_gradient, ai_accent, widget_position, quick_replies, system_prompt,
  primary_llm, openai_model, anthropic_model, max_tokens, lead_email, allowed_origins,
  rate_limit_per_minute, rate_limit_per_hour, max_conversation_length,
  kb_enabled, kb_max_context, kb_match_threshold,
  xo_enabled, xo_api_base_url, xo_project_slug,
  community_type, community_name, community_url, community_location)
VALUES ('hw_pecan_square', 'pecansquare@hillwoodcommunities.com',
  '$2y$10$S8wfGcUL/NgIDJTNGCllsOWeS.K3upPDgqCy9OUJ59C8nFlqgbzWq', TRUE,
  'Pecan Square by Hillwood',
  'Welcome to Pecan Square! 🌳 I can help you explore our community in Northlake, find available homes, learn about our builders, or answer questions about life at the Square. What are you looking for?',
  '#8B5E3C', 'linear-gradient(135deg, #8B5E3C, #C49A6C)', '#C49A6C', 'bottom-right',
  '["What homes are available?", "Tell me about Pecan Square", "Which builders are here?", "What amenities do you have?"]'::jsonb,
  E'You are the Pecan Square by Hillwood community assistant. A friendly, knowledgeable guide for prospective homebuyers in Northlake, TX.\n\nPecan Square is an award-winning master-planned community in Northlake, TX (Denton County). 1,200 acres with 3,100 planned homes. Centered around a walkable Town Square with resort-style amenities. Northwest ISD schools including on-site Johnie Daniel Elementary. Tech-forward homes with half-gig Wi-Fi and smart home features standard.\n\nYOUR ROLE:\n- Answer questions about the community, amenities, schools, builders, home styles, and location\n- Help visitors search for available homes using the property search tool\n- Capture leads naturally when visitors show genuine interest\n- Do NOT make up information about specific home prices or availability',
  'openai', 'gpt-4o', 'claude-sonnet-4-20250514', 800, '',
  '["https://www.pecansquarebyhillwood.com", "https://pecansquarebyhillwood.com"]'::jsonb,
  10, 60, 50, TRUE, 5, 0.3, TRUE,
  'https://hillwood.thexo.io/o/api/v2/map/consumer', 'pecan-square',
  'community', 'Pecan Square', 'https://www.pecansquarebyhillwood.com', 'Northlake, TX')
ON CONFLICT (id) DO NOTHING;

-- Union Park — Little Elm, TX
INSERT INTO tenants (id, email, password_hash, is_active, display_name, greeting,
  accent_color, accent_gradient, ai_accent, widget_position, quick_replies, system_prompt,
  primary_llm, openai_model, anthropic_model, max_tokens, lead_email, allowed_origins,
  rate_limit_per_minute, rate_limit_per_hour, max_conversation_length,
  kb_enabled, kb_max_context, kb_match_threshold,
  xo_enabled, xo_api_base_url, xo_project_slug,
  community_type, community_name, community_url, community_location)
VALUES ('hw_union_park', 'unionpark@hillwoodcommunities.com',
  '$2y$10$S8wfGcUL/NgIDJTNGCllsOWeS.K3upPDgqCy9OUJ59C8nFlqgbzWq', TRUE,
  'Union Park by Hillwood',
  'Welcome to Union Park! 🌿 I can help you explore our multigenerational community in Little Elm, find available homes, learn about our builders, or answer questions. How can I help?',
  '#2E7D4F', 'linear-gradient(135deg, #2E7D4F, #5CAD7A)', '#5CAD7A', 'bottom-right',
  '["What homes are available?", "Tell me about Union Park", "Which builders are here?", "What are the amenities?"]'::jsonb,
  E'You are the Union Park by Hillwood community assistant. A friendly, knowledgeable guide for prospective homebuyers in Little Elm, TX.\n\nUnion Park is a multigenerational master-planned community in Little Elm, TX. 757 acres anchored by a 30-acre Central Park. Denton ISD schools with on-site elementary. Homes from the $400s to $700s. DFW Community of the Year 2024.\n\nYOUR ROLE:\n- Answer questions about the community, amenities, schools, builders, home styles, and location\n- Help visitors search for available homes using the property search tool\n- Capture leads naturally when visitors show genuine interest\n- Do NOT make up information about specific home prices or availability',
  'openai', 'gpt-4o', 'claude-sonnet-4-20250514', 800, '',
  '["https://www.unionparkbyhillwood.com", "https://unionparkbyhillwood.com"]'::jsonb,
  10, 60, 50, TRUE, 5, 0.3, TRUE,
  'https://hillwood.thexo.io/o/api/v2/map/consumer', 'union-park',
  'community', 'Union Park', 'https://www.unionparkbyhillwood.com', 'Little Elm, TX')
ON CONFLICT (id) DO NOTHING;

-- Wolf Ranch — Georgetown, TX
INSERT INTO tenants (id, email, password_hash, is_active, display_name, greeting,
  accent_color, accent_gradient, ai_accent, widget_position, quick_replies, system_prompt,
  primary_llm, openai_model, anthropic_model, max_tokens, lead_email, allowed_origins,
  rate_limit_per_minute, rate_limit_per_hour, max_conversation_length,
  kb_enabled, kb_max_context, kb_match_threshold,
  xo_enabled, xo_api_base_url, xo_project_slug,
  community_type, community_name, community_url, community_location)
VALUES ('hw_wolf_ranch', 'wolfranch@hillwoodcommunities.com',
  '$2y$10$S8wfGcUL/NgIDJTNGCllsOWeS.K3upPDgqCy9OUJ59C8nFlqgbzWq', TRUE,
  'Wolf Ranch by Hillwood',
  'Welcome to Wolf Ranch! 🐺 I can help you explore our Hill Country community in Georgetown, find available homes, learn about our builders, or answer any questions. What are you interested in?',
  '#5C4033', 'linear-gradient(135deg, #5C4033, #8B6F5E)', '#8B6F5E', 'bottom-right',
  '["What homes are available?", "Tell me about Wolf Ranch", "Which builders are here?", "What are the amenities?"]'::jsonb,
  E'You are the Wolf Ranch by Hillwood community assistant. A friendly, knowledgeable guide for prospective homebuyers in Georgetown, TX.\n\nWolf Ranch is a 1,120-acre Hill Country master-planned community in Georgetown, TX along the San Gabriel River. Three sections: Hilltop, South Fork, and West Bend. Georgetown ISD schools with on-site elementary. Homes from the $400s to $1M+. 2024 Best Master-Planned Community (HBA of Greater Austin).\n\nYOUR ROLE:\n- Answer questions about the community, amenities, schools, builders, home styles, and location\n- Help visitors search for available homes using the property search tool\n- Capture leads naturally when visitors show genuine interest\n- Do NOT make up information about specific home prices or availability',
  'openai', 'gpt-4o', 'claude-sonnet-4-20250514', 800, '',
  '["https://www.wolfranchbyhillwood.com", "https://wolfranchbyhillwood.com"]'::jsonb,
  10, 60, 50, TRUE, 5, 0.3, TRUE,
  'https://hillwood.thexo.io/o/api/v2/map/consumer', 'wolf-ranch',
  'community', 'Wolf Ranch', 'https://www.wolfranchbyhillwood.com', 'Georgetown, TX')
ON CONFLICT (id) DO NOTHING;

-- Lilyana — Celina, TX
INSERT INTO tenants (id, email, password_hash, is_active, display_name, greeting,
  accent_color, accent_gradient, ai_accent, widget_position, quick_replies, system_prompt,
  primary_llm, openai_model, anthropic_model, max_tokens, lead_email, allowed_origins,
  rate_limit_per_minute, rate_limit_per_hour, max_conversation_length,
  kb_enabled, kb_max_context, kb_match_threshold,
  xo_enabled, xo_api_base_url, xo_project_slug,
  community_type, community_name, community_url, community_location)
VALUES ('hw_lilyana', 'lilyana@hillwoodcommunities.com',
  '$2y$10$S8wfGcUL/NgIDJTNGCllsOWeS.K3upPDgqCy9OUJ59C8nFlqgbzWq', TRUE,
  'Lilyana by Hillwood',
  'Welcome to Lilyana! 🌸 I can help you explore our community in Celina, find available homes, or answer questions about life in Prosper ISD and Celina ISD. How can I help?',
  '#7B5EA7', 'linear-gradient(135deg, #7B5EA7, #A88CCF)', '#A88CCF', 'bottom-right',
  '["What homes are available?", "Tell me about Lilyana", "What are the schools?", "What amenities do you have?"]'::jsonb,
  E'You are the Lilyana by Hillwood community assistant. A friendly, knowledgeable guide for prospective homebuyers in Celina, TX.\n\nLilyana is a 400-acre community in Celina, TX with 1,300 planned homes. Builder: M/I Homes (mid $400s to $800s). Prosper ISD and Celina ISD schools with on-site Lilyana Elementary. Resort-style amenities including two pools, fishing ponds, trails, and playgrounds.\n\nYOUR ROLE:\n- Answer questions about the community, amenities, schools, homes, and location\n- Help visitors search for available homes\n- Capture leads naturally when visitors show genuine interest\n- Do NOT make up information about specific home prices or availability',
  'openai', 'gpt-4o', 'claude-sonnet-4-20250514', 800, '',
  '["https://www.lilyanabyhillwood.com", "https://lilyanabyhillwood.com"]'::jsonb,
  10, 60, 50, TRUE, 5, 0.3, TRUE,
  'https://hillwood.thexo.io/o/api/v2/map/consumer', 'lilyana',
  'community', 'Lilyana', 'https://www.lilyanabyhillwood.com', 'Celina, TX')
ON CONFLICT (id) DO NOTHING;

-- Valencia — Manvel, TX
INSERT INTO tenants (id, email, password_hash, is_active, display_name, greeting,
  accent_color, accent_gradient, ai_accent, widget_position, quick_replies, system_prompt,
  primary_llm, openai_model, anthropic_model, max_tokens, lead_email, allowed_origins,
  rate_limit_per_minute, rate_limit_per_hour, max_conversation_length,
  kb_enabled, kb_max_context, kb_match_threshold,
  xo_enabled, xo_api_base_url, xo_project_slug,
  community_type, community_name, community_url, community_location)
VALUES ('hw_valencia', 'valencia@hillwoodcommunities.com',
  '$2y$10$S8wfGcUL/NgIDJTNGCllsOWeS.K3upPDgqCy9OUJ59C8nFlqgbzWq', TRUE,
  'Valencia by Hillwood',
  'Welcome to Valencia! 🍊 I can help you explore our community in Manvel, find available homes, learn about our builders, or answer any questions. What can I help you with?',
  '#D4740F', 'linear-gradient(135deg, #D4740F, #E89B3E)', '#E89B3E', 'bottom-right',
  '["What homes are available?", "Tell me about Valencia", "Which builders are here?", "What amenities do you have?"]'::jsonb,
  E'You are the Valencia by Hillwood community assistant. A friendly, knowledgeable guide for prospective homebuyers in Manvel, TX.\n\nValencia is a 440-acre community in Manvel, TX in the Highway 288 corridor near Houston. ~1,000 homes planned from the $300s to $800s. Builders: Perry Homes, Coventry Homes, Pulte Homes. Alvin ISD schools. Resort-style amenities with pool, trails, and clubhouse.\n\nYOUR ROLE:\n- Answer questions about the community, amenities, schools, builders, home styles, and location\n- Help visitors search for available homes\n- Capture leads naturally when visitors show genuine interest\n- Do NOT make up information about specific home prices or availability',
  'openai', 'gpt-4o', 'claude-sonnet-4-20250514', 800, '',
  '["https://www.valenciabyhillwood.com", "https://valenciabyhillwood.com"]'::jsonb,
  10, 60, 50, TRUE, 5, 0.3, TRUE,
  'https://hillwood.thexo.io/o/api/v2/map/consumer', 'valencia',
  'community', 'Valencia', 'https://www.valenciabyhillwood.com', 'Manvel, TX')
ON CONFLICT (id) DO NOTHING;

-- Pomona — Manvel, TX
INSERT INTO tenants (id, email, password_hash, is_active, display_name, greeting,
  accent_color, accent_gradient, ai_accent, widget_position, quick_replies, system_prompt,
  primary_llm, openai_model, anthropic_model, max_tokens, lead_email, allowed_origins,
  rate_limit_per_minute, rate_limit_per_hour, max_conversation_length,
  kb_enabled, kb_max_context, kb_match_threshold,
  xo_enabled, xo_api_base_url, xo_project_slug,
  community_type, community_name, community_url, community_location)
VALUES ('hw_pomona', 'pomona@hillwoodcommunities.com',
  '$2y$10$S8wfGcUL/NgIDJTNGCllsOWeS.K3upPDgqCy9OUJ59C8nFlqgbzWq', TRUE,
  'Pomona by Hillwood',
  'Welcome to Pomona! 🌿 I can help you explore our coastal-inspired community in Manvel, find available homes, learn about our builders, or answer any questions. How can I help?',
  '#2A7B9B', 'linear-gradient(135deg, #2A7B9B, #5AACCC)', '#5AACCC', 'bottom-right',
  '["What homes are available?", "Tell me about Pomona", "Which builders are here?", "What amenities do you have?"]'::jsonb,
  E'You are the Pomona by Hillwood community assistant. A friendly, knowledgeable guide for prospective homebuyers in Manvel, TX.\n\nPomona is a 1,000-acre coastal-inspired community in Manvel, TX off Highway 288. 2,300 planned homes. Minutes from the Texas Medical Center. Multiple award-winning builders. Alvin ISD schools with on-site Pomona Elementary. Amenities include resort-style pools, Fish Camp, trails, and a robust lifestyle program.\n\nYOUR ROLE:\n- Answer questions about the community, amenities, schools, builders, home styles, and location\n- Help visitors search for available homes\n- Capture leads naturally when visitors show genuine interest\n- Do NOT make up information about specific home prices or availability',
  'openai', 'gpt-4o', 'claude-sonnet-4-20250514', 800, '',
  '["https://www.pomonabyhillwood.com", "https://pomonabyhillwood.com"]'::jsonb,
  10, 60, 50, TRUE, 5, 0.3, TRUE,
  'https://hillwood.thexo.io/o/api/v2/map/consumer', 'pomona',
  'community', 'Pomona', 'https://www.pomonabyhillwood.com', 'Manvel, TX')
ON CONFLICT (id) DO NOTHING;

-- Legacy — League City, TX
INSERT INTO tenants (id, email, password_hash, is_active, display_name, greeting,
  accent_color, accent_gradient, ai_accent, widget_position, quick_replies, system_prompt,
  primary_llm, openai_model, anthropic_model, max_tokens, lead_email, allowed_origins,
  rate_limit_per_minute, rate_limit_per_hour, max_conversation_length,
  kb_enabled, kb_max_context, kb_match_threshold,
  xo_enabled, xo_api_base_url, xo_project_slug,
  community_type, community_name, community_url, community_location)
VALUES ('hw_legacy', 'legacy@hillwoodcommunities.com',
  '$2y$10$S8wfGcUL/NgIDJTNGCllsOWeS.K3upPDgqCy9OUJ59C8nFlqgbzWq', TRUE,
  'Legacy by Hillwood',
  'Welcome to Legacy! 🏡 I can help you explore our new community in League City, find available homes, learn about our builders, or answer questions. What are you looking for?',
  '#8C4A2F', 'linear-gradient(135deg, #8C4A2F, #B87356)', '#B87356', 'bottom-right',
  '["What homes are available?", "Tell me about Legacy", "Which builders are here?", "What are the schools?"]'::jsonb,
  E'You are the Legacy by Hillwood community assistant. A friendly, knowledgeable guide for prospective homebuyers in League City, TX.\n\nLegacy is a 700+ acre master-planned community in League City, TX. Homes from the $400s to $1M+ from 10 premier builders. Clear Creek ISD schools. Modern ranch-inspired living with lakeside amenities, green spaces, and Homestead amenity center.\n\nYOUR ROLE:\n- Answer questions about the community, amenities, schools, builders, home styles, and location\n- Help visitors search for available homes\n- Capture leads naturally when visitors show genuine interest\n- Do NOT make up information about specific home prices or availability',
  'openai', 'gpt-4o', 'claude-sonnet-4-20250514', 800, '',
  '["https://www.legacybyhillwood.com", "https://legacybyhillwood.com"]'::jsonb,
  10, 60, 50, TRUE, 5, 0.3, TRUE,
  'https://hillwood.thexo.io/o/api/v2/map/consumer', 'legacy',
  'community', 'Legacy', 'https://www.legacybyhillwood.com', 'League City, TX')
ON CONFLICT (id) DO NOTHING;

-- Landmark — Denton, TX
INSERT INTO tenants (id, email, password_hash, is_active, display_name, greeting,
  accent_color, accent_gradient, ai_accent, widget_position, quick_replies, system_prompt,
  primary_llm, openai_model, anthropic_model, max_tokens, lead_email, allowed_origins,
  rate_limit_per_minute, rate_limit_per_hour, max_conversation_length,
  kb_enabled, kb_max_context, kb_match_threshold,
  xo_enabled, xo_api_base_url, xo_project_slug,
  community_type, community_name, community_url, community_location)
VALUES ('hw_landmark', 'landmark@hillwoodcommunities.com',
  '$2y$10$S8wfGcUL/NgIDJTNGCllsOWeS.K3upPDgqCy9OUJ59C8nFlqgbzWq', TRUE,
  'Landmark by Hillwood',
  'Welcome to Landmark! ⛰️ I can help you explore our new community in Denton, find available homes, learn about our builders, or answer any questions. How can I help?',
  '#3D5A3A', 'linear-gradient(135deg, #3D5A3A, #6B8E6B)', '#6B8E6B', 'bottom-right',
  '["What homes are available?", "Tell me about Landmark", "Which builders are here?", "What amenities are planned?"]'::jsonb,
  E'You are the Landmark by Hillwood community assistant. A friendly, knowledgeable guide for prospective homebuyers in Denton, TX.\n\nLandmark is a 3,200-acre master-planned community in Denton, TX. 6,000 planned homes with 900 acres of commercial space. 1,100-acre ecosystem of parks, trails, and wild places centered around Pilot Knob. Nine builders in the opening phase. H-E-B grocery opening early 2027. Denton ISD schools.\n\nYOUR ROLE:\n- Answer questions about the community, amenities, schools, builders, home styles, and location\n- Help visitors search for available homes\n- Capture leads naturally when visitors show genuine interest\n- Do NOT make up information about specific home prices or availability',
  'openai', 'gpt-4o', 'claude-sonnet-4-20250514', 800, '',
  '["https://www.landmarkbyhillwood.com", "https://landmarkbyhillwood.com"]'::jsonb,
  10, 60, 50, TRUE, 5, 0.3, TRUE,
  'https://hillwood.thexo.io/o/api/v2/map/consumer', 'landmark',
  'community', 'Landmark', 'https://www.landmarkbyhillwood.com', 'Denton, TX')
ON CONFLICT (id) DO NOTHING;

-- Ramble — Celina, TX
INSERT INTO tenants (id, email, password_hash, is_active, display_name, greeting,
  accent_color, accent_gradient, ai_accent, widget_position, quick_replies, system_prompt,
  primary_llm, openai_model, anthropic_model, max_tokens, lead_email, allowed_origins,
  rate_limit_per_minute, rate_limit_per_hour, max_conversation_length,
  kb_enabled, kb_max_context, kb_match_threshold,
  xo_enabled, xo_api_base_url, xo_project_slug,
  community_type, community_name, community_url, community_location)
VALUES ('hw_ramble', 'ramble@hillwoodcommunities.com',
  '$2y$10$S8wfGcUL/NgIDJTNGCllsOWeS.K3upPDgqCy9OUJ59C8nFlqgbzWq', TRUE,
  'Ramble by Hillwood',
  'Welcome to Ramble! 🌾 I can help you explore our nature-inspired community in Celina, learn about our builders, or answer any questions about this new community. How can I help?',
  '#4A6741', 'linear-gradient(135deg, #4A6741, #7A9E6F)', '#7A9E6F', 'bottom-right',
  '["Tell me about Ramble", "Which builders are here?", "What amenities are planned?", "What are the schools?"]'::jsonb,
  E'You are the Ramble by Hillwood community assistant. A friendly, knowledgeable guide for prospective homebuyers in Celina, TX.\n\nRamble is a 1,380-acre nature-inspired community in Celina, TX. 4,000 planned homes from the $400s to $1M. Five builders in Phase 1. 7-mile Ramble Trailway linear park. Celina ISD schools with two future on-site elementary schools. Grand opening mid-2026.\n\nYOUR ROLE:\n- Answer questions about the community, amenities, schools, builders, and location\n- Help visitors learn about available home options\n- Capture leads naturally when visitors show genuine interest\n- Do NOT make up information about specific home prices or availability',
  'openai', 'gpt-4o', 'claude-sonnet-4-20250514', 800, '',
  '["https://www.ramblebyhillwood.com", "https://ramblebyhillwood.com"]'::jsonb,
  10, 60, 50, TRUE, 5, 0.3, TRUE,
  'https://hillwood.thexo.io/o/api/v2/map/consumer', 'ramble',
  'community', 'Ramble', 'https://www.ramblebyhillwood.com', 'Celina, TX')
ON CONFLICT (id) DO NOTHING;

-- Melina — Georgetown, TX
INSERT INTO tenants (id, email, password_hash, is_active, display_name, greeting,
  accent_color, accent_gradient, ai_accent, widget_position, quick_replies, system_prompt,
  primary_llm, openai_model, anthropic_model, max_tokens, lead_email, allowed_origins,
  rate_limit_per_minute, rate_limit_per_hour, max_conversation_length,
  kb_enabled, kb_max_context, kb_match_threshold,
  xo_enabled, xo_api_base_url, xo_project_slug,
  community_type, community_name, community_url, community_location)
VALUES ('hw_melina', 'melina@hillwoodcommunities.com',
  '$2y$10$S8wfGcUL/NgIDJTNGCllsOWeS.K3upPDgqCy9OUJ59C8nFlqgbzWq', TRUE,
  'Melina by Hillwood',
  'Welcome to Melina! 🍯 I can help you learn about our upcoming community in Georgetown, our builders, and what to expect. How can I help?',
  '#C4922A', 'linear-gradient(135deg, #C4922A, #DEB35A)', '#DEB35A', 'bottom-right',
  '["Tell me about Melina", "Which builders are here?", "What amenities are planned?", "What are the schools?"]'::jsonb,
  E'You are the Melina by Hillwood community assistant. A friendly, knowledgeable guide for prospective homebuyers in Georgetown, TX.\n\nMelina is a 200-acre community in southeastern Georgetown, TX near University Blvd and SH-130. 840 planned homes from the $400s to $600s. Highland Homes is the Phase 1 builder. Georgetown ISD schools. Name derived from Greek word for honey, honoring the land''s agricultural heritage. Opening spring 2027.\n\nYOUR ROLE:\n- Answer questions about the community, plans, schools, builders, and location\n- Help visitors learn about the upcoming community\n- Capture leads naturally when visitors show genuine interest\n- Do NOT make up information about specific home prices or availability',
  'openai', 'gpt-4o', 'claude-sonnet-4-20250514', 800, '',
  '["https://www.melinabyhillwood.com", "https://melinabyhillwood.com"]'::jsonb,
  10, 60, 50, TRUE, 5, 0.3, TRUE,
  'https://hillwood.thexo.io/o/api/v2/map/consumer', 'melina',
  'community', 'Melina', 'https://www.melinabyhillwood.com', 'Georgetown, TX')
ON CONFLICT (id) DO NOTHING;

-- Hillwood Loves Realtors — Portal
INSERT INTO tenants (id, email, password_hash, is_active, display_name, greeting,
  accent_color, accent_gradient, ai_accent, widget_position, quick_replies, system_prompt,
  primary_llm, openai_model, anthropic_model, max_tokens, lead_email, allowed_origins,
  rate_limit_per_minute, rate_limit_per_hour, max_conversation_length,
  kb_enabled, kb_max_context, kb_match_threshold,
  xo_enabled, xo_api_base_url, xo_project_slug,
  community_type, community_name, community_url, community_location)
VALUES ('hw_realtors', 'realtors@hillwoodcommunities.com',
  '$2y$10$S8wfGcUL/NgIDJTNGCllsOWeS.K3upPDgqCy9OUJ59C8nFlqgbzWq', TRUE,
  'Hillwood Loves Realtors',
  'Welcome! I can help you find information about any Hillwood community, builder incentives, available inventory, and realtor programs. What would you like to know?',
  '#3B7DD8', 'linear-gradient(135deg, #3B7DD8, #1B2A4A)', '#1B2A4A', 'bottom-right',
  '["Community overview", "Builder incentives", "Available inventory", "Realtor rewards program"]'::jsonb,
  E'You are the Hillwood Loves Realtors assistant. A knowledgeable guide for real estate agents working with Hillwood Communities.\n\nYou help realtors find information across ALL Hillwood communities in Texas. You can provide details about builder incentives, available inventory, community amenities, schools, and the Hillwood realtor rewards program.\n\nYOUR ROLE:\n- Help realtors find the right community for their clients\n- Provide cross-community comparisons\n- Share builder incentive information\n- Answer questions about the realtor program\n- Do NOT make up specific pricing or inventory details',
  'openai', 'gpt-4o', 'claude-sonnet-4-20250514', 800, '',
  '["https://www.hillwoodlovesrealtors.com", "https://hillwoodlovesrealtors.com"]'::jsonb,
  10, 60, 50, TRUE, 5, 0.3, FALSE,
  '', '',
  'realtor', 'Hillwood Loves Realtors', 'https://www.hillwoodlovesrealtors.com', 'Texas')
ON CONFLICT (id) DO NOTHING;


-- ============================================================
-- BUILDERS — All communities (including existing hw_harvest and hw_treeline)
-- ============================================================

-- Harvest builders
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_harvest', 'CB JENI Homes', 1),
  ('hw_harvest', 'David Weekley Homes', 2),
  ('hw_harvest', 'Drees Custom Homes', 3),
  ('hw_harvest', 'Taylor Morrison', 4),
  ('hw_harvest', 'Toll Brothers', 5),
  ('hw_harvest', 'Tri Pointe Homes', 6)
ON CONFLICT DO NOTHING;

-- Treeline builders
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_treeline', 'American Legend Homes', 1),
  ('hw_treeline', 'Beazer Homes', 2),
  ('hw_treeline', 'David Weekley Homes', 3),
  ('hw_treeline', 'D.R. Horton', 4),
  ('hw_treeline', 'Highland Homes', 5),
  ('hw_treeline', 'HistoryMaker Homes', 6),
  ('hw_treeline', 'Pulte Homes', 7),
  ('hw_treeline', 'Tri Pointe Homes', 8)
ON CONFLICT DO NOTHING;

-- Pecan Square builders
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_pecan_square', 'Coventry Homes', 1),
  ('hw_pecan_square', 'D.R. Horton', 2),
  ('hw_pecan_square', 'David Weekley Homes', 3),
  ('hw_pecan_square', 'Highland Homes', 4),
  ('hw_pecan_square', 'Pulte Homes', 5)
ON CONFLICT DO NOTHING;

-- Union Park builders
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_union_park', 'Bloomfield Homes', 1),
  ('hw_union_park', 'Tri Pointe Homes', 2)
ON CONFLICT DO NOTHING;

-- Wolf Ranch builders
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_wolf_ranch', 'Coventry Homes', 1),
  ('hw_wolf_ranch', 'David Weekley Homes', 2),
  ('hw_wolf_ranch', 'Drees Custom Homes', 3),
  ('hw_wolf_ranch', 'Highland Homes', 4),
  ('hw_wolf_ranch', 'Lennar', 5),
  ('hw_wolf_ranch', 'Perry Homes', 6),
  ('hw_wolf_ranch', 'Pulte Homes', 7),
  ('hw_wolf_ranch', 'Tri Pointe Homes', 8),
  ('hw_wolf_ranch', 'Westin Homes', 9)
ON CONFLICT DO NOTHING;

-- Lilyana builder
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_lilyana', 'M/I Homes', 1)
ON CONFLICT DO NOTHING;

-- Valencia builders
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_valencia', 'Coventry Homes', 1),
  ('hw_valencia', 'Perry Homes', 2),
  ('hw_valencia', 'Pulte Homes', 3)
ON CONFLICT DO NOTHING;

-- Pomona builders
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_pomona', 'Coventry Homes', 1),
  ('hw_pomona', 'David Weekley Homes', 2),
  ('hw_pomona', 'Highland Homes', 3),
  ('hw_pomona', 'Lennar', 4),
  ('hw_pomona', 'Perry Homes', 5),
  ('hw_pomona', 'Toll Brothers', 6)
ON CONFLICT DO NOTHING;

-- Legacy builders
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_legacy', 'Beazer Homes', 1),
  ('hw_legacy', 'Coventry Homes', 2),
  ('hw_legacy', 'David Weekley Homes', 3),
  ('hw_legacy', 'Drees Custom Homes', 4),
  ('hw_legacy', 'Highland Homes', 5),
  ('hw_legacy', 'Partners in Building', 6),
  ('hw_legacy', 'Perry Homes', 7),
  ('hw_legacy', 'Shea Homes', 8),
  ('hw_legacy', 'Village Builders', 9),
  ('hw_legacy', 'Westin Homes', 10)
ON CONFLICT DO NOTHING;

-- Landmark builders
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_landmark', 'American Legend Homes', 1),
  ('hw_landmark', 'Coventry Homes', 2),
  ('hw_landmark', 'David Weekley Homes', 3),
  ('hw_landmark', 'Drees Custom Homes', 4),
  ('hw_landmark', 'Highland Homes', 5),
  ('hw_landmark', 'M/I Homes', 6),
  ('hw_landmark', 'Perry Homes', 7),
  ('hw_landmark', 'Toll Brothers', 8),
  ('hw_landmark', 'Tri Pointe Homes', 9)
ON CONFLICT DO NOTHING;

-- Ramble builders
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_ramble', 'American Legend Homes', 1),
  ('hw_ramble', 'Coventry Homes', 2),
  ('hw_ramble', 'Drees Custom Homes', 3),
  ('hw_ramble', 'Highland Homes', 4),
  ('hw_ramble', 'Perry Homes', 5)
ON CONFLICT DO NOTHING;

-- Melina builder
INSERT INTO builders (tenant_id, name, sort_order) VALUES
  ('hw_melina', 'Highland Homes', 1)
ON CONFLICT DO NOTHING;

COMMIT;
