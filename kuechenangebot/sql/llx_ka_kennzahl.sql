-- Kennzahlen früherer Küchen (händisch erfasst; angenommene Angebote des Moduls kommen automatisch dazu)
CREATE TABLE llx_ka_kennzahl (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  datum date,
  art varchar(16) DEFAULT 'privat' NOT NULL,
  label varchar(255) DEFAULT '',
  stueck double(24,8) DEFAULT 1,
  lfm double(24,8) DEFAULT 0,
  ek double(24,8) DEFAULT 0,
  kosten double(24,8) DEFAULT 0,
  vk double(24,8) DEFAULT 0,
  montage_std double(24,8) DEFAULT 0,
  note text
) ENGINE=innodb;
