#!/bin/sh -e


php ../../make_composer_json.php composer.json << EOF
{
    "require": {
        "yiisoft/yii2": "~2.0.16"
    },
    "repositories": [
        {
            "type": "composer",
            "url": "https://asset-packagist.org"
        }
    ]
}
EOF

$COMPOSER_BINARY install

test -f vendor/autoload.php || (echo "vendor/autoload.php does not exist!"; exit 1)
test -f vendor/yiisoft/extensions.php || (echo "vendor/yiisoft/extensions.php does not exist!"; exit 1)
test -d vendor/yiisoft/yii2 || (echo "vendor/yiisoft/yii2 does not exist!"; exit 1)
