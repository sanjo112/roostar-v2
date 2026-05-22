ALTER TABLE schooljaar_periodes
    ADD COLUMN week_van_jaar SMALLINT UNSIGNED NULL AFTER week_van,
    ADD COLUMN week_tot_jaar SMALLINT UNSIGNED NULL AFTER week_tot,
    ADD INDEX idx_schooljaar_periodes_weekjaren (week_van_jaar, week_van, week_tot_jaar, week_tot);

UPDATE schooljaar_periodes sp
INNER JOIN schooljaren sj ON sj.id = sp.schooljaar_id
SET
    sp.week_van_jaar = CASE
        WHEN sp.week_van >= WEEK(sj.startdatum, 3) THEN YEAR(sj.startdatum)
        ELSE YEAR(sj.einddatum)
    END,
    sp.week_tot_jaar = CASE
        WHEN sp.week_tot >= WEEK(sj.startdatum, 3) THEN YEAR(sj.startdatum)
        ELSE YEAR(sj.einddatum)
    END
WHERE sp.week_van_jaar IS NULL
   OR sp.week_tot_jaar IS NULL;
