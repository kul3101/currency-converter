<?php
/**
 * Bootstrap for running the unit tests outside a Magento installation (used by CI).
 *
 * Inside a Magento root, dev/tests/unit generates classes such as CurlFactory on
 * demand. This file registers the same generators shipped in magento/framework, so
 * the tests behave the same against a plain Composer install.
 */
declare(strict_types=1);

use Magento\Framework\Code\Generator\Io;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\Renderer\Placeholder;
use Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesInterfaceGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader;
use Magento\Framework\TestFramework\Unit\Autoloader\ProxyGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

$generatedDir = sys_get_temp_dir() . '/sidelah-currency-converter-tests/generated/code';

$generatedClassesAutoloader = new GeneratedClassesAutoloader(
    [
        new ExtensionAttributesGenerator(),
        new ExtensionAttributesInterfaceGenerator(),
        new FactoryGenerator(),
        new ProxyGenerator(),
    ],
    new Io(new File(), $generatedDir)
);
spl_autoload_register([$generatedClassesAutoloader, 'load']);

Phrase::setRenderer(new Placeholder());
