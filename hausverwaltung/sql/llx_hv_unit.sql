-- Wohneinheiten / Apartments, verknüpft mit dem Geschäftspartner (llx_societe)
CREATE TABLE llx_hv_unit (
  rowid          integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity         integer DEFAULT 1 NOT NULL,
  ref            varchar(32) NOT NULL,             -- z.B. 1.1
  fk_building    integer NOT NULL,
  fk_soc         integer DEFAULT NULL,             -- Geschäftspartner "Apartment x.y"
  area           double(24,4) DEFAULT 0 NOT NULL,  -- Wohnfläche m²
  reserve_rate   double(24,4) DEFAULT 0 NOT NULL,  -- Rücklage €/m²/Monat
  admin_rate     double(24,4) DEFAULT 0 NOT NULL,  -- Verwaltung €/m²/Monat
  position       integer DEFAULT 0,
  active         smallint DEFAULT 1 NOT NULL,
  note           text,
  datec          datetime,
  tms            timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
