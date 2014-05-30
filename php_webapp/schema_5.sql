ALTER TABLE contest_participant CHANGE COLUMN status participant_status CHAR(1);
ALTER TABLE tournament ADD COLUMN schedule_granularity INTEGER;

UPDATE master SET version=6;
