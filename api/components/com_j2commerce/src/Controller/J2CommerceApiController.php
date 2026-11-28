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

use J2Commerce\Component\J2commerce\Administrator\Exception\UserFacingException;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Controller\Exception\Save;

/**
 * Base API controller for J2Commerce.
 *
 * Forces 'Administrator' prefix for model resolution since J2Commerce
 * API controllers reuse admin models (no API-specific models exist).
 *
 * Also carries the authorisation every J2Commerce API route needs. Before Joomla 6.2.1
 * ApiDispatcher never called checkAccess(), and ApiController::displayList()/displayItem()
 * authorise nothing at all. Without the gates below, any principal a merchant has granted
 * core.login.api reads every order, customer and address regardless of what
 * j2commerce.vieworders says.
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
     * Capability a read requires: a j2commerce.* action, a core.* one, or core.api where the
     * resource needs nothing beyond API access itself.
     *
     * Empty denies. A controller that declares nothing must fail closed rather than inherit
     * an unauthorised displayList() from ApiController.
     */
    protected string $readAction = '';

    /** Capability a write requires, checked with the core verb for the method. Empty denies. */
    protected string $writeAction = '';

    /** Largest page[limit] a list route will serve, whatever the caller asks for. */
    private const MAX_PAGE_SIZE = 100;

    public function getModel($name = '', $prefix = '', $config = [])
    {
        if (!$prefix) {
            $prefix = 'Administrator';
        }

        // ApiController::save() is the one path that resolves the model without ignore_request;
        // displayList(), displayItem() and delete() all pass it. A model whose populateState()
        // seeds its own '.id' from the request `id` therefore hands AdminModel::save() an
        // existing key on the create route, where the payload key is null by construction. Only
        // the create task needs this — edit() carries the record key in the payload already.
        if ($this->input->get('task') === 'add' && !\array_key_exists('ignore_request', $config)) {
            $config['ignore_request'] = true;
        }

        return parent::getModel($name, $prefix, $config);
    }

    public function displayList()
    {
        $this->assertAllowed($this->readAction);
        $this->pinResource();
        $this->capPageSize();

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

        // A model's refusal would otherwise reach the fallback handler: a bare 500 without its message.
        try {
            return parent::delete($id);
        } catch (UserFacingException $e) {
            throw new Save($e->getMessage(), 409, $e);
        }
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
     * Hold page[limit] to a size this component chooses.
     *
     * ApiController takes the limit straight from the request and applies no ceiling, and a
     * list response is serialised whole rather than streamed, so the caller would otherwise
     * decide how much of a table is assembled in memory at once. Clamping before
     * parent::displayList() keeps the model state and the model itself on the same value, so
     * the pagination links advance by the size actually served.
     */
    private function capPageSize(): void
    {
        $page = $this->input->get('page', [], 'array');

        if (!isset($page['limit'])) {
            return;
        }

        $page['limit'] = min(max((int) $page['limit'], 1), self::MAX_PAGE_SIZE);
        $this->input->set('page', $page);
    }

    /**
     * Throw unless the caller holds the API floor plus $action, and $coreVerb when one is given.
     *
     * The floor is a precondition rather than one capability among several: the ACL seed
     * materialises the j2commerce.* actions as explicit allows on the component asset, and
     * those allows stand on their own, so without it revoking API access would leave every
     * resource open to the same group. Joomla 6.2.1 ApiDispatcher checks core.api as well;
     * repeating it here keeps the gate whichever dispatcher runs.
     *
     * j2commerce.* actions go through canAccess(), which honours an explicit deny and keeps
     * the pre-seed core.manage fallback, so this reads the same way the admin views do.
     */
    protected function assertAllowed(string $action, string $coreVerb = ''): void
    {
        $user = $this->app->getIdentity();

        if (!$user || $user->guest || $action === '' || !$user->authorise(self::apiAccessAction(), 'com_j2commerce')) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN', 403);
        }

        $allowed = match (true) {
            $action === 'core.api'                  => true,
            str_starts_with($action, 'j2commerce.') => J2CommerceHelper::canAccess($action),
            default                                 => $user->authorise($action, 'com_j2commerce'),
        };

        if (!$allowed || ($coreVerb !== '' && !$user->authorise($coreVerb, 'com_j2commerce'))) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN', 403);
        }
    }

    /**
     * The action that grants API access to this component.
     *
     * Joomla 6.2.1 replaced core.manage with core.api for Web Services and seeds core.api only
     * from that release on; below it no group holds core.api, so core.manage stays the floor.
     * The -dev suffix also counts 6.2.1 pre-releases, which already carry the change.
     * DELETE WHEN: script.j2commerce.php $minimumJoomla is 6.2.1 or newer.
     */
    public static function apiAccessAction(): string
    {
        return version_compare(JVERSION, '6.2.1-dev', '>=') ? 'core.api' : 'core.manage';
    }
}
