-- Borrowing, returning, inventory audit metadata, and overdue reminder support.
-- Safe to run repeatedly after migrations 008 and 014.

INSERT INTO public.service_verification_settings
    (service_type, token_ttl_seconds, otp_required)
VALUES
    ('borrow', 180, false),
    ('return', 180, false)
ON CONFLICT (service_type) DO UPDATE
SET token_ttl_seconds = EXCLUDED.token_ttl_seconds,
    otp_required = EXCLUDED.otp_required,
    updated_at = CURRENT_TIMESTAMP;

ALTER TABLE public.borrow_records
    ADD COLUMN IF NOT EXISTS borrow_verification_token_id bigint
        REFERENCES public.service_verification_tokens(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS return_verification_token_id bigint
        REFERENCES public.service_verification_tokens(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS borrowed_by_admin_id integer
        REFERENCES public.admins(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS returned_by_admin_id integer
        REFERENCES public.admins(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS overdue_notified_at timestamptz,
    ADD COLUMN IF NOT EXISTS overdue_notification_attempts smallint NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS overdue_notification_error text;

ALTER TABLE public.borrow_records
    DROP CONSTRAINT IF EXISTS borrow_records_overdue_notification_attempts_check;
ALTER TABLE public.borrow_records
    ADD CONSTRAINT borrow_records_overdue_notification_attempts_check
        CHECK (overdue_notification_attempts BETWEEN 0 AND 5);

CREATE INDEX IF NOT EXISTS idx_borrow_records_active_due_date
    ON public.borrow_records (status, due_date)
    WHERE status IN ('Borrowed', 'Overdue');
CREATE INDEX IF NOT EXISTS idx_borrow_records_overdue_notifications
    ON public.borrow_records (overdue_notified_at, due_date)
    WHERE status = 'Overdue';
CREATE INDEX IF NOT EXISTS idx_borrow_records_borrow_verification_token
    ON public.borrow_records (borrow_verification_token_id);
CREATE INDEX IF NOT EXISTS idx_borrow_records_return_verification_token
    ON public.borrow_records (return_verification_token_id);
