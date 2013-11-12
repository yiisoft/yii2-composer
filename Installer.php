<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Script\CommandEvent;
use Composer\Util\Filesystem;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Installer extends LibraryInstaller
{
	const EXTRA_BOOTSTRAP = 'bootstrap';
	const EXTRA_WRITABLE = 'writable';
	const EXTRA_EXECUTABLE = 'executable';
    const EXTRA_COMMANDS = 'commands';
    const EXTRA_CONFIG_FILE = 'config_file';
    const EXTRA_YII2_PATH = 'yii2_path';

	const EXTENSION_FILE = 'yiisoft/extensions.php';

	/**
	 * @inheritdoc
	 */
	public function supports($packageType)
	{
		return $packageType === 'yii2-extension';
	}

	/**
	 * @inheritdoc
	 */
	public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
	{
		parent::install($repo, $package);
		$this->addPackage($package);
	}

	/**
	 * @inheritdoc
	 */
	public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
	{
		parent::update($repo, $initial, $target);
		$this->removePackage($initial);
		$this->addPackage($target);
	}

	/**
	 * @inheritdoc
	 */
	public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
	{
		parent::uninstall($repo, $package);
		$this->removePackage($package);
	}

	protected function addPackage(PackageInterface $package)
	{
		$extension = [
			'name' => $package->getName(),
			'version' => $package->getVersion(),
		];

		$alias = $this->generateDefaultAlias($package);
		if (!empty($alias)) {
			$extension['alias'] = $alias;
		}
		$extra = $package->getExtra();
		if (isset($extra[self::EXTRA_BOOTSTRAP]) && is_string($extra[self::EXTRA_BOOTSTRAP])) {
			$extension['bootstrap'] = $extra[self::EXTRA_BOOTSTRAP];
		}

		$extensions = $this->loadExtensions();
		$extensions[$package->getName()] = $extension;
		$this->saveExtensions($extensions);
	}

	protected function generateDefaultAlias(PackageInterface $package)
	{
		$autoload = $package->getAutoload();
		if (empty($autoload['psr-0'])) {
			return false;
		}
		$fs = new Filesystem;
		$vendorDir = $fs->normalizePath($this->vendorDir);
		$aliases = [];
		foreach ($autoload['psr-0'] as $name => $path) {
			$name = str_replace('\\', '/', trim($name, '\\'));
			if (!$fs->isAbsolutePath($path)) {
				$path = $this->vendorDir . '/' . $package->getName() . '/' . $path;
			}
			$path = $fs->normalizePath($path);
			if (strpos($path . '/', $vendorDir . '/') === 0) {
				$aliases["@$name"] = '<vendor-dir>' . substr($path, strlen($vendorDir)) . '/' . $name;
			} else {
				$aliases["@$name"] = $path . '/' . $name;
			}
		}
		return $aliases;
	}

	protected function removePackage(PackageInterface $package)
	{
		$packages = $this->loadExtensions();
		unset($packages[$package->getName()]);
		$this->saveExtensions($packages);
	}

	protected function loadExtensions()
	{
		$file = $this->vendorDir . '/' . self::EXTENSION_FILE;
		if (!is_file($file)) {
			return [];
		}
		$extensions = require($file);

		$vendorDir = str_replace('\\', '/', $this->vendorDir);
		$n = strlen($vendorDir);

		foreach ($extensions as &$extension) {
			if (isset($extension['alias'])) {
				foreach ($extension['alias'] as $alias => $path) {
					$path = str_replace('\\', '/', $path);
					if (strpos($path . '/', $vendorDir . '/') === 0) {
						$extension['alias'][$alias] = '<vendor-dir>' . substr($path, $n);
					}
				}
			}
		}

		return $extensions;
	}

	protected function saveExtensions(array $extensions)
	{
		$file = $this->vendorDir . '/' . self::EXTENSION_FILE;
		$array = str_replace("'<vendor-dir>", '$vendorDir . \'', var_export($extensions, true));
		file_put_contents($file, "<?php\n\n\$vendorDir = dirname(__DIR__);\n\nreturn $array;\n");
	}


	/**
	 * Sets the correct permission for the files and directories listed in the extra section.
	 * @param CommandEvent $event
	 */
	public static function setPermission($event)
	{
		$options = array_merge([
			self::EXTRA_WRITABLE => [],
			self::EXTRA_EXECUTABLE => [],
		], $event->getComposer()->getPackage()->getExtra());

		foreach ((array)$options[self::EXTRA_WRITABLE] as $path) {
			echo "Setting writable: $path ...";
			if (is_dir($path)) {
				chmod($path, 0777);
				echo "done\n";
			} else {
				echo "The directory was not found: " . getcwd() . DIRECTORY_SEPARATOR . $path;
				return;
			}
		}

		foreach ((array)$options[self::EXTRA_EXECUTABLE] as $path) {
			echo "Setting executable: $path ...";
			if (is_file($path)) {
				chmod($path, 0755);
				echo "done\n";
			} else {
				echo "\n\tThe file was not found: " . getcwd() . DIRECTORY_SEPARATOR . $path . "\n";
				return;
			}
		}
	}


    /**
     * Console command parsing.
     * @param string $commandLine
     * @return array
     */
    public static function  parseCommandLine($commandLine){
        $params = preg_split('/(--\w+=".*?"|".*?")/', $commandLine, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $realParams = array();
        foreach($params as $param)
        {
            $param = trim($param);
            if ($param == '')
                continue;

            if (substr($param,0,2) === '--')
                $realParams = array_merge($realParams, array(trim($param)));
            elseif (strpos($param, '"') === 0)
                $realParams = array_merge($realParams, array(trim($param, '"')));
            else
                $realParams = array_merge($realParams, explode(' ', $param));
        }

        return $realParams;
    }

    /**
     * Running console commands.
     * Usage
     * composer.json
     {
            "scripts": {
                "post-update-cmd": "yii\\composer\\Installer::runCommands"
            },
            "extra": {
                "yii2_path": "vendor/yiisoft/yii2/yii",
                "config_file": "config/console.php",
                "commands": [
                    "hello \"test composer mess\"",
                    "migrate"
                ]
            }
     }
     * @param CommandEvent $event
     */
    public static function runCommands($event){
        $commands = ['migrate'];

        $extra = $event->getComposer()->getPackage()->getExtra();

        if(isset($extra[self::EXTRA_COMMANDS])){
            $commands = $extra[self::EXTRA_COMMANDS];
        }

        defined('YII_DEBUG') or define('YII_DEBUG', true);

        $vendorDir = rtrim($event->getComposer()->getConfig()->get('vendor-dir'), '/');

        $configFile = $vendorDir . '/../config/console.php';
        if(isset($extra[self::EXTRA_CONFIG_FILE]))
            $configFile = $extra[self::EXTRA_CONFIG_FILE];

        $configs = include $configFile;

        $yiiPath = $vendorDir . '/yiisoft/yii2/yii/Yii.php';

        if(isset($extra[self::EXTRA_YII2_PATH])){
            $yiiPath = rtrim($extra[self::EXTRA_YII2_PATH], '/')."/Yii.php";
        }

        require_once($yiiPath);

        $application = new \yii\console\Application($configs);

        foreach($commands as $command){
            $application->getRequest()->setParams(self::parseCommandLine($command));
            $application->run();
        }
    }
}
