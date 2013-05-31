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
	name VARCHAR(200),
	member_number VARCHAR(200),
	home_location VARCHAR(200)
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

