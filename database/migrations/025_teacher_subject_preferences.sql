ALTER TABLE leraar_vakken
    ADD COLUMN voorkeur_percentage TINYINT UNSIGNED NOT NULL DEFAULT 100 AFTER vak_id;
