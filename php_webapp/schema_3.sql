ALTER TABLE person ADD COLUMN ordinal INTEGER AFTER name;
ALTER TABLE person ADD COLUMN phone VARCHAR(200) AFTER mail;

ALTER TABLE tournament ADD COLUMN ratings CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_member_number CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_entry_rank CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_home_location CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_mail CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_phone CHAR(1) NOT NULL DEFAULT 'Y';

--status one of 'enabled','disabled'
CREATE TABLE venue (
	id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
	tournament INTEGER NOT NULL,
	venue_name VARCHAR(200),
	status VARCHAR(20),
	FOREIGN KEY (tournament) REFERENCES tournament (id)
	);

CREATE TABLE reservation (
	contest INTEGER NOT NULL,
	venue INTEGER NOT NULL,
	starts DATETIME NOT NULL,
	ends DATETIME NOT NULL,
	FOREIGN KEY (contest) REFERENCES contest(id),
	FOREIGN KEY (venue) REFERENCES venue(id),
	PRIMARY KEY (contest, venue, starts)
	);

ALTER TABLE game_definition ADD COLUMN use_scenario CHAR(1) NOT NULL DEFAULT 'Y';

UPDATE master SET version=4;
