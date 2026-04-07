<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Model\Trait;

use Joomla\CMS\Event\AbstractEvent;
use Joomla\Database\DatabaseQuery;

/**
 * Model Behavior Trait
 * Provides F0F-like behavior methods for models
 *
 * @since  6.0.0
 */
trait BehaviorTrait
{
    /**
     * Trigger before save event
     *
     * @param   array  &$data  The data to save
     *
     * @return  bool
     */
    protected function triggerBeforeSave(&$data)
    {
        $dispatcher = $this->getDispatcher();

        $event = AbstractEvent::create('onBeforeSave', [
            'subject' => $this,
            'data'    => &$data,
        ]);

        $dispatcher->dispatch('onBeforeSave', $event);

        return !$event->isStopped();
    }

    /**
     * Trigger after save event
     *
     * @return  void
     */
    protected function triggerAfterSave()
    {
        $dispatcher = $this->getDispatcher();

        $event = AbstractEvent::create('onAfterSave', [
            'subject' => $this,
        ]);

        $dispatcher->dispatch('onAfterSave', $event);
    }

    /**
     * Trigger before delete event
     *
     * @return  bool
     */
    protected function triggerBeforeDelete()
    {
        $dispatcher = $this->getDispatcher();

        $event = AbstractEvent::create('onBeforeDelete', [
            'subject' => $this,
        ]);

        $dispatcher->dispatch('onBeforeDelete', $event);

        return !$event->isStopped();
    }

    /**
     * Trigger after delete event
     *
     * @return  void
     */
    protected function triggerAfterDelete()
    {
        $dispatcher = $this->getDispatcher();

        $event = AbstractEvent::create('onAfterDelete', [
            'subject' => $this,
        ]);

        $dispatcher->dispatch('onAfterDelete', $event);
    }

    /**
     * Trigger before build query event
     *
     * @param   DatabaseQuery  &$query  The query being built
     *
     * @return  void
     */
    protected function triggerBeforeBuildQuery(&$query)
    {
        $dispatcher = $this->getDispatcher();

        $event = AbstractEvent::create('onBeforeBuildQuery', [
            'subject' => $this,
            'query'   => &$query,
        ]);

        $dispatcher->dispatch('onBeforeBuildQuery', $event);
    }

    /**
     * Trigger after build query event
     *
     * @param   DatabaseQuery  &$query  The query being built
     *
     * @return  void
     */
    protected function triggerAfterBuildQuery(&$query)
    {
        $dispatcher = $this->getDispatcher();

        $event = AbstractEvent::create('onAfterBuildQuery', [
            'subject' => $this,
            'query'   => &$query,
        ]);

        $dispatcher->dispatch('onAfterBuildQuery', $event);
    }

    /**
     * Trigger after get item event
     *
     * @param   mixed  &$item  The item loaded
     *
     * @return  void
     */
    protected function triggerAfterGetItem(&$item)
    {
        $dispatcher = $this->getDispatcher();

        $event = AbstractEvent::create('onAfterGetItem', [
            'subject' => $this,
            'item'    => &$item,
        ]);

        $dispatcher->dispatch('onAfterGetItem', $event);
    }

    // Add other trigger methods as needed...
}
