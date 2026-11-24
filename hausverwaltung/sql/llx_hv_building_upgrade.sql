-- 1.15.0: Rücklagen-Satz je Haus (Festlegung mit Begründung)
ALTER TABLE llx_hv_building ADD COLUMN reserve_mode varchar(16) DEFAULT 'manual';
ALTER TABLE llx_hv_building ADD COLUMN reserve_factor double(24,4) DEFAULT 1;
ALTER TABLE llx_hv_building ADD COLUMN reserve_rate double(24,4) DEFAULT 0;
ALTER TABLE llx_hv_building ADD COLUMN reserve_note text;
