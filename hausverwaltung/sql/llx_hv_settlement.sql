-- Gebuchte Jahresabrechnungen je Apartment. Guthaben (saldo > 0) wird mit den folgenden Vorschreibungen verrechnet.
CREATE TABLE llx_hv_settlement (
  rowid            integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity           integer DEFAULT 1 NOT NULL,
  year             integer NOT NULL,
  fk_unit          integer NOT NULL,
  fk_soc           integer DEFAULT NULL,
  actual           double(24,8) DEFAULT 0 NOT NULL,   -- Ist-Kosten Anteil
  prepaid          double(24,8) DEFAULT 0 NOT NULL,   -- vorgeschriebene BK-Akonti
  saldo            double(24,8) DEFAULT 0 NOT NULL,   -- prepaid - actual (+ Guthaben / - Nachzahlung)
  fk_invoice       integer DEFAULT NULL,              -- Nachzahlungsrechnung
  credit_remaining double(24,8) DEFAULT 0 NOT NULL,   -- noch nicht verrechnetes Guthaben
  datec            datetime,
  fk_user          integer DEFAULT NULL
) ENGINE=innodb;
