-- Ideen und ToDos (aus der App „Ideenbox“ oder im Büro erfasst)
CREATE TABLE llx_ideenbox_note (
  rowid            integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity           integer DEFAULT 1 NOT NULL,      -- Firma, in der gesammelt wird (z. B. Holding)
  uuid             varchar(64) DEFAULT NULL,        -- Kennung aus der App (verhindert Dubletten beim Synchronisieren)
  type             varchar(16) DEFAULT 'idea' NOT NULL, -- idea | todo
  text             text NOT NULL,
  date_reminder    datetime DEFAULT NULL,           -- Erinnerung / Termin
  status           smallint DEFAULT 0 NOT NULL,     -- 0 neu, 1 zugeordnet/in Arbeit, 2 erledigt, 9 verworfen
  priority         smallint DEFAULT 0 NOT NULL,     -- 0 normal, 1 wichtig
  source           varchar(16) DEFAULT NULL,        -- voice | keyboard | web
  fk_target_entity integer DEFAULT NULL,            -- zugeordnete Firma (Multicompany)
  fk_soc           integer DEFAULT NULL,            -- zugeordneter Geschäftspartner
  fk_project       integer DEFAULT NULL,            -- zugeordnetes Projekt
  fk_actioncomm    integer DEFAULT NULL,            -- erzeugter Agenda-Eintrag
  fk_task          integer DEFAULT NULL,            -- erzeugte Projektaufgabe
  fk_user_author   integer DEFAULT NULL,
  fk_user_assign   integer DEFAULT NULL,            -- zuständig
  date_creation    datetime DEFAULT NULL,
  date_modif       datetime DEFAULT NULL,           -- für den Abgleich mit der App
  date_done        datetime DEFAULT NULL,
  tms              timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  import_key       varchar(14) DEFAULT NULL
) ENGINE=innodb;
