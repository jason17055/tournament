prefix = /srv/www/htdocs/webtd

php_scripts = \
	config.php \
	index.php \
	player.php \
	tournament_dashboard.php \
	includes/db.php \
	includes/skin.php

all:

install:
	mkdir -p "$(prefix)/includes"
	for f in $(php_scripts); do \
		cp "$$f" "$(prefix)/$$f"; \
	done
