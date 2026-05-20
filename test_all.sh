#!/usr/bin/env sh

set -e

for (( i=81; i <= 85; ++i ))
do
  docker exec flexid_php$i composer update
  docker exec flexid_php$i composer run check
  echo "------ TESTS FOR PHP$i PASSED! ------"
done
