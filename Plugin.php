<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script;
use Composer\Script\ScriptEvents;

/**
 * Plugin is the composer plugin that registers the Yii composer installer.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var array noted package updates.
     */
    private $_packageUpdates = [];

    /**
     * @inheritdoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new Installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
        $file = rtrim($composer->getConfig()->get('vendor-dir'), '/') . '/yiisoft/extensions.php';
        if (!is_file($file)) {
            @mkdir(dirname($file), 0777, true);
            file_put_contents($file, "<?php\n\nreturn [];\n");
        }
    }

    /**
     * @inheritdoc
     * @return array The event names to listen to.
     */
    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::POST_PACKAGE_UPDATE => 'checkPackageUpdates',
            ScriptEvents::POST_UPDATE_CMD => 'showUpgradeNotes',
        ];
    }


    /**
     * Listen to POST_PACKAGE_UPDATE event and take note of the package updates.
     * @param PackageEvent $event
     */
    public function checkPackageUpdates(PackageEvent $event)
    {
        $operation = $event->getOperation();
        if ($operation instanceof UpdateOperation) {
            $this->_packageUpdates[$operation->getInitialPackage()->getName()] = [
                'from' => $operation->getInitialPackage()->getVersion(),
                'fromPretty' => $operation->getInitialPackage()->getPrettyVersion(),
                'to' => $operation->getTargetPackage()->getVersion(),
                'toPretty' => $operation->getTargetPackage()->getPrettyVersion(),
                'direction' => $event->getPolicy()->versionCompare(
                    $operation->getInitialPackage(),
                    $operation->getTargetPackage(),
                    '<'
                ) ? 'up' : 'down',
            ];
        }
    }

    /**
     * Listen to POST_UPDATE_CMD event to display information about upgrade notes if appropriate.
     * @param Script\Event $event
     */
    public function showUpgradeNotes(Script\Event $event)
    {
        if (isset($this->_packageUpdates['yiisoft/yii2'])) {

            $package = $this->_packageUpdates['yiisoft/yii2'];

            $io = $event->getIO();

            $io->write("\n  Seems you have "
                . ($package['direction'] === 'up' ? 'upgraded' : 'downgraded')
                . ' Yii Framework from version '
                . $package['fromPretty'] . ' to ' . $package['toPretty'] . '.'
            );
            $io->write("\n  Please check the upgrade notes for possible incompatible changes");
            $io->write('  and adjust your application code accordingly.');
            $maxVersion = $package['direction'] === 'up' ? $package['toPretty'] : $package['fromPretty'];
            $io->write("\n  Here is a link: https://github.com/yiisoft/yii2/blob/$maxVersion/framework/UPGRADE.md\n");
        }
    }
}
