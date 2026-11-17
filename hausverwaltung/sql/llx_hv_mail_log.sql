-- Versandprotokoll Vorschreibungen / Abrechnungen
CREATE TABLE llx_hv_mail_log (
  rowid       integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity      integer DEFAULT 1 NOT NULL,
  fk_invoice  integer NOT NULL,
  sendto      text,
  files       text,
  status      varchar(8) NOT NULL,      -- ok | error
  error       text,
  datec       datetime,
  fk_user     integer DEFAULT NULL
) ENGINE=innodb;
