prefix = /srv/www/htdocs/webtd

php_scripts = \
	config.php \
	index.php \
	includes/db.php

all:

install:
	mkdir -p "$(prefix)/includes"
	for f in $(php_scripts); do \
		cp "$$f" "$(prefix)/$$f"; \
	done
