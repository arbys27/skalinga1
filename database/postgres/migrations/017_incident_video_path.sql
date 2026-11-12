ALTER TABLE public.incidents
    ADD COLUMN IF NOT EXISTS video_path varchar(255);
