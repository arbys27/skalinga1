ALTER TABLE borrow_records
    ADD COLUMN return_condition VARCHAR(500) NULL AFTER return_date;
