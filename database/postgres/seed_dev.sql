-- Optional, non-secret development fixtures. No user or administrator accounts.

INSERT INTO public.resources (item_id, name, description, quantity, available)
VALUES
    ('DEV-ITEM-BALL', 'Development basketball', 'Non-production sample inventory item.', 3, 3),
    ('DEV-ITEM-CHAIR', 'Development chair', 'Non-production sample inventory item.', 10, 10)
ON CONFLICT (item_id) DO NOTHING;
