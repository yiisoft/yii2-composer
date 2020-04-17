<?php

// create a virtual package to make composer load the plugin from the current directory

error_reporting(-1);
ini_set('display_errors', 1);

if (empty($argv[1])) {
    echo "argument 1 must be composer.json file name!\n";
    exit(1);
}
$targetFile = $argv[1];

$json = stream_get_contents(STDIN);
if (empty($json)) {
    echo "expected composer.json content on STDIN!\n";
    exit(1);
}

$packageJson = file_get_contents(__DIR__ . '/../../composer.json');
$packageComposerJson = json_decode($packageJson, true);
check_json_error($packageJson);
$PLUGIN_SOURCE = dirname(dirname(__DIR__)) . '/package.zip';
$packageComposerJson['version'] = 'dev-test';
$packageComposerJson['dist'] = array(
    'url' => "file://$PLUGIN_SOURCE",
    'type' => 'zip',
);

$rootComposerJson = json_decode($json, true);
check_json_error($json);
$rootComposerJson['require']['yiisoft/yii2-composer'] = 'dev-test as 2.0.x-dev';
$rootComposerJson['repositories'][] = array(
    'type' => 'package',
    'package' => $packageComposerJson,
);

file_put_contents($targetFile, json_encode($rootComposerJson, JSON_PRETTY_PRINT));

function check_json_error($json)
{
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Failed to parse JSON: " . json_last_error_msg() . "\n";
        echo "$json\n";
        exit(1);
    }
}
