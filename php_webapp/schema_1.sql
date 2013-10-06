CREATE TABLE game_definition (
	id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
	tournament INTEGER NOT NULL,
	name VARCHAR(200) NOT NULL,
	seat_names VARCHAR(200) NOT NULL,
	FOREIGN KEY (tournament) REFERENCES tournament (id)
	);

INSERT INTO game_definition (tournament,name)
	SELECT DISTINCT tournament,game
	FROM contest
	WHERE game IS NOT NULL
	ORDER BY tournament,game;

ALTER TABLE contest CHANGE game game_name VARCHAR(200);
ALTER TABLE contest ADD COLUMN game INTEGER AFTER game_name;
ALTER TABLE contest ADD FOREIGN KEY (game) REFERENCES game_definition (id);
UPDATE contest c SET game=(SELECT id FROM game_definition g WHERE g.name=c.game_name AND g.tournament=c.tournament)
	WHERE game_name IS NOT NULL;
ALTER TABLE contest DROP COLUMN game_name;

INSERT INTO column_type (name, type_data) VALUES (
	'PERSON.STATUS', 'enum:prereg,ready,absent'
	);

UPDATE master SET version=2;
