CREATE TABLE score (
	player INTEGER NOT NULL,
	score_method VARCHAR(20) NOT NULL,
	score FLOAT NOT NULL,
	FOREIGN KEY (player) REFERENCES person (id),
	PRIMARY KEY (player, score_method)
	);

UPDATE master SET version=7;
