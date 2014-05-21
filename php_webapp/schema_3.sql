ALTER TABLE person ADD COLUMN phone VARCHAR(200) AFTER mail;

UPDATE master SET version=4;
