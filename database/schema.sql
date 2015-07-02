CREATE TABLE person
(
  id integer PRIMARY KEY AUTOINCREMENT,
  login VARCHAR(255) NOT NULL ,
  name VARCHAR(255) NOT NULL
);

CREATE TABLE poll
(
  id integer PRIMARY KEY AUTOINCREMENT,
  date_start DATETIME NOT NULL,
  date_end DATETIME NOT NULL
);

CREATE TABLE vote
(
  voter_id integer,
  person_id integer,
  poll_id integer,
  rating integer NOT NULL,
  PRIMARY KEY (voter_id, person_id, poll_id),
  FOREIGN KEY (voter_id) references person(id),
  FOREIGN KEY (person_id) REFERENCES person(id),
  FOREIGN KEY (poll_id) REFERENCES poll(id)
);