<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects
?>
	<div class="uk-grid" uk-grid>
		<div class="uk-width-1-1">
				<?php
			$hasShortDesc = $this->params->get('item_show_sdesc') && !empty(trim(strip_tags($this->item->product_short_desc ?? '')));
			$hasLongDesc  = $this->params->get('item_show_ldesc') && !empty(trim(strip_tags($this->item->product_long_desc ?? '')));
			?>
			<?php if($hasShortDesc || $hasLongDesc):?>
				<div class="product-description">
					<?php echo $this->loadTemplate('sdesc'); ?>
					<?php echo $this->loadTemplate('ldesc'); ?>
				</div>
				<?php endif;?>

				<?php if($this->params->get('item_show_product_specification')):?>
					<div class="product-specs">
						<?php echo $this->loadTemplate('specs'); ?>
					</div>
				<?php endif;?>
		</div>
	</div>
