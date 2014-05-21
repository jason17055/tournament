ALTER TABLE person ADD COLUMN ordinal INTEGER AFTER name;
ALTER TABLE person ADD COLUMN phone VARCHAR(200) AFTER mail;

ALTER TABLE tournament ADD COLUMN ratings CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_member_number CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_entry_rank CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_home_location CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_mail CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_phone CHAR(1) NOT NULL DEFAULT 'Y';

UPDATE master SET version=4;
