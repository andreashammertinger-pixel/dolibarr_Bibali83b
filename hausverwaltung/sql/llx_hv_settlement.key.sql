ALTER TABLE llx_hv_settlement ADD UNIQUE INDEX uk_hv_settlement (entity, year, fk_unit);
