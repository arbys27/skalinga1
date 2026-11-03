-- Add phone_verified field to users table for SMS OTP authentication
-- PostgreSQL/Supabase Compatible Version
-- ============================================================================

-- Add phone_verified column (PostgreSQL: BOOLEAN instead of TINYINT)
    ALTER TABLE "users" 
    ADD COLUMN "phone_verified" BOOLEAN DEFAULT false;

    -- Add phone_verified_at timestamp column
    ALTER TABLE "users"
    ADD COLUMN "phone_verified_at" TIMESTAMP WITH TIME ZONE NULL;

    -- Create index for phone verification status
    CREATE INDEX "idx_users_phone_verified" ON "users"("phone_verified");

    -- Add comments for documentation (PostgreSQL)
    COMMENT ON COLUMN "users"."phone_verified" IS 'Phone verification status';
    COMMENT ON COLUMN "users"."phone_verified_at" IS 'Phone verification timestamp';

    -- ============================================================================
    -- Update existing users to mark phone as verified (for backward compatibility)
    -- ============================================================================
    UPDATE "users" 
    SET "phone_verified" = true 
    WHERE "status" = 'active' AND "created_at" IS NOT NULL;

-- ============================================================================
-- Verification - Show updated schema
-- ============================================================================
-- Run this query to verify the changes:
-- SELECT column_name, data_type, column_default FROM information_schema.columns 
-- WHERE table_name = 'users' AND column_name IN ('phone_verified', 'phone_verified_at');
