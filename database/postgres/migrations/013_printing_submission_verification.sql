-- Add physical and email verification metadata for printing submissions.
-- Safe to run repeatedly after migration 008.

ALTER TABLE public.printing_requests
    ADD COLUMN IF NOT EXISTS verified_at timestamptz,
    ADD COLUMN IF NOT EXISTS verified_by_admin_id integer
        REFERENCES public.admins(id) ON UPDATE CASCADE ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_printing_requests_verified_at
    ON public.printing_requests (verified_at);

ALTER TABLE public.printing_requests
    DROP CONSTRAINT IF EXISTS printing_requests_status_check;
ALTER TABLE public.printing_requests
    ADD CONSTRAINT printing_requests_status_check
        CHECK (status IN (
            'AwaitingVerification',
            'Pending',
            'Printing',
            'Completed',
            'Claimed',
            'Rejected'
        ));
