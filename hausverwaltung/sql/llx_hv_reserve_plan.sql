-- Rücklagen-Planung (Mehrjahresprogramm): geplante größere Instandhaltungen je Gebäude oder für alle Gebäude
CREATE TABLE llx_hv_reserve_plan (
  rowid        integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity       integer DEFAULT 1 NOT NULL,
  label        varchar(255) NOT NULL,
  fk_building  integer DEFAULT 0 NOT NULL,        -- 0 = alle Gebäude (Aufteilung nach Schlüssel)
  alloc_key    varchar(16) DEFAULT 'area' NOT NULL,
  year_planned integer NOT NULL,
  amount       double(24,8) DEFAULT 0 NOT NULL,   -- geschätzter Betrag brutto zu heutigen Preisen
  basis        varchar(255) DEFAULT NULL,         -- Schätzgrundlage (z. B. "ca. 390 m² Fassade x 30 €/m²")
  position     integer DEFAULT 0,
  active       smallint DEFAULT 1 NOT NULL,
  note         text,
  datec        datetime,
  tms          timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
