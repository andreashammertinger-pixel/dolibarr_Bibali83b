-- Kostenarten / Kostenplan. fk_building = 0 => betrifft alle Gebäude (Verteilung nach m² aller Einheiten)
CREATE TABLE llx_hv_costitem (
  rowid          integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity         integer DEFAULT 1 NOT NULL,
  label          varchar(255) NOT NULL,
  fk_building    integer DEFAULT 0 NOT NULL,
  period_months  integer DEFAULT 12 NOT NULL,      -- 12 = jährlich, 6 = halbjährlich, 3 = vierteljährlich, 1 = monatlich
  amount_net     double(24,8) DEFAULT 0 NOT NULL,  -- Planbetrag netto je Periode
  vat_rate       double(6,3) DEFAULT 0 NOT NULL,   -- MwSt-Satz in % (25, 13, 0 ...)
  fk_soc_supplier integer DEFAULT NULL,            -- üblicher Lieferant
  reference      varchar(128) DEFAULT NULL,        -- Polizzennummer, Vertragsnummer ...
  position       integer DEFAULT 0,
  active         smallint DEFAULT 1 NOT NULL,
  note           text,
  datec          datetime,
  tms            timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
