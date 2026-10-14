-- Ejecutar sólo en base de prueba después de esquema + categorías.
-- Prueba real de restricciones y visibilidad; todos los datos de prueba se revierten.
DELIMITER //
CREATE PROCEDURE prodigo_model_smoke_test()
BEGIN
 DECLARE a BIGINT UNSIGNED;
 DECLARE b BIGINT UNSIGNED;
 DECLARE cat BIGINT UNSIGNED;
 DECLARE img BIGINT UNSIGNED;
 DECLARE total INT;
 DECLARE rejected BOOLEAN DEFAULT FALSE;
 DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
 START TRANSACTION;
 SELECT id INTO cat FROM categories WHERE slug = 'radios';
 IF cat IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falta categoría radios'; END IF;
 INSERT INTO pieces (code,slug,title,unit_type,component_count)
 VALUES ('TEST-PRODIGO-A','test-prodigo-a','Conjunto de prueba','set',2);
 SET a = LAST_INSERT_ID();
 INSERT INTO piece_categories VALUES (a,cat);
 INSERT INTO piece_field_values VALUES (a,cat,'marca','Marca de prueba');
 INSERT INTO piece_private (piece_id,physical_location,internal_notes)
 VALUES (a,'Ubicación privada','No debe salir en ficha pública');
 INSERT INTO pieces (code,slug,title,publication_status,availability)
 VALUES ('TEST-PRODIGO-B','test-prodigo-b','Pieza vendida de prueba','published','sold');
 SET b = LAST_INSERT_ID();
 SELECT COUNT(*) INTO total FROM public_pieces WHERE id = a;
 IF total <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Se filtró un borrador'; END IF;
 SELECT COUNT(*) INTO total FROM public_pieces WHERE id = b;
 IF total <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El vendido desapareció'; END IF;
 INSERT INTO images (piece_id,alt_text) VALUES (a,'Vista completa del conjunto');
 SET img = LAST_INSERT_ID();
 INSERT INTO piece_cover VALUES (a,img);
 BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejected = TRUE;
  INSERT INTO piece_cover VALUES (b,img);
 END;
 IF NOT rejected THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Portada ajena aceptada'; END IF;
 SET rejected = FALSE;
 BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejected = TRUE;
  INSERT INTO piece_relations VALUES (a,a,'visual','Auto vínculo',0);
 END;
 IF NOT rejected THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Auto vínculo aceptado'; END IF;
 INSERT INTO piece_relations VALUES (a,b,'visual','Comparten formas curvas',0);
 SET rejected = FALSE;
 BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejected = TRUE;
  UPDATE pieces SET price_mode='visible' WHERE id=a;
 END;
 IF NOT rejected THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Precio visible incompleto aceptado'; END IF;
 INSERT INTO transactions (piece_id,status) VALUES (a,'reserved');
 SET rejected = FALSE;
 BEGIN
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejected = TRUE;
  INSERT INTO transactions (piece_id,status) VALUES (a,'completed');
 END;
 IF NOT rejected THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Dos operaciones activas aceptadas'; END IF;
 UPDATE pieces SET publication_status='published' WHERE id=a;
 UPDATE pieces SET deleted_at=CURRENT_TIMESTAMP WHERE id=a;
 SELECT COUNT(*) INTO total FROM public_pieces WHERE id=a;
 IF total <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Se filtró la papelera'; END IF;
 ROLLBACK;
 SELECT 'OK: borradores, vendidos, papelera, portada, vínculos, precio y operaciones' AS result;
END//
DELIMITER ;
CALL prodigo_model_smoke_test();
DROP PROCEDURE prodigo_model_smoke_test;
