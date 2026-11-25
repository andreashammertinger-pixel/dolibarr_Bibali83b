-- 1.17.0: Testversand vor dem Versand eines Rundschreibens
ALTER TABLE llx_hv_mailing ADD COLUMN date_test datetime;
