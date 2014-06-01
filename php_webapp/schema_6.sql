CREATE TABLE person_attrib_float (
	person INTEGER NOT NULL,
	attrib VARCHAR(20) NOT NULL,
	value FLOAT NOT NULL,
	FOREIGN KEY (person) REFERENCES person (id),
	PRIMARY KEY (person, attrib)
	);

CREATE TABLE person_attrib_value (
	person INTEGER NOT NULL,
	attrib VARCHAR(20) NOT NULL,
	value VARCHAR(250),
	FOREIGN KEY (person) REFERENCES person (id),
	PRIMARY KEY (person, attrib)
	);

ALTER TABLE person DROP COLUMN score;
ALTER TABLE person DROP COLUMN score_alt;

UPDATE master SET version=7;
