ALTER TABLE opleiding_vak_periode_uren
    ADD COLUMN blokuur_toegestaan TINYINT(1) NOT NULL DEFAULT 0 AFTER uren_per_week;
