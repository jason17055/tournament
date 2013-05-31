prefix = /srv/www/htdocs/webtd

php_scripts = \
	as_dominion_results.php \
	config.php \
	contest.php \
	contest_participant.php \
	index.php \
	player.php \
	run_ratings.php \
	tournament_dashboard.php \
	includes/db.php \
	includes/ratings_functions.php \
	includes/skin.php

all:

install:
	mkdir -p "$(prefix)/includes"
	for f in $(php_scripts); do \
		cp "$$f" "$(prefix)/$$f"; \
	done
