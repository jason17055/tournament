ALTER TABLE person ADD COLUMN ordinal INTEGER AFTER name;
ALTER TABLE person ADD COLUMN phone VARCHAR(200) AFTER mail;
ALTER TABLE person ADD COLUMN is_team CHAR(1) NOT NULL DEFAULT 'N';
ALTER TABLE person ADD COLUMN member_of INTEGER;
ALTER TABLE person ADD COLUMN score INTEGER;
ALTER TABLE person ADD COLUMN score_alt VARCHAR(200);

ALTER TABLE tournament ADD COLUMN ratings CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_ordinal CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_member_number CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_entry_rank CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_home_location CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_mail CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN use_person_phone CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE tournament ADD COLUMN scoreboard_roundrobin_style CHAR(1) NOT NULL DEFAULT 'Y';

ALTER TABLE tournament CHANGE COLUMN multi_table multi_venue CHAR(1) NOT NULL DEFAULT 'Y';

-- status one of 'enabled','disabled'
CREATE TABLE venue (
	id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
	tournament INTEGER NOT NULL,
	venue_name VARCHAR(200),
	status VARCHAR(20),
	FOREIGN KEY (tournament) REFERENCES tournament (id)
	);

ALTER TABLE game_definition ADD COLUMN use_scenario CHAR(1) NOT NULL DEFAULT 'Y';
ALTER TABLE contest ADD COLUMN venue INTEGER;
-- FOREIGN KEY contest (venue) REFERENCES venue (id)
ALTER TABLE contest ADD COLUMN starts VARCHAR(20);

-- transfer contest 'board' information into 'venue' table
INSERT INTO venue (tournament,venue_name)
SELECT DISTINCT tournament,board
	FROM contest;

UPDATE contest c SET venue=(
	SELECT id FROM venue WHERE tournament=c.tournament AND venue_name=c.board
	) WHERE board IS NOT NULL;
ALTER TABLE contest DROP COLUMN board;

UPDATE master SET version=4;
