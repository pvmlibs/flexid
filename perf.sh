#!/usr/bin/env sh

rm -f src/Scripts/RefPerf/perf.txt
nice -20 docker exec flexid_php85 php -dopcache.enable=0 src/Scripts/perf.php $@
