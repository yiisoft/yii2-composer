<?php

namespace tests;

use Composer\Config;
use Composer\IO\IOInterface;
use yii\composer\Plugin;

/**
 *
 */
class UpgradeNotesTest extends TestCase
{
    private function getPlugin()
    {
        $plugin = new Plugin();

        $reflection = new \ReflectionObject($plugin);
        $property = $reflection->getProperty('_vendorDir');
        $property->setAccessible(true);
        $property->setValue($plugin,__DIR__ . '/data');

        return $plugin;
    }

    public function introProvider()
    {
        return [
            ['up', '2.0.12', '2.0.13', 'from version 2.0.12 to 2.0.13'],
            ['up', '2.0.12', 'dev-master', 'from version 2.0.12 to dev-master'],
            ['down', '2.0.14.1', '2.0.13', 'from version 2.0.14.1 to 2.0.13'],
            ['down', 'dev-master', '2.0.13', 'from version dev-master to 2.0.13'],
        ];
    }

    /**
     * @dataProvider introProvider
     */
    public function testPrintUpgradeIntro($direction, $from, $to, $expected)
    {
        $io = new MockIO();
        $this->invokeMethod($this->getPlugin(), 'printUpgradeIntro', [$io, [
            'direction' => $direction,
            'fromPretty' => $from,
            'toPretty' => $to,
        ]]);
        $this->assertCount(3, $io->messages);
        if ($direction === 'up') {
            $this->assertContains('upgraded', implode("\n", $io->messages));
        } else {
            $this->assertContains('downgraded', implode("\n", $io->messages));
        }
        $this->assertContains($expected, implode("\n", $io->messages));
        $this->assertContains('check the upgrade notes for possible incompatible changes', implode("\n", $io->messages));
    }

    /**
     * @dataProvider introProvider
     */
    public function testPrintUpgradeLink($direction, $from, $to)
    {
        $io = new MockIO();
        $this->invokeMethod($this->getPlugin(), 'printUpgradeLink', [$io, [
            'direction' => $direction,
            'fromPretty' => $from,
            'toPretty' => $to,
        ]]);
        $this->assertCount(1, $io->messages);
        if ($direction === 'up') {
            if ($to === 'dev-master') {
                $to = 'master';
            }
            $this->assertContains("https://github.com/yiisoft/yii2/blob/$to/framework/UPGRADE.md", implode("\n", $io->messages));
        } else {
            if ($from === 'dev-master') {
                $from = 'master';
            }
            $this->assertContains("https://github.com/yiisoft/yii2/blob/$from/framework/UPGRADE.md", implode("\n", $io->messages));
        }
    }

    public function testUpgradeNotes_brokenFile()
    {
        $notes = $this->invokeMethod($this->getPlugin(), 'findUpgradeNotes', ['yiisoft/yii3', 'dev-master']);
        $this->assertFalse($notes);
    }

    public function testUpgradeNotes_alpha()
    {
        $notes = $this->invokeMethod($this->getPlugin(), 'findUpgradeNotes', ['yiisoft/yii2', '2.0.0-alpha']);
        $this->assertEquals(<<<STRING
Upgrade from Yii 2.0.14
-----------------------

These are the upgrade notes from 2.0.14.

Upgrade from Yii 2.0.13.1
-------------------------

These are the upgrade notes from 2.0.13.1.

Upgrade from Yii 2.0.13
-----------------------

These are the upgrade notes from 2.0.13.

Upgrade from Yii 2.0.12
-----------------------

These are the upgrade notes from 2.0.12.

Upgrade from Yii 2.0.0
----------------------

These are the upgrade notes from 2.0.0.

Upgrade from Yii 2.0.0-alpha
----------------------------

These are the upgrade notes from 2.0.0-alpha.

STRING
        , implode("\n", $notes));
    }

    public function testUpgradeNotes_fromMajor()
    {
        $notes = $this->invokeMethod($this->getPlugin(), 'findUpgradeNotes', ['yiisoft/yii2', '2.0.10']);
        $this->assertEquals(<<<STRING
Upgrade from Yii 2.0.14
-----------------------

These are the upgrade notes from 2.0.14.

Upgrade from Yii 2.0.13.1
-------------------------

These are the upgrade notes from 2.0.13.1.

Upgrade from Yii 2.0.13
-----------------------

These are the upgrade notes from 2.0.13.

Upgrade from Yii 2.0.12
-----------------------

These are the upgrade notes from 2.0.12.

STRING
        , implode("\n", $notes));
    }

    public function testUpgradeNotes_fromMinorWithMinorNotes()
    {
        $notes = $this->invokeMethod($this->getPlugin(), 'findUpgradeNotes', ['yiisoft/yii2', '2.0.13.1']);
        $this->assertEquals(<<<STRING
Upgrade from Yii 2.0.14
-----------------------

These are the upgrade notes from 2.0.14.

Upgrade from Yii 2.0.13.1
-------------------------

These are the upgrade notes from 2.0.13.1.

STRING
        , implode("\n", $notes));
    }

    public function testUpgradeNotes_fromMinorWithoutMinorNotes()
    {
        $notes = $this->invokeMethod($this->getPlugin(), 'findUpgradeNotes', ['yiisoft/yii2', '2.0.12.1']);
        $this->assertEquals(<<<STRING
Upgrade from Yii 2.0.14
-----------------------

These are the upgrade notes from 2.0.14.

Upgrade from Yii 2.0.13.1
-------------------------

These are the upgrade notes from 2.0.13.1.

Upgrade from Yii 2.0.13
-----------------------

These are the upgrade notes from 2.0.13.

Upgrade from Yii 2.0.12
-----------------------

These are the upgrade notes from 2.0.12.

STRING
        , implode("\n", $notes));
    }

    public function testIsNumericVersion()
    {
        $plugin = new Plugin();

        $this->assertTrue($this->invokeMethod($plugin, 'isNumericVersion', ['2.0.10']));
        $this->assertTrue($this->invokeMethod($plugin, 'isNumericVersion', ['2.0.0']));
        $this->assertTrue($this->invokeMethod($plugin, 'isNumericVersion', ['2.0.0-alpha']));
        $this->assertTrue($this->invokeMethod($plugin, 'isNumericVersion', ['2.0.13.0']));
        $this->assertTrue($this->invokeMethod($plugin, 'isNumericVersion', ['2.0.13.1']));
        $this->assertTrue($this->invokeMethod($plugin, 'isNumericVersion', ['2.1']));
        $this->assertTrue($this->invokeMethod($plugin, 'isNumericVersion', ['2.0']));

        $this->assertFalse($this->invokeMethod($plugin, 'isNumericVersion', ['dev-master']));
        $this->assertFalse($this->invokeMethod($plugin, 'isNumericVersion', ['dev-something']));
    }
}

class MockIO
{
    public $messages = [];

    public function write($messages, $newline = true, $verbosity = 'normal')
    {
        $this->messages[] = $messages;
    }

    public $errorMessages = [];

    public function writeError($messages, $newline = true, $verbosity = self::NORMAL)
    {
        $this->errorMessages[] = $messages;
    }
}
