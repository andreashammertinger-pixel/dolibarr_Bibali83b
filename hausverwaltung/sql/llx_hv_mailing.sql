-- Rundschreiben an Eigentümer
CREATE TABLE llx_hv_mailing (
  rowid       integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity      integer DEFAULT 1 NOT NULL,
  subject     varchar(255),
  body        text,
  srclang     varchar(8),
  i18n        text,                 -- {lang: {subject, body, ai}} Übersetzungen (bearbeitbar)
  docs        text,                 -- [{id, title, versions: {lang|all: datei}, ai: {lang: datei}}] bzw. Modul-Dokumente {id, gen, param}
  recipients  text,                 -- [socid, …] ausgewählte Apartments
  status      varchar(8) DEFAULT 'draft' NOT NULL,   -- draft | sent
  datec       datetime,
  date_sent   datetime,
  date_test   datetime,             -- letzter erfolgreicher Testversand (wird bei jeder Änderung gelöscht)
  fk_user     integer DEFAULT NULL,
  tms         timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
