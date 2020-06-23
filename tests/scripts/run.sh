#!/bin/sh -e

TESTDIR=$PWD
BASEDIR=`dirname $TESTDIR`
BASEDIR=`dirname $BASEDIR`
COMPOSER_BINARY="$BASEDIR/vendor/bin/composer"
PLUGIN_SOURCE="$BASEDIR/package.zip"

# create zip file from package source
cd $BASEDIR
rm -f $PLUGIN_SOURCE
find . -type f | grep -vP '^./.git|^./tests|^./vendor' | zip "$PLUGIN_SOURCE" -@
cd $TESTDIR

# run tests
for TESTCASE in "test_install" "test_upgrade" ; do
    rm -rf ./run/$TESTCASE/
    mkdir -p ./run/$TESTCASE/
    cp ./$TESTCASE.sh ./run/$TESTCASE/$TESTCASE.sh
    cd ./run/$TESTCASE/
    echo "\nRUNNING TEST $TESTCASE..."
    export COMPOSER_BINARY BASEDIR PLUGIN_SOURCE
    sh -e ./$TESTCASE.sh
    RESULT=$?
    if [ ! "$RESULT" -eq "0" ]; then
        echo "TEST FAILED with $RESULT"
        exit $RESULT
    fi
    cd ../..
    echo "DONE WITH TEST $TESTCASE.\n"
done

echo "ALL TESTS SUCCESSFUL"
exit 0
