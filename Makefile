
# perhaps change to php-cli for your system
PHPCLI := /usr/bin/php
PHPDBIN := phpd
# apache? or create special user... init scripts are run by root... so...
USERNAME := ypb

DESTDIR := 

PREFIX := /usr
LIBEXEC := ${PREFIX}/libexec/${PHPDBIN}
# this is different on debians or redhats (retoggle comments)
INITD := /etc/rc.d
#INITD := /etc/init.d
# doing logging with syslog for now, but data debug may better go
# in its own logile (TODO)
LOGDIR := /var/log/phpd
#LOGDIR := /var/log/${PHPDBIN}

# there should be no need to edit beyod this point...

COMMAND := ${LIBEXEC}/${PHPDBIN}

GENFILES := ${PHPDBIN} init.sh
lib_srcs := $(shell ls lib)
LIBFILES := $(lib_srcs:%=lib/%)

all: ${GENFILES}

${PHPDBIN}: phpd.php
	echo "#! "${PHPCLI} > $@
	cat $< >> $@
	chmod +x $@

init.sh: in/init
	sed "s|@USERNAME@|${USERNAME}|g;\
	     s|@PROGNAME@|${PHPDBIN}|g;\
	     s|@LIBEXEC@|${LIBEXEC}|g;\
	     s|@COMMAND@|${COMMAND}|g" $< > $@
	chmod +x $@

install: all install-dirs install-main

install-dirs:
	mkdir -p ${DESTDIR}${INITD}
	mkdir -p ${DESTDIR}${LIBEXEC}/lib
# logdir
	mkdir -p ${DESTDIR}${LOGDIR}
	chown ${USERNAME} ${DESTDIR}${LOGDIR}
	chmod 750 ${DESTDIR}${LOGDIR}

install-main: install-bin install-init install-config install-lib

install-bin: ${PHPDBIN}
	cp $< ${DESTDIR}${LIBEXEC}
install-init: init.sh
	cp $< ${DESTDIR}${INITD}/${PHPDBIN}
install-config: config.php
	cp $< ${DESTDIR}${LIBEXEC}
install-lib: ${LIBFILES}
	for f in ${LIBFILES} ; do \
	  cp $$f ${DESTDIR}${LIBEXEC}/lib ; \
	done

clean:
	rm ${GENFILES}
	find -name "*~" | xargs rm -f
