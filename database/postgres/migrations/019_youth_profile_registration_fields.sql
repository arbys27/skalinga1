-- Structured name and address fields collected during youth account activation.

ALTER TABLE public.youth_profiles
    ADD COLUMN IF NOT EXISTS middle_name varchar(100),
    ADD COLUMN IF NOT EXISTS house_number varchar(100),
    ADD COLUMN IF NOT EXISTS street_name varchar(150),
    ADD COLUMN IF NOT EXISTS subdivision_purok varchar(150),
    ADD COLUMN IF NOT EXISTS city varchar(100),
    ADD COLUMN IF NOT EXISTS province varchar(100);

