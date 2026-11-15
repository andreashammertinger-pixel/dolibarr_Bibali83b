ALTER TABLE llx_hv_unit ADD UNIQUE INDEX uk_hv_unit_ref (ref, entity);
ALTER TABLE llx_hv_unit ADD INDEX idx_hv_unit_fk_building (fk_building);
ALTER TABLE llx_hv_unit ADD INDEX idx_hv_unit_fk_soc (fk_soc);
