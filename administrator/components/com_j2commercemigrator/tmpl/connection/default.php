<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="j2cm-connection">

    <?php if (!$this->pdoAvailable) : ?>
        <div class="alert alert-warning" role="alert">
            <span class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_PDO_UNAVAILABLE'); ?>
        </div>
    <?php endif; ?>

    <?php if ($this->connected) : ?>
        <div class="alert alert-success" role="alert">
            <span class="fa-solid fa-circle-check me-2" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_ALREADY_CONNECTED'); ?>
            <?php if (!empty($this->connectionStatus['database'])) : ?>
                <strong><?php echo $this->escape($this->connectionStatus['database']); ?></strong>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_FORM_HEADING'); ?></h2>
        </div>
        <div class="card-body">

            <form id="j2cm-connection-form"
                  method="post"
                  action="<?php echo Route::_('index.php?option=com_j2commercemigrator&task=connection.verify'); ?>"
                  novalidate>

                <?php echo HTMLHelper::_('form.token'); ?>
                <input type="hidden" name="adapter" value="<?php echo $this->escape($this->adapterKey); ?>">

                <fieldset class="mb-4">
                    <legend class="fw-semibold fs-6 mb-3"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_MODE_HEADING'); ?></legend>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="radio"
                                   name="mode"
                                   id="j2cm-mode-a"
                                   value="A"
                                   data-j2cm-mode="A"
                                   checked>
                            <label class="form-check-label" for="j2cm-mode-a">
                                <span class="fw-semibold"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_MODE_A_LABEL'); ?></span>
                                <span class="d-block text-muted small"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_MODE_A_DESC'); ?></span>
                            </label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input"
                                   type="radio"
                                   name="mode"
                                   id="j2cm-mode-b"
                                   value="B"
                                   data-j2cm-mode="B">
                            <label class="form-check-label" for="j2cm-mode-b">
                                <span class="fw-semibold"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_MODE_B_LABEL'); ?></span>
                                <span class="d-block text-muted small"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_MODE_B_DESC'); ?></span>
                            </label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input"
                                   type="radio"
                                   name="mode"
                                   id="j2cm-mode-c"
                                   value="C"
                                   data-j2cm-mode="C">
                            <label class="form-check-label" for="j2cm-mode-c">
                                <span class="fw-semibold"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_MODE_C_LABEL'); ?></span>
                                <span class="d-block text-muted small"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_MODE_C_DESC'); ?></span>
                            </label>
                        </div>
                    </div>
                </fieldset>

                <div id="j2cm-remote-fields" class="d-none">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="j2cm-host" class="form-label"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_FIELD_HOST'); ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="j2cm-host"
                                   name="host"
                                   autocomplete="off"
                                   value="<?php echo $this->escape($this->connectionStatus['host'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="j2cm-port" class="form-label"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_FIELD_PORT'); ?></label>
                            <input type="number"
                                   class="form-control"
                                   id="j2cm-port"
                                   name="port"
                                   min="1"
                                   max="65535"
                                   value="<?php echo (int) ($this->connectionStatus['port'] ?? 3306) ?: 3306; ?>">
                        </div>
                    </div>
                </div>

                <div id="j2cm-db-fields">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="j2cm-database" class="form-label"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_FIELD_DATABASE'); ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="j2cm-database"
                                   name="database"
                                   autocomplete="off"
                                   value="<?php echo $this->escape($this->connectionStatus['database'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="j2cm-prefix" class="form-label"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_FIELD_PREFIX'); ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="j2cm-prefix"
                                   name="prefix"
                                   placeholder="jos_"
                                   pattern="[a-zA-Z0-9_]{1,32}"
                                   value="<?php echo $this->escape($this->connectionStatus['prefix'] ?? ''); ?>">
                            <div class="form-text"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_FIELD_PREFIX_HELP'); ?></div>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="j2cm-username" class="form-label"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_FIELD_USERNAME'); ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="j2cm-username"
                                   name="username"
                                   autocomplete="off"
                                   value="<?php echo $this->escape($this->connectionStatus['username'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="j2cm-password" class="form-label"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_FIELD_PASSWORD'); ?></label>
                            <input type="password"
                                   class="form-control"
                                   id="j2cm-password"
                                   name="password"
                                   autocomplete="new-password">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="j2cm-ssl"
                                   name="ssl"
                                   value="1"
                                   data-j2cm-ssl>
                            <label class="form-check-label" for="j2cm-ssl">
                                <?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_FIELD_SSL'); ?>
                            </label>
                        </div>
                    </div>

                    <div id="j2cm-ssl-ca-field" class="mb-3 d-none">
                        <label for="j2cm-ssl-ca" class="form-label"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_FIELD_SSL_CA'); ?></label>
                        <input type="text"
                               class="form-control"
                               id="j2cm-ssl-ca"
                               name="sslCa"
                               placeholder="/etc/mysql/certs/ca-cert.pem">
                        <div class="form-text"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_FIELD_SSL_CA_HELP'); ?></div>
                    </div>
                </div>

                <div id="j2cm-connection-result" class="mb-3" role="status" aria-live="polite"></div>

                <div class="d-flex gap-2">
                    <button type="button"
                            class="btn btn-purple"
                            data-j2cm-action="verifyConnection">
                        <span class="fa-solid fa-plug me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONNECTION_BTN_VERIFY'); ?>
                    </button>
                    <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator'); ?>"
                       class="btn btn-outline-secondary">
                        <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DASHBOARD'); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
