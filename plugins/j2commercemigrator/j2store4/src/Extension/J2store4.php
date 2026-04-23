<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commercemigrator_j2store4
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2commercemigrator\J2store4\Extension;

use J2Commerce\Plugin\J2commercemigrator\J2store4\Adapter\J2store4Adapter;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

class J2store4 extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onJ2CommerceMigratorRegister' => 'registerAdapter',
        ];
    }

    public function registerAdapter(Event $event): void
    {
        $result   = $event->getArgument('result', []);
        $result[] = new J2store4Adapter();
        $event->setArgument('result', $result);
    }
}

