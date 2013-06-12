prefix = /srv/www/htdocs/webtd

data_files = \
	webtd.css \
	webtd.js \
	images/plus.png \
	images/minus.png \
	images/red_cross.png \
	images/bad_result.png \
	images/good_result.png \
	images/neutral_result.png \
	images/very_bad_result.png \
	images/very_good_result.png

php_scripts = \
	config.php \
	contest.php \
	contest_participant.php \
	index.php \
	list_roster.inc.php \
	login.php \
	pairings.php \
	person.php \
	player_scorecard.php \
	rating_chart.inc.php \
	run_ratings.php \
	tournament.php \
	tournament_dashboard.php \
	includes/auth.php \
	includes/db.php \
	includes/pairing_functions.php \
	includes/ratings_functions.php \
	includes/skin.php

all:

install:
	mkdir -p "$(prefix)/images"
	mkdir -p "$(prefix)/includes"
	for f in $(php_scripts) $(data_files); do \
		cp "$$f" "$(prefix)/$$f"; \
	done
