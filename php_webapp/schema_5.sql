ALTER TABLE contest_participant CHANGE COLUMN status participant_status CHAR(1);

UPDATE master SET version=6;
