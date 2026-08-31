<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\RadioField;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Helpers\User;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

$style = '.autocomplete-list{background: var(--form-control-bg);max-height: 200px;overflow-y: auto;width: 100%;}.autocomplete-list.autocomplete-active{border: var(--form-control-border);}.autocomplete-item{padding: 8px;cursor: pointer;font-size: .8rem;}.autocomplete-item:hover {background-color: var(--template-bg-dark-5, #f0f0f0);}';
$wa->addInlineStyle($style, [], []);

// Adopts the filter list this view fetches. Deferred, so it has run before any fetch callback.
$wa->registerAndUseScript('com_j2commerce.dom', 'media/com_j2commerce/js/site/j2commerce-dom.js', [], ['defer' => true]);

$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

$global_config = Factory::getApplication()->getConfig();
$limit    = $global_config->get('list_limit', 20);
$ajaxBase = json_encode(\Joomla\CMS\Uri\Uri::base() . 'index.php');

?>

<fieldset class="options-form">
    <legend><?php echo Text::_('COM_J2COMMERCE_TITLE_FILTERGROUPS'); ?></legend>
    <div class="j2commerce-product-filters" id="j2commerce-product-filters">
        <?php echo (new FileLayout('form_ajax_avfilter', JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product'))->render(['product' => $item]);?>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductFiltersEdit', array($this, $item, $formPrefix))->getArgument('html', ''); ?>
    </div>
</fieldset>


<script type="text/javascript">
    var J2COMMERCE_AJAX_BASE = <?php echo $ajaxBase; ?>;
    var total_variants = <?php echo $item->productfilter_pagination->total ?? 0; ?>;
    var limit = <?php echo $limit ?? 20; ?>;
    var product_id = <?php echo $item->j2commerce_product_id ?? 0; ?>;

    document.addEventListener("DOMContentLoaded", function () {
        var filterBlock = document.getElementById("j2commerce-product-filters");
        if (filterBlock) {
            var paginationWrapper = document.createElement('nav');
            paginationWrapper.className = 'pagination__wrapper';
            paginationWrapper.setAttribute('aria-label', '<?php echo Text::_('JLIB_HTML_PAGINATION'); ?>');
            var totalWrap = document.createElement('div');
            totalWrap.className = 'text-end';
            var totalCount = document.createElement('span');
            totalCount.className = 'me-1';
            totalCount.textContent = String(total_variants);
            totalWrap.appendChild(totalCount);
            totalWrap.appendChild(document.createTextNode(
                <?php echo json_encode(Text::_('COM_J2COMMERCE_PRODUCT_FILTERS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
            ));

            var filterNav = document.createElement('div');
            filterNav.id = 'j2commerce_filterNav';
            filterNav.className = 'pagination pagination-toolbar text-center mt-0 mx-0';
            var filterNavList = document.createElement('ul');
            filterNavList.className = 'pagination pagination-list text-center ms-auto me-0';
            filterNav.appendChild(filterNavList);

            paginationWrapper.replaceChildren(totalWrap, filterNav);
            filterBlock.parentNode.insertBefore(paginationWrapper, filterBlock.nextSibling);
            var numPages = Math.ceil(total_variants / limit);
            if(numPages > 1 ){
                createFilterFooterList(numPages);
            }
        }
    });

    function createFilterFooterList(numPages){
        var paginationList = document.querySelector('#j2commerce_filterNav .pagination-list');
        if (!paginationList) {
            console.error("Pagination list element not found!");
            return;
        }
        var limitstart = 0;
        for (var i = 0; i < numPages; i++) {
            var pageNum = i + 1;
            limitstart = i * limit;
            var listItem = document.createElement('li');
            listItem.className = 'page-item';
            var link = document.createElement('a');
            link.className = 'page-link';
            link.href = 'javascript:void(0);';
            link.setAttribute('data-get_limitstart', limitstart);
            link.setAttribute('data-get_page', i);
            link.setAttribute('rel', i);
            link.textContent = pageNum;
            link.addEventListener('click', function () {
                var paginationItems = document.querySelectorAll('#j2commerce_filterNav .pagination-list li');
                paginationItems.forEach(function (item) {
                    item.classList.remove('active');
                });
                this.parentNode.classList.add('active');
                getProductFilterList(this);
            });
            listItem.appendChild(link);
            paginationList.appendChild(listItem);
        }
        var firstListItem = paginationList.querySelector('li.page-item');
        if (firstListItem) {
            firstListItem.classList.add('active');
        }
    }

    function getProductFilterList(element) {
        var limitstart = element.getAttribute('data-get_limitstart');
        var data = {
            option: 'com_j2commerce',
            task: 'products.getProductFilterListAjax',
            format: 'raw',
            limitstart: limitstart,
            product_id: product_id,
            limit: limit,
            form_prefix: <?php echo json_encode($formPrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
        };
        var serializedData = Object.keys(data)
            .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(data[key]))
            .join('&');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', J2COMMERCE_AJAX_BASE, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('Cache-Control', 'no-cache');
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    var json = JSON.parse(xhr.responseText);
                    if(json['html']){
                        J2CommerceDom.adopt(document.getElementById('j2commerce-product-filters'), json['html']);
                    }
                } catch (error) {
                    console.error('Error parsing JSON response:', error);
                }
            } else {
                console.error('Request failed with status:', xhr.status);
            }
        };

        xhr.onerror = function () {
            console.error('Request failed due to a network error.');
        };
        xhr.send(serializedData);
    }

    function removeFilter(filter_id, product_id) {
        // Prepare the data for the request
        var rem_filter = {
            option: 'com_j2commerce',
            task: 'products.deleteproductfilter',
            format: 'raw',
            filter_id: filter_id,
            product_id: product_id
        };

        // Add CSRF token
        var tokenName = Joomla.getOptions('com_j2commerce.productForm')?.csrfToken;
        if (tokenName) {
            rem_filter[tokenName] = 1;
        }

        // Serialize the data into a query string
        var formData = Object.keys(rem_filter)
            .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(rem_filter[key]))
            .join('&');

        // Remove all existing notifications
        var notifications = document.querySelectorAll('.j2notify');
        notifications.forEach(function (notification) {
            notification.remove();
        });

        // Create an XMLHttpRequest
        var xhr = new XMLHttpRequest();
        xhr.open('POST', J2COMMERCE_AJAX_BASE, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        // Handle the response
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                var data;
                try {
                    data = JSON.parse(xhr.responseText);
                } catch (e) {
                    console.error('Failed to parse JSON response:', xhr.responseText);
                    return;
                }

                if(data.success) {
                    // Remove the filter element
                    var filterElement = document.getElementById('product_filter_current_option_' + filter_id);
                    if (filterElement) {
                        filterElement.remove();
                    }
                }

                // Add the notification message
                var productFiltersTable = document.getElementById('j2commerce_product_filters_table');
                if (productFiltersTable) {
                    var notificationDiv = document.createElement('div');
                    notificationDiv.className = 'j2notify alert alert-block';
                    notificationDiv.textContent = data.msg;
                    productFiltersTable.parentNode.insertBefore(notificationDiv, productFiltersTable);
                }
            } else {
                console.error('Request failed with status:', xhr.status);
            }
        };

        xhr.onerror = function () {
            console.error('Request failed due to a network error.');
        };

        // Send the serialized data
        xhr.send(formData);
    }

    document.addEventListener("DOMContentLoaded", function () {
        var productFilterInput = document.getElementById('J2CommerceproductFilter');

        if (productFilterInput) {
            let autocompleteList;

            function createAutocompleteContainer() {
                autocompleteList = document.createElement('div');
                autocompleteList.className = 'autocomplete-list';
                autocompleteList.style.position = 'absolute';
                autocompleteList.style.width = '350px';
                autocompleteList.style.zIndex = '1000';
                productFilterInput.parentNode.appendChild(autocompleteList);
            }

            function updateAutocompleteListState() {
                if (autocompleteList.children.length > 0) {
                    autocompleteList.classList.add('autocomplete-active');
                } else {
                    autocompleteList.classList.remove('autocomplete-active');
                }
            }

            function createFilterRow(value, label) {
                var row = document.createElement('tr');

                var nameCell = document.createElement('td');
                nameCell.className = 'addedFilter';
                nameCell.textContent = label;

                var actionCell = document.createElement('td');
                actionCell.className = 'text-center';

                // Same shape as the server-rendered rows in form_ajax_avfilter.php.
                var remove = document.createElement('span');
                remove.className = 'filterRemove';
                remove.addEventListener('click', function () {
                    row.remove();
                });

                var icon = document.createElement('span');
                icon.className = 'icon icon-trash text-danger';
                remove.appendChild(icon);

                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.value = value;
                hidden.name = <?php echo json_encode($formPrefix . '[productfilter_ids][]', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

                actionCell.appendChild(remove);
                actionCell.appendChild(hidden);
                row.appendChild(nameCell);
                row.appendChild(actionCell);

                return row;
            }

            createAutocompleteContainer();
            productFilterInput.addEventListener('input', function () {
                var term = this.value;
                if (term.length < 2) {
                    autocompleteList.replaceChildren();
                    updateAutocompleteListState();
                    return;
                }

                var searchFilter = {
                    option: 'com_j2commerce',
                    task: 'products.searchproductfilters',
                    format: 'raw',
                    q: term
                };

                var formData = new URLSearchParams(searchFilter).toString();

                fetch(J2COMMERCE_AJAX_BASE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        productFilterInput.classList.remove('optionsLoading');
                        autocompleteList.replaceChildren();

                        data.forEach(item => {
                            var label = item.group_name + ' > ' + item.filter_name;
                            var option = document.createElement('div');
                            option.className = 'autocomplete-item';
                            option.textContent = label;
                            option.dataset.value = item.j2commerce_filter_id;
                            option.dataset.label = label;

                            // Handle item selection
                            option.addEventListener('click', function () {
                                // The anchor row only exists once the AJAX partial has rendered.
                                var anchor = document.querySelector('.j2commerce_a_filter');
                                if (!anchor) {
                                    return;
                                }

                                autocompleteList.replaceChildren();
                                updateAutocompleteListState();
                                anchor.parentNode.insertBefore(
                                    createFilterRow(this.dataset.value, this.dataset.label),
                                    anchor
                                );
                                productFilterInput.value = '';
                            });
                            autocompleteList.appendChild(option);
                        });

                        updateAutocompleteListState();
                    })
                    .catch(error => {
                        console.error('Error fetching autocomplete data:', error);
                    });
                productFilterInput.classList.add('optionsLoading');
            });

            document.addEventListener('click', function (e) {
                if (!productFilterInput.contains(e.target) && !autocompleteList.contains(e.target)) {
                    autocompleteList.replaceChildren();
                    updateAutocompleteListState();
                }
            });
        }
    });
</script>


