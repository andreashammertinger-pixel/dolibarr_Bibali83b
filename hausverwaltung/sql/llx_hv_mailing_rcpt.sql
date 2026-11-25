-- Versandprotokoll Rundschreiben je Empfänger
CREATE TABLE llx_hv_mailing_rcpt (
  rowid       integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity      integer DEFAULT 1 NOT NULL,
  fk_mailing  integer NOT NULL,
  fk_soc      integer NOT NULL,
  email       text,
  lang        varchar(8),
  files       text,
  status      varchar(8) NOT NULL,
  error       text,
  datec       datetime
) ENGINE=innodb;
