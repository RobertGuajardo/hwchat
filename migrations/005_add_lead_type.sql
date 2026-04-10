-- Add lead_type column to leads table
ALTER TABLE leads ADD COLUMN lead_type VARCHAR(20) NOT NULL DEFAULT 'lead';

-- Existing leads are all type 'lead'
-- Future bookings will be saved as type 'booking'
