-- Protokoll: Vorschreibungen, die nach Änderung der Sätze neu berechnet wurden (gleiche Nummer, ohne Gutschrift)
CREATE TABLE llx_hv_invoice_recalc (
  rowid       integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity      integer DEFAULT 1 NOT NULL,
  fk_invoice  integer NOT NULL,
  old_total   double(24,8) DEFAULT 0,
  new_total   double(24,8) DEFAULT 0,
  action      varchar(8) DEFAULT 'recalc', -- recalc = neu berechnet, keep = bewusst beibehalten
  diffs       text,                 -- Abweichungen vor der Neuberechnung (Text)
  datec       datetime,
  fk_user     integer DEFAULT NULL
) ENGINE=innodb;
