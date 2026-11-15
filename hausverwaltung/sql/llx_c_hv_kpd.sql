-- KPD 2025 (Klasifikacija proizvoda po djelatnostima) – 6-stellige Codes, Quelle: kpd.informacija.hr, deutsche Übersetzung ergänzt
CREATE TABLE llx_c_hv_kpd (
  rowid     integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  code      varchar(12) NOT NULL,
  label_hr  varchar(512) NOT NULL,
  label_de  varchar(512),
  active    smallint DEFAULT 1 NOT NULL
) ENGINE=innodb;
