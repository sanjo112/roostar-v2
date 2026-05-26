ALTER TABLE opleiding_vakken
    ADD COLUMN blokuur_toegestaan TINYINT(1) NOT NULL DEFAULT 0 AFTER keuzevak;
