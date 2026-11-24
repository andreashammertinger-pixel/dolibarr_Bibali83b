-- Gebäude (Haus 1, Haus 2, Haus 3 ...)
CREATE TABLE llx_hv_building (
  rowid              integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity             integer DEFAULT 1 NOT NULL,
  ref                varchar(32) NOT NULL,
  label              varchar(255) NOT NULL,
  fk_bank_reserve    integer DEFAULT NULL,       -- Bankkonto (llx_bank_account) der Rücklage dieses Gebäudes
  fk_product_reserve integer DEFAULT NULL,       -- Dienstleistung "Rücklage Haus x"
  position           integer DEFAULT 0,
  active             smallint DEFAULT 1 NOT NULL,
  note               text,
  reserve_mode       varchar(16) DEFAULT 'manual',  -- Rücklagen-Satz: manual | plan | factor | legal
  reserve_factor     double(24,4) DEFAULT 1,        -- Faktor auf den Mindestsatz laut Plan
  reserve_rate       double(24,4) DEFAULT 0,        -- festgelegter Rücklagen-Satz €/m²/Monat (0 = Satz je Apartment)
  reserve_note       text,                          -- Begründung / Beschluss
  datec              datetime,
  tms                timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
