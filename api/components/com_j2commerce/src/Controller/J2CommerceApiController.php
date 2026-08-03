<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\MVC\Controller\ApiController;

/**
 * Base API controller for J2Commerce.
 *
 * Forces 'Administrator' prefix for model resolution since J2Commerce
 * API controllers reuse admin models (no API-specific models exist).
 *
 * Also carries the authorisation every J2Commerce API route needs. ApiDispatcher overrides
 * dispatch() without calling checkAccess(), so there is no core.manage floor the way there is
 * on the admin surface, and ApiController::displayList()/displayItem() authorise nothing at
 * all. Without the gates below, any principal a merchant has granted core.login.api reads
 * every order, customer and address regardless of what j2commerce.vieworders says.
 *
 * Each subclass declares the capability its resource needs. The values mirror the admin
 * views over the same data — Orders and Customers gate on j2commerce.vieworders, Products on
 * j2commerce.viewproducts, and so on — so a merchant's existing permission setup means the
 * same thing on both surfaces.
 *
 * @since  6.0.15
 */
abstract class J2CommerceApiController extends ApiController
{
    /**
     * Capability a read requires: a j2commerce.* action, or a core.* one where the admin twin
     * has no custom action and relies on the dispatcher's core.manage floor.
     *
     * Empty denies. A controller that declares nothing must fail closed rather than inherit
     * an unauthorised displayList() from ApiController.
     */
    protected string $readAction = '';

    /** Capability a write requires, checked with the core verb for the method. Empty denies. */
    protected string $writeAction = '';

    public function getModel($name = '', $prefix = '', $config = [])
    {
        if (!$prefix) {
            $prefix = 'Administrator';
        }

        return parent::getModel($name, $prefix, $config);
    }

    public function displayList()
    {
        $this->assertAllowed($this->readAction);
        $this->pinResource();

        return parent::displayList();
    }

    public function displayItem($id = null)
    {
        $this->assertAllowed($this->readAction);
        $this->pinResource();

        return parent::displayItem($id);
    }

    public function add()
    {
        // Also the read action: parent::add() renders the new record through displayItem(),
        // so checking it only there would 403 after the row had already been written.
        $this->assertAllowed($this->readAction);
        $this->assertAllowed($this->writeAction, 'core.create');

        return parent::add();
    }

    public function edit()
    {
        $this->assertAllowed($this->readAction);
        $this->assertAllowed($this->writeAction, 'core.edit');

        return parent::edit();
    }

    public function delete($id = null)
    {
        $this->assertAllowed($this->writeAction, 'core.delete');
        $this->pinResource();

        return parent::delete($id);
    }

    /**
     * Bind the request to this controller's own resource.
     *
     * ApiController resolves the model and the view from request input — displayList() reads
     * `view` and `model`, displayItem() and delete() read `model` — and ApiApplication::route()
     * sets only format, controller, task and the route's own vars, never those two. Left alone,
     * `?model=orders&view=orders` on any endpoint whose gate the caller does pass returns the
     * orders model through it, which collapses every capability declared here to the weakest
     * one in the set. Clearing the values makes ApiController fall back to its own
     * $contentType / $default_view, which is the resource the route actually named.
     */
    private function pinResource(): void
    {
        $this->input->set('model', null);
        $this->input->set('view', null);
    }

    /**
     * Throw unless the caller holds core.manage plus $action, and $coreVerb when one is given.
     *
     * core.manage is a precondition rather than one capability among several: the ACL seed
     * materialises the j2commerce.* actions as explicit allows on the component asset for
     * every group that held core.manage when it ran, and from then on those allows stand on
     * their own. ComponentDispatcher::checkAccess() applies core.manage for the administrator
     * client only, and ApiDispatcher::dispatch() never calls checkAccess() at all, so without
     * the floor below a revoked core.manage closes the admin screens while leaving the whole
     * API surface open to the same group.
     *
     * j2commerce.* actions go through canAccess(), which honours an explicit deny and keeps
     * the pre-seed core.manage fallback, so this reads the same way the admin views do.
     */
    protected function assertAllowed(string $action, string $coreVerb = ''): void
    {
        $user = $this->app->getIdentity();

        if (!$user || $user->guest || $action === '' || !$user->authorise('core.manage', 'com_j2commerce')) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN', 403);
        }

        $allowed = str_starts_with($action, 'j2commerce.')
            ? J2CommerceHelper::canAccess($action)
            : $user->authorise($action, 'com_j2commerce');

        if (!$allowed || ($coreVerb !== '' && !$user->authorise($coreVerb, 'com_j2commerce'))) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN', 403);
        }
    }
}
