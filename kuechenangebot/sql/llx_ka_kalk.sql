-- Küchenkalkulation (eine je Angebot)
CREATE TABLE llx_ka_kalk (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  fk_soc integer DEFAULT NULL,
  fk_propal integer DEFAULT NULL,
  art varchar(16) DEFAULT 'privat' NOT NULL,
  lang2 varchar(8) DEFAULT '',
  label varchar(255) DEFAULT '',
  objekt text,
  params text,
  hinweise text,
  note_de text,
  note_lang2 text,
  total_ek double(24,8) DEFAULT 0,
  total_kosten double(24,8) DEFAULT 0,
  total_ht double(24,8) DEFAULT 0,
  total_lfm double(24,8) DEFAULT 0,
  total_stueck double(24,8) DEFAULT 0,
  montage_std double(24,8) DEFAULT 0,
  datec datetime,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat integer DEFAULT NULL
) ENGINE=innodb;
