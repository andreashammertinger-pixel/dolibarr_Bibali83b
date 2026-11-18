ALTER TABLE llx_hv_costitem ADD COLUMN alloc_key varchar(16) DEFAULT 'area' NOT NULL;
ALTER TABLE llx_hv_costitem ADD COLUMN i18n text;
