
# perhaps change to php-cli for your system
PHPCLI := /usr/bin/php

PHPDBIN := phpd

all: ${PHPDBIN}

${PHPDBIN}: phpd.php
	echo "#! "${PHPCLI} > $@
	cat $< >> $@
	chmod +x $@

clean:
	rm ${PHPDBIN}
	find -name "*~" | xargs rm -f
