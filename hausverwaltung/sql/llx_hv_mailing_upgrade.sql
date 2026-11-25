-- 1.17.0: Testversand vor dem Versand eines Rundschreibens
ALTER TABLE llx_hv_mailing ADD COLUMN date_test datetime;
-- 1.18.0: Anhänge nur in der Sprache des Eigentümers
ALTER TABLE llx_hv_mailing ADD COLUMN ownlang_only integer DEFAULT 1;
