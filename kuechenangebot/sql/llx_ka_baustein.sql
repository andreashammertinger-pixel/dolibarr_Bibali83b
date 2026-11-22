-- Textbausteine (Hinweise) in Deutsch + Übersetzungen
CREATE TABLE llx_ka_baustein (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  code varchar(32) NOT NULL,
  label varchar(128) DEFAULT '',
  text_de text,
  text_hr text,
  text_en text,
  vorauswahl smallint DEFAULT 0,
  aktiv smallint DEFAULT 1,
  rang integer DEFAULT 0
) ENGINE=innodb;
