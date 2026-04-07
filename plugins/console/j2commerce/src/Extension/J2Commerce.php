<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.Console.J2Commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Plugin\Console\J2Commerce\Extension;

\defined('_JEXEC') || die;

use J2Commerce\Plugin\Console\J2Commerce\Command\CommandFactoryInterface;
use Joomla\Application\ApplicationEvents;
use Joomla\Application\Event\ApplicationEvent;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\Folder;

class J2Commerce extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    protected $commandFactory;

    public function setCLICommandFactory(CommandFactoryInterface $factory)
    {
        $this->commandFactory = $factory;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ApplicationEvents::BEFORE_EXECUTE => 'registerCLICommands',
        ];
    }

    public function registerCLICommands(ApplicationEvent $event)
    {
        /** @var ConsoleApplication $app */
        $app = $event->getApplication();

        // Load the component language files
        $lang = $app->getLanguage();
        $lang->load('com_j2commerce', JPATH_ADMINISTRATOR);
        $lang->load('com_j2commerce', JPATH_SITE);

        // Only register CLI commands if J2Commerce is installed and enabled
        try {
            if (!ComponentHelper::isEnabled('com_j2commerce')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        // Try to find all commands in the CliCommands directory of the component
        try {
            $files = Folder::files(JPATH_ADMINISTRATOR . '/components/com_j2commerce/src/CliCommands', '.php');
        } catch (\Exception $e) {
            $files = [];
        }
        $files = \is_array($files) ? $files : [];

        foreach ($files as $file) {
            try {
                $app->addCommand(
                    $this->commandFactory->getCLICommand(basename($file, '.php'))
                );
            } catch (\Throwable $e) {
            }
        }
    }
}
