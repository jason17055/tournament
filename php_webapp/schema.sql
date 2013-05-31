CREATE TABLE master (
	version INTEGER NOT NULL
	);

INSERT INTO master (version) VALUES (1);

CREATE TABLE tournament (
	id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
	name VARCHAR(200) NOT NULL,
	location VARCHAR(200),
	start_time DATETIME
	);

CREATE TABLE player (
	id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
	tournament INTEGER NOT NULL,
	name VARCHAR(200),
	member_number VARCHAR(200),
	home_location VARCHAR(200),
	FOREIGN KEY (tournament) REFERENCES tournament (id)
	);

CREATE TABLE contest (
	id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
	tournament INTEGER NOT NULL,
	game VARCHAR(200),
	board VARCHAR(200),
	status VARCHAR(200),
	started DATETIME,
	finished DATETIME,
	round VARCHAR(200),
	FOREIGN KEY (tournament) REFERENCES tournament (id)
	);

CREATE TABLE contest_participant (
	id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
	contest INTEGER NOT NULL,
	player INTEGER,
	seat VARCHAR(200),
	handicap VARCHAR(200),
	turn_order INTEGER,
	score VARCHAR(200),
	placement INTEGER,
	FOREIGN KEY (contest) REFERENCES contest (id),
	FOREIGN KEY (player) REFERENCES player (id)
	);

CREATE TABLE column_type (
	name VARCHAR(200) NOT NULL PRIMARY KEY,
	type_data VARCHAR(4000)
	);

INSERT INTO column_type (name, type_data) VALUES (
	'PLAY.STATUS', 'enum:proposed,assigned,started,suspended,aborted,completed'
	);

CREATE TABLE rating_batch (
	id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT
	);

CREATE TABLE rating_identity (
	id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
	batch INTEGER NOT NULL,
	player INTEGER NOT NULL,
	rating_cycle INTEGER NOT NULL,
	rating FLOAT NOT NULL,
	FOREIGN KEY (batch) REFERENCES rating_batch (id),
	FOREIGN KEY (player) REFERENCES player (id)
	);

CREATE TABLE rating_data (
	batch INTEGER NOT NULL,
	player_a INTEGER NOT NULL,
	player_b INTEGER NOT NULL,
	actual_performance FLOAT NOT NULL,
	expected_performance FLOAT NOT NULL,
	weight FLOAT NOT NULL,
	FOREIGN KEY (batch) REFERENCES rating_batch (id),
	FOREIGN KEY (player_a) REFERENCES rating_identity (id),
	FOREIGN KEY (player_b) REFERENCES rating_identity (id)
	);
