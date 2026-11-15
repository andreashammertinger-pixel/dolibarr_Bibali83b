ALTER TABLE llx_hv_reserve_move ADD INDEX idx_hv_reserve_move_payment (element, fk_payment);
ALTER TABLE llx_hv_reserve_move ADD INDEX idx_hv_reserve_move_building (fk_building);
