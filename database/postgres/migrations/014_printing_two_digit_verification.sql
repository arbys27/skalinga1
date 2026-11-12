-- Add request-bound, short-lived two-digit verification for printing.
-- Safe to run repeatedly after migration 013.

ALTER TABLE public.service_verification_tokens
    ADD COLUMN IF NOT EXISTS code_hash char(64),
    ADD COLUMN IF NOT EXISTS code_expires_at timestamptz,
    ADD COLUMN IF NOT EXISTS code_attempts smallint NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS code_verified_at timestamptz;

ALTER TABLE public.service_verification_tokens
    DROP CONSTRAINT IF EXISTS service_verification_tokens_code_attempts_check;
ALTER TABLE public.service_verification_tokens
    ADD CONSTRAINT service_verification_tokens_code_attempts_check
        CHECK (code_attempts BETWEEN 0 AND 5);

ALTER TABLE public.printing_requests
    ADD COLUMN IF NOT EXISTS print_verification_token_id bigint
        REFERENCES public.service_verification_tokens(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS print_verified_at timestamptz,
    ADD COLUMN IF NOT EXISTS print_verified_by_admin_id integer
        REFERENCES public.admins(id) ON UPDATE CASCADE ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_service_verification_tokens_code_expiry
    ON public.service_verification_tokens (code_expires_at);
CREATE INDEX IF NOT EXISTS idx_printing_requests_print_verified_at
    ON public.printing_requests (print_verified_at);
