ALTER TABLE llx_ideenbox_note ADD UNIQUE INDEX uk_ideenbox_note_uuid (entity, uuid);
ALTER TABLE llx_ideenbox_note ADD INDEX idx_ideenbox_note_status (entity, status);
ALTER TABLE llx_ideenbox_note ADD INDEX idx_ideenbox_note_modif (entity, date_modif);
