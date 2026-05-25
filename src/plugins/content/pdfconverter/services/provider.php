<?php

/**
 * @package     PDF Converter
 *
 * @copyright   (C) 2007 - 2022 Flygcert FZE. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Flygcert\Plugin\Content\PDFConverter\Extension\PDFConverter;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param  Container  $container  The DI container.
     *
     * @return void
     *
     * @since  4.0.0
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $config = (array) PluginHelper::getPlugin('content', 'pdfconverter');

                $plugin = new PDFConverter($config);
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
