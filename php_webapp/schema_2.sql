ALTER TABLE tournament ADD COLUMN multi_table CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN vocab_table VARCHAR(20) NOT NULL DEFAULT 'table';

UPDATE master SET version=3;
