<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// Extract display data
$items  = $displayData['items'] ?? [];
$active = $displayData['active'] ?? '';

J2CommerceHelper::strapper()->addCSS();

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('bootstrap.dropdown');
$pluginHelper = J2CommerceHelper::plugin();
$versionHelper = J2CommerceHelper::version();

$currentUrl = Uri::getInstance()->toString();
$encodedReturn = base64_encode($currentUrl);

$showWelcome = (int) J2CommerceHelper::config()->get('config_show_welcome_message', 1);

if ($showWelcome) {
    // Active frontend session counts (last 30 min) — fetched once, passed to greeting widget
    $db        = Factory::getContainer()->get(DatabaseInterface::class);
    $threshold = time() - 1800;
    $query     = $db->getQuery(true)
        ->select('SUM(CASE WHEN ' . $db->quoteName('guest') . ' = 0 THEN 1 ELSE 0 END) AS registered')
        ->select('SUM(CASE WHEN ' . $db->quoteName('guest') . ' = 1 THEN 1 ELSE 0 END) AS guests')
        ->from($db->quoteName('#__session'))
        ->where($db->quoteName('client_id') . ' = 0')
        ->where($db->quoteName('time') . ' >= :threshold')
        ->bind(':threshold', $threshold, ParameterType::INTEGER);
    $db->setQuery($query);
    $sessions = $db->loadObject();

    $greetingData = [
        'registered' => (int) ($sessions->registered ?? 0),
        'guests'     => (int) ($sessions->guests ?? 0),
    ];
}

?>
<div class="j2commerce-navbar-container">
    <?php if ($showWelcome) : ?>
        <?php echo LayoutHelper::render('widget.greeting', $greetingData, JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts'); ?>
    <?php endif; ?>
    <nav class="navbar navbar-expand-lg bg-primary border-bottom mb-3" role="navigation" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" id="j2c-nav-brand" href="<?php echo Route::_('index.php?option=com_j2commerce'); ?>">
                <span class="icon-cart me-1"></span> <?php echo Text::_('COM_J2COMMERCE'); ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#j2commerceNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="j2commerceNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 w-100 justify-content-xl-end">
                    <?php foreach ($items as $item) : ?>
                        <?php if (isset($item['children']) && !empty($item['children'])) : ?>
                            <!-- Dropdown Menu -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo $active == $item['view'] ? 'active' : ''; ?>"
                                   href="#"
                                   id="dropdown-<?php echo $item['view']; ?>"
                                   role="button"
                                   data-bs-toggle="dropdown"
                                   aria-expanded="false">
                                    <?php if (!empty($item['icon'])) : ?>
                                        <span class="<?php echo $item['icon']; ?> fa-fw me-1"></span>
                                    <?php endif; ?>
                                    <?php echo Text::_($item['title']); ?>
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="dropdown-<?php echo $item['view']; ?>">
                                    <?php foreach ($item['children'] as $child) : ?>
                                        <?php if ($child === 'divider') : ?>
                                            <li><hr class="dropdown-divider"></li>
                                        <?php elseif (isset($child['header']) && $child['header']) : ?>
                                            <li><h6 class="dropdown-header"><?php echo Text::_($child['title']); ?></h6></li>
                                        <?php else : ?>
                                            <li>
                                                <a class="dropdown-item <?php echo $active == ($child['view'] ?? '') ? 'active' : ''; ?>"
                                                   id="j2c-nav-<?php echo $child['view'] ?? ''; ?>"
                                                   href="<?php echo Route::_($child['link']); ?>">
                                                    <?php if (!empty($child['icon'])) : ?>
                                                        <span class="<?php echo $child['icon']; ?> fa-fw me-1"></span>
                                                    <?php endif; ?>
                                                    <?php echo Text::_($child['title']); ?>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php else : ?>
                            <!-- Regular Menu Item -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active == $item['view'] ? 'active' : ''; ?>"
                                   id="j2c-nav-<?php echo $item['view']; ?>"
                                   href="<?php echo Route::_($item['link']); ?>">
                                    <?php if (!empty($item['icon'])) : ?>
                                        <span class="<?php echo $item['icon']; ?> fa-fw me-1"></span>
                                    <?php endif; ?>
                                    <?php echo Text::_($item['title']); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>

            </div>
        </div>
    </nav>
</div>
