ALTER TABLE tournament ADD COLUMN use_teams CHAR(1) NOT NULL DEFAULT 'N';

UPDATE master SET version=5;
