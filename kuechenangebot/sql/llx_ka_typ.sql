-- Küchentyp innerhalb einer Kalkulation (ein Nobilia-Angebot × Stückzahl)
CREATE TABLE llx_ka_typ (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  fk_kalk integer NOT NULL,
  rang integer DEFAULT 0,
  label varchar(255) DEFAULT '',
  qty double(24,8) DEFAULT 1,
  preis_modus varchar(8) DEFAULT 'eur',
  katalog double(24,8) DEFAULT 0,
  faktor double(24,8) DEFAULT 1,
  lfm double(24,8) DEFAULT 0,
  vk_override double(24,8) DEFAULT NULL,
  data mediumtext,
  file_an varchar(255) DEFAULT '',
  file_bd varchar(255) DEFAULT '',
  image varchar(255) DEFAULT ''
) ENGINE=innodb;
