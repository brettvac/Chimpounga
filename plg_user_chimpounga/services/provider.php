<?php
/**
 * @package     Joomla.Site
 * @subpackage  plg_user_chimpounga
 *
 * @version     1.2
 * @license     GNU General Public License version 2
 */

\defined('_JEXEC') or die; //No direct access
 
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

use Naftee\Plugin\User\Chimpounga\Extension\Chimpounga;

    return new class() implements ServiceProviderInterface
    {
        public function register(Container $container)
        {
            $container->set(
                PluginInterface::class,
                function (Container $container) {
    
                    $config = (array) PluginHelper::getPlugin('user', 'chimpounga');
                    $subject = $container->get(DispatcherInterface::class);
                    $app = Factory::getApplication();
                    
                    $plugin = new Chimpounga($subject, $config);
                    $plugin->setApplication($app);
    
                    return $plugin;
                }
            );
        }
    };