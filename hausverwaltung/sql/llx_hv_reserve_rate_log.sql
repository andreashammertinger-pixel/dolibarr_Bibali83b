-- Verlauf der Festlegung des Rücklagen-Satzes je Haus (nachvollziehbar für die Eigentümerversammlung)
CREATE TABLE llx_hv_reserve_rate_log (
  rowid          integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity         integer DEFAULT 1 NOT NULL,
  fk_building    integer NOT NULL,
  datec          datetime,
  fk_user        integer DEFAULT NULL,
  mode           varchar(16),
  factor         double(24,4),
  rate_old       double(24,4),
  rate_new       double(24,4),
  legal_min      double(24,6),     -- gesetzlicher Mindestsatz zum Zeitpunkt
  plan_min       double(24,6),     -- Mindestsatz laut Rücklagen-Plan zum Zeitpunkt
  plan_total     double(24,2),     -- Summe der aktiven Planpositionen (heutige Preise)
  inflation      double(24,4),
  horizon        integer,
  balance        double(24,2),     -- Kontostand Rücklage zum Zeitpunkt
  note           text
) ENGINE=innodb;
