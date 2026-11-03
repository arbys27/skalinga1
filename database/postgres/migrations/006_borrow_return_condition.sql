-- Borrowed-item return condition. Safe to run repeatedly.
ALTER TABLE public.borrow_records
    ADD COLUMN IF NOT EXISTS return_condition varchar(500);
