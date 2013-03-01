<?php

namespace Namespacer;

use Zend\Mvc\ModuleRouteListener;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\Console\Adapter\AdapterInterface as ConsoleAdapterInterface;

class Module implements ConsoleUsageProviderInterface, AutoloaderProviderInterface, ConfigProviderInterface
{
    const VERSION = '0.1';
    const NAME    = 'Namespacer';

    protected $config;

    public function onBootstrap($e)
    {
    }

    public function getConfig()
    {
        return $this->config = include __DIR__ . '/../../config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__,
                ),
            ),
        );
    }

    public function getConsoleBanner(ConsoleAdapterInterface $console)
    {
        return self::NAME . ' ver. ' . self::VERSION;
    }

    public function getConsoleUsage(ConsoleAdapterInterface $console)
    {
        if (!empty($this->config->disableUsage)){
            return null; // usage information has been disabled
        }

        return array(
            'Basic information:',
            'create map <file>'       => 'create a map in file <file>',
            'version | --version'         => 'display current Zend Framework version',
        );
    }
}
