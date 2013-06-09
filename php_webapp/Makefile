prefix = /srv/www/htdocs/webtd

data_files = \
	webtd.css \
	webtd.js \
	images/plus.png \
	images/minus.png

php_scripts = \
	config.php \
	contest.php \
	contest_participant.php \
	index.php \
	list_roster.inc.php \
	login.php \
	person.php \
	player_scorecard.php \
	run_ratings.php \
	tournament.php \
	tournament_dashboard.php \
	includes/auth.php \
	includes/db.php \
	includes/ratings_functions.php \
	includes/skin.php

all:

install:
	mkdir -p "$(prefix)/images"
	mkdir -p "$(prefix)/includes"
	for f in $(php_scripts) $(data_files); do \
		cp "$$f" "$(prefix)/$$f"; \
	done
