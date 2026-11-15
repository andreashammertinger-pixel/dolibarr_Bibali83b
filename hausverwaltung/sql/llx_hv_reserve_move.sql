-- Protokoll der automatischen Rücklagen-Umbuchungen
CREATE TABLE llx_hv_reserve_move (
  rowid          integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity         integer DEFAULT 1 NOT NULL,
  fk_building    integer NOT NULL,
  direction      varchar(8) NOT NULL,              -- 'in' = Zuführung, 'out' = Entnahme
  element        varchar(32) NOT NULL,             -- 'payment' (Kunde) oder 'payment_supplier'
  fk_payment     integer NOT NULL,
  fk_invoice     integer NOT NULL,
  amount         double(24,8) NOT NULL,
  fk_bank_line_from integer DEFAULT NULL,
  fk_bank_line_to   integer DEFAULT NULL,
  date_move      date,
  datec          datetime,
  fk_user        integer DEFAULT NULL
) ENGINE=innodb;
