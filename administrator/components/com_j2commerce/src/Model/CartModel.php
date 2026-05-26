<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CartHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UploadHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;

/**
 * Cart Model for single cart operations.
 *
 * Provides cart operations: add item, update, remove, get cart, validate, etc.
 * This model is used by both admin and site frontend for cart manipulation.
 *
 * @since  6.0.0
 */
class CartModel extends BaseDatabaseModel
{
    /**
     * Default behaviors to load for cart item processing.
     *
     * @var    array
     * @since  6.0.0
     */
    protected array $default_behaviors = ['filters', 'cartdefault'];

    /**
     * Behavior prefix for loading product type behaviors.
     *
     * @var    string
     * @since  6.0.0
     */
    private string $behavior_prefix = 'Cart';

    /**
     * Cached cart items.
     *
     * @var    array|null
     * @since  6.0.0
     */
    protected ?array $_cartitems = null;

    /**
     * Cart type (cart, wishlist, etc.).
     *
     * @var    string
     * @since  6.0.0
     */
    protected string $cart_type = 'cart';

    /**
     * Database instance.
     *
     * @var    DatabaseInterface|null
     * @since  6.0.0
     */
    protected ?DatabaseInterface $db = null;

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.0
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Add item to cart.
     *
     * Validates product and triggers behavior-based processing.
     *
     * @return  array|object  Result array or object with success/error info.
     *
     * @since   6.0.0
     */
    public function addCartItem(): array|object
    {
        $app    = Factory::getApplication();
        $errors = [];
        $json   = new CMSObject();

        // Check product ID
        $productId = $app->getInput()->getInt('product_id', 0);

        if (!$productId) {
            $errors['error'] = ['general' => Text::_('COM_J2COMMERCE_PRODUCT_NOT_FOUND')];
            return $errors;
        }

        // Check quantity is positive
        $quantity = $app->getInput()->getFloat('product_qty', 1);

        if ($quantity <= 0) {
            $errors['error'] = ['general' => Text::_('COM_J2COMMERCE_PRODUCT_INVALID_QUANTITY')];
            return $errors;
        }

        // Load product via native ProductHelper
        $product = ProductHelper::getFullProduct($productId, true, true);

        // Validate product was found
        if (!$product) {
            $errors['error'] = ['general' => Text::_('COM_J2COMMERCE_PRODUCT_NOT_FOUND')];
            return $errors;
        }

        // Validate product is enabled (published). Visibility only controls category list display,
        // not whether a product can be added to cart.
        if (($product->enabled ?? 0) != 1) {
            $errors['error'] = ['general' => Text::_('COM_J2COMMERCE_PRODUCT_NOT_ENABLED_CANNOT_ADDTOCART')];
            return $errors;
        }

        if (($product->j2commerce_product_id ?? 0) != $productId) {
            $errors['error'] = ['general' => Text::_('COM_J2COMMERCE_PRODUCT_NOT_FOUND')];
            return $errors;
        }

        // Load product type behavior
        $behaviorClass = $this->getBehaviorClass($product->product_type ?: 'simple');

        if ($behaviorClass && class_exists($behaviorClass)) {
            $behavior = new $behaviorClass();

            if (method_exists($behavior, 'onBeforeAddCartItem')) {
                try {
                    $behavior->onBeforeAddCartItem($this, $product, $json);
                } catch (\Exception $e) {
                    $this->setError($e->getMessage());
                    $errors['error'] = ['general' => $e->getMessage()];
                    return $errors;
                }
            }
        }

        return $json->result ?? $json;
    }

    /**
     * Add item to cart with given item data.
     *
     * @param   object  $item  Cart item object with required properties.
     *
     * @return  object|false  Cart object on success, false on failure.
     *
     * @since   6.0.0
     */
    public function addItem(object $item): object|false
    {
        $cart = $this->getCart();

        if (empty($cart) || empty($cart->j2commerce_cart_id)) {
            return false;
        }

        // product_type must come from the caller (Cart{Type} behavior or reorder
        // controller). Never default to 'simple' — that downgrades subscription /
        // variable / configurable / etc. products and breaks downstream routing.
        $itemProductType = trim((string) ($item->product_type ?? ''));

        if ($itemProductType === '') {
            $this->setError('CartModel::addItem requires $item->product_type — refusing to default to "simple"');
            return false;
        }

        $db  = $this->db;
        $app = Factory::getApplication();

        // Prepare search keys
        $cartId         = (int) $cart->j2commerce_cart_id;
        $variantId      = (int) $item->variant_id;
        $productOptions = $item->product_options ?? '';

        // Check if item already exists in cart
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_cartitems'))
            ->where($db->quoteName('cart_id') . ' = :cartId')
            ->where($db->quoteName('variant_id') . ' = :variantId')
            ->where($db->quoteName('product_options') . ' = :options')
            ->bind(':cartId', $cartId, ParameterType::INTEGER)
            ->bind(':variantId', $variantId, ParameterType::INTEGER)
            ->bind(':options', $productOptions);

        $db->setQuery($query);
        $existingItem = $db->loadObject();

        // Prepare cart item params
        $itemParams = new Registry();

        if (isset($item->cartitem_params)) {
            if (\is_array($item->cartitem_params)) {
                $itemParams->loadArray($item->cartitem_params);
            } else {
                $itemParams->loadString($item->cartitem_params);
            }
        }

        $cartitemParams = $itemParams->toString('JSON');

        // Trigger plugin event
        J2CommerceHelper::plugin()->event('BeforeLoadCartItemForAdd', [&$item]);

        if ($existingItem) {
            // Update existing item quantity
            $newQty = (float) $existingItem->product_qty + (float) $item->product_qty;

            // Merge params
            $existingParams = new Registry($existingItem->cartitem_params);
            $existingParams->merge($itemParams);
            $mergedParams = $existingParams->toString('JSON');

            $updateQuery = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_cartitems'))
                ->set($db->quoteName('product_qty') . ' = :qty')
                ->set($db->quoteName('cartitem_params') . ' = :params')
                ->where($db->quoteName('j2commerce_cartitem_id') . ' = :itemId')
                ->bind(':qty', $newQty)
                ->bind(':params', $mergedParams)
                ->bind(':itemId', $existingItem->j2commerce_cartitem_id, ParameterType::INTEGER);

            $db->setQuery($updateQuery);
            $db->execute();

            // Trigger after add event
            $this->triggerAfterAddCartItem($existingItem);
        } else {
            // Insert new cart item
            $productId   = (int) $item->product_id;
            $vendorId    = (int) ($item->vendor_id ?? 0);
            $productType = $itemProductType;
            $productQty  = (float) $item->product_qty;

            $insertQuery = $db->getQuery(true)
                ->insert($db->quoteName('#__j2commerce_cartitems'))
                ->columns($db->quoteName([
                    'cart_id',
                    'product_id',
                    'variant_id',
                    'vendor_id',
                    'product_type',
                    'cartitem_params',
                    'product_qty',
                    'product_options',
                ]))
                ->values(':cartId, :productId, :variantId, :vendorId, :productType, :params, :qty, :options')
                ->bind(':cartId', $cartId, ParameterType::INTEGER)
                ->bind(':productId', $productId, ParameterType::INTEGER)
                ->bind(':variantId', $variantId, ParameterType::INTEGER)
                ->bind(':vendorId', $vendorId, ParameterType::INTEGER)
                ->bind(':productType', $productType)
                ->bind(':params', $cartitemParams)
                ->bind(':qty', $productQty)
                ->bind(':options', $productOptions);

            $db->setQuery($insertQuery);

            if (!$db->execute()) {
                return false;
            }

            // Get inserted item for event
            $newItemId                       = $db->insertid();
            $newItem                         = new \stdClass();
            $newItem->j2commerce_cartitem_id = $newItemId;
            $newItem->cart_id                = $cartId;
            $newItem->product_id             = $productId;
            $newItem->variant_id             = $variantId;

            $this->triggerAfterAddCartItem($newItem);
        }

        return $cart;
    }

    /**
     * Trigger after add cart item event.
     *
     * @param   object  $item  Cart item object.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function triggerAfterAddCartItem(object $item): void
    {
        try {
            J2CommerceHelper::plugin()->event('AfterAddCartItem', [$this, $item]);
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
        }
    }

    /**
     * Get cart for current user/session.
     *
     * @param   int   $cartId          Optional specific cart ID to load.
     * @param   bool  $needCreateCart  Whether to create cart if none exists.
     *
     * @return  object|null  Cart object or null.
     *
     * @since   6.0.0
     */
    public function getCart(int $cartId = 0, bool $needCreateCart = true): ?object
    {
        return CartHelper::getInstance()->getCart($cartId, $needCreateCart, $this->cart_type);
    }

    /**
     * Get cart items.
     *
     * @param   bool  $force  Force reload.
     *
     * @return  array  Cart items array.
     *
     * @since   6.0.0
     */
    public function getItems(bool $force = false): array
    {
        $cart = $this->getCart(0, false);

        if (!$cart || empty($cart->j2commerce_cart_id)) {
            return [];
        }

        static $cartsets = [];

        if ($force) {
            $cartsets = [];
        }

        $cartId = (int) $cart->j2commerce_cart_id;

        if (!isset($cartsets[$cartId])) {
            // Load cart items via CartItems model
            $cartItemsModel = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('CartItems', 'Administrator', ['ignore_request' => true]);

            // Force populateState() to run first, then override cart_id filter.
            // Without this, getItems() triggers populateState() which overwrites
            // the programmatic setState with an empty value from user state.
            $cartItemsModel->getState();
            $cartItemsModel->setState('filter.cart_id', $cartId);
            $items = $cartItemsModel->getItems();

            if (!\is_array($items)) {
                $items = [];
            }

            // Process each item with product type behavior
            foreach ($items as &$item) {
                $behaviorClass = $this->getBehaviorClass($item->product_type ?: 'simple');

                if ($behaviorClass && class_exists($behaviorClass)) {
                    $behavior = new $behaviorClass();

                    if (method_exists($behavior, 'onGetCartItems')) {
                        try {
                            $behavior->onGetCartItems($this, $item);
                        } catch (\Exception $e) {
                            $this->setError($e->getMessage());
                        }
                    }
                }
            }

            // Trigger plugin event
            J2CommerceHelper::plugin()->event('AfterGetCartItems', [&$items]);

            $cartsets[$cartId] = $items;
        }

        return $cartsets[$cartId];
    }

    /**
     * Update cart quantities.
     *
     * @return  array  Result with success or error.
     *
     * @since   6.0.0
     */
    public function update(): array
    {
        $app    = Factory::getApplication();
        $post   = $app->getInput()->getArray(['quantities' => 'ARRAY']);
        $cartId = $this->getCartId();
        $json   = [];

        if (!isset($post['quantities']) || !\is_array($post['quantities'])) {
            return $json;
        }

        $db = $this->db;

        foreach ($post['quantities'] as $cartitemId => $quantity) {
            $cartitemId = (int) $cartitemId;
            $quantity   = (float) $quantity;

            // Load cart item
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__j2commerce_cartitems'))
                ->where($db->quoteName('j2commerce_cartitem_id') . ' = :itemId')
                ->bind(':itemId', $cartitemId, ParameterType::INTEGER);

            $db->setQuery($query);
            $cartitem = $db->loadObject();

            if (!$cartitem || (int) $cartitem->cart_id !== $cartId) {
                continue;
            }

            // Validate quantity
            if (!$this->validate($cartitem, $quantity)) {
                $json['error'] = $this->getError();
                continue;
            }

            if (empty($quantity) || $quantity < 1) {
                // Remove item
                $this->removeItemById($cartitemId, $cartitem);
            } else {
                // Update quantity
                $updateQuery = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_cartitems'))
                    ->set($db->quoteName('product_qty') . ' = :qty')
                    ->where($db->quoteName('j2commerce_cartitem_id') . ' = :itemId')
                    ->bind(':qty', $quantity)
                    ->bind(':itemId', $cartitemId, ParameterType::INTEGER);

                $db->setQuery($updateQuery);
                $db->execute();
            }
        }

        // Trigger plugin event
        J2CommerceHelper::plugin()->event('AfterUpdateCart', [$cartId, $post]);

        return $json;
    }

    /**
     * Delete item from cart.
     *
     * @return  bool  True on success.
     *
     * @since   6.0.0
     */
    public function deleteItem(): bool
    {
        $app        = Factory::getApplication();
        $cartitemId = $app->getInput()->getInt('cartitem_id', 0);

        if (!$cartitemId) {
            $this->setError(Text::_('COM_J2COMMERCE_CART_DELETE_ERROR'));
            return false;
        }

        $db = $this->db;

        // Load cart item
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_cartitems'))
            ->where($db->quoteName('j2commerce_cartitem_id') . ' = :itemId')
            ->bind(':itemId', $cartitemId, ParameterType::INTEGER);

        $db->setQuery($query);
        $cartitem = $db->loadObject();

        if (!$cartitem) {
            $this->setError(Text::_('COM_J2COMMERCE_CART_DELETE_ERROR'));
            return false;
        }

        if ((int) $cartitem->cart_id !== $this->getCartId()) {
            $this->setError(Text::_('COM_J2COMMERCE_CART_DELETE_ERROR'));
            return false;
        }

        return $this->removeItemById($cartitemId, $cartitem);
    }

    /**
     * Remove cart item by ID.
     *
     * @param   int     $cartitemId  Cart item ID.
     * @param   object  $cartitem    Cart item object.
     *
     * @return  bool  True on success.
     *
     * @since   6.0.0
     */
    protected function removeItemById(int $cartitemId, object $cartitem): bool
    {
        $db = $this->db;

        // Create item object for event
        $item                         = new CMSObject();
        $item->product_id             = $cartitem->product_id;
        $item->variant_id             = $cartitem->variant_id;
        $item->product_options        = $cartitem->product_options ?? '';
        $item->j2commerce_cartitem_id = $cartitemId;

        // Delete the item
        $deleteQuery = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_cartitems'))
            ->where($db->quoteName('j2commerce_cartitem_id') . ' = :itemId')
            ->bind(':itemId', $cartitemId, ParameterType::INTEGER);

        $db->setQuery($deleteQuery);

        if ($db->execute()) {
            J2CommerceHelper::plugin()->event('RemoveFromCart', [$item]);
            return true;
        }

        $this->setError(Text::_('COM_J2COMMERCE_CART_DELETE_ERROR'));
        return false;
    }

    /**
     * Validate cart item quantity.
     *
     * @param   object  $cartitem  Cart item object.
     * @param   float   $quantity  Desired quantity.
     *
     * @return  bool  True if valid.
     *
     * @since   6.0.0
     */
    public function validate(object $cartitem, float $quantity): bool
    {
        $cart = $this->getCart($this->getCartId());

        if ($cart && $cart->cart_type !== 'cart') {
            return true;
        }

        $behaviorClass = $this->getBehaviorClass($cartitem->product_type ?: 'simple');

        if ($behaviorClass && class_exists($behaviorClass)) {
            $behavior = new $behaviorClass();

            if (method_exists($behavior, 'onValidateCart')) {
                try {
                    return $behavior->onValidateCart($this, $cartitem, $quantity);
                } catch (\Exception $e) {
                    $this->setError($e->getMessage());
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Validate uploaded files.
     *
     * @param   array  $files  Files array.
     *
     * @return  array  Result with success/error info.
     *
     * @since   6.0.0
     */
    public function validate_files(array $files = []): array
    {
        $app  = Factory::getApplication();
        $json = [];

        if (\count($files) < 1) {
            $files = $app->getInput()->files->get('file');
        }

        $uploadResult = $this->uploadFile($files);

        if ($uploadResult === false) {
            $json['error'] = $this->getError();
        } else {
            J2CommerceHelper::plugin()->event('AfterValidateFiles', [&$json, &$uploadResult]);

            if (empty($json)) {
                $user   = Factory::getApplication()->getIdentity();
                $stored = UploadHelper::createPendingUpload(
                    $this->getCartId(),
                    (string) $uploadResult['original_name'],
                    (string) $uploadResult['mangled_name'],
                    (string) $uploadResult['saved_name'],
                    (string) $uploadResult['mime_type'],
                    (int) ($uploadResult['file_size'] ?? 0),
                    (int) ($user->id ?? 0)
                );

                if (!$stored) {
                    $json['error'] = Text::sprintf('COM_J2COMMERCE_UPLOAD_ERR_GENERIC_ERROR');
                }
            }
        }

        if (empty($json['error'])) {
            $json['name']    = $uploadResult['original_name'];
            $json['code']    = $uploadResult['mangled_name'];
            $json['success'] = Text::_('COM_J2COMMERCE_UPLOAD_SUCCESSFUL');
        }

        return $json;
    }

    /**
     * Replicates MediaHelper::canUpload's filename/extension/MIME/size/PHP-tag checks
     * inline so guests can upload product-option files when the Allow Guest Uploads
     * config flag is enabled, without flipping Joomla's global media restrictions.
     * Skips ONLY the user-group gate; every other security check is preserved.
     *
     * @param   array  $file  File array from $_FILES.
     *
     * @return  string|null  Error message, or null when the file is acceptable.
     *
     * @since   6.0.0
     */
    protected function validateGuestUpload(array $file): ?string
    {
        if (empty($file['name'])) {
            return Text::_('JLIB_MEDIA_ERROR_UPLOAD_INPUT');
        }

        if ($file['name'] !== File::makeSafe($file['name'])) {
            return Text::_('JLIB_MEDIA_ERROR_WARNFILENAME');
        }

        $parts = explode('.', $file['name']);

        if (\count($parts) < 2) {
            return Text::_('JLIB_MEDIA_ERROR_WARNFILETYPE');
        }

        array_shift($parts);
        $parts = array_map('strtolower', $parts);

        $executables = array_merge(MediaHelper::EXECUTABLES, InputFilter::FORBIDDEN_FILE_EXTENSIONS);

        if (array_intersect($parts, $executables)) {
            return Text::_('JLIB_MEDIA_ERROR_WARNFILETYPE');
        }

        $filetype    = array_pop($parts);
        $mediaParams = ComponentHelper::getParams('com_media');
        $allowable   = array_map('trim', explode(',', (string) $mediaParams->get('restrict_uploads_extensions', 'bmp,gif,jpg,jpeg,png,webp,avif,ico,mp3,m4a,mp4a,ogg,mp4,mp4v,mpeg,mov,odg,odp,ods,odt,pdf,png,ppt,txt,xcf,xls,csv')));

        if ($filetype === '' || !\in_array($filetype, $allowable, true)) {
            return Text::_('JLIB_MEDIA_ERROR_WARNFILETYPE');
        }

        $maxSize = (int) $mediaParams->get('upload_maxsize', 0) * 1024 * 1024;

        if ($maxSize > 0 && (int) ($file['size'] ?? 0) > $maxSize) {
            return Text::_('JLIB_MEDIA_ERROR_WARNFILETOOLARGE');
        }

        if (empty($file['tmp_name'])) {
            return Text::_('JLIB_MEDIA_ERROR_WARNFILETOOLARGE');
        }

        $mime = MediaHelper::getMimeType($file['tmp_name'], MediaHelper::isImage($file['tmp_name']));

        if ($mime === false) {
            return Text::_('JLIB_MEDIA_ERROR_WARNINVALID_MIME');
        }

        if ((int) $mediaParams->get('check_mime', 1) === 1) {
            $allowedMime = $mediaParams->get(
                'upload_mime',
                'image/jpeg,image/gif,image/png,image/bmp,image/webp,image/avif,application/msword,'
                    . 'application/excel,application/pdf,application/powerpoint,text/plain,application/x-zip'
            );
            $allowedMime = array_map('trim', explode(',', str_replace('\\', '', (string) $allowedMime)));

            if (!\in_array($mime, $allowedMime, true)) {
                return Text::sprintf('JLIB_MEDIA_ERROR_WARNINVALID_MIMETYPE', $mime);
            }
        }

        $content = file_get_contents($file['tmp_name']);

        if ($content !== false && preg_match('/\<\?php/i', $content)) {
            return Text::_('COM_J2COMMERCE_UPLOAD_FILE_PHP_TAGS');
        }

        return null;
    }

    /**
     * Upload file with validation.
     *
     * @param   array  $file         File array from $_FILES.
     * @param   bool   $checkUpload  Whether to validate upload.
     *
     * @return  array|false  Upload info array or false on failure.
     *
     * @since   6.0.0
     */
    protected function uploadFile(array $file, bool $checkUpload = true): array|false
    {
        if (!isset($file['name'])) {
            $this->setError(Text::_('COM_J2COMMERCE_ATTACHMENTS_ERR_NOFILE'));
            return false;
        }

        // Preserve the shopper's original filename for storage + display, then
        // sanitize a working copy so Joomla's File::makeSafe (called inside
        // MediaHelper::canUpload) accepts names with spaces and parens.
        $originalName = (string) $file['name'];
        $file['name'] = self::sanitizeUploadFileName($originalName);

        if ($checkUpload) {
            $app              = Factory::getApplication();
            $isGuest          = $app->getIdentity()?->guest ?? true;
            $j2cParams        = ComponentHelper::getParams('com_j2commerce');
            $allowGuestBypass = $isGuest && (int) $j2cParams->get('allow_guest_uploads', 0) === 1;

            if ($allowGuestBypass) {
                $err = $this->validateGuestUpload($file);

                if ($err !== null) {
                    $this->setError(Text::_('COM_J2COMMERCE_UPLOAD_ERR_MEDIAHELPER_ERROR') . ' ' . $err);
                    return false;
                }
            } else {
                $mediaHelper = new MediaHelper();

                if (!$mediaHelper->canUpload($file)) {
                    $errors = $app->getMessageQueue();

                    if (\count($errors)) {
                        $error = array_pop($errors);
                        $err   = $error['message'];
                    } else {
                        $err = '';
                    }

                    // Check for PHP tags
                    $content = file_get_contents($file['tmp_name']);

                    if (preg_match('/\<\?php/i', $content)) {
                        $err = Text::_('COM_J2COMMERCE_UPLOAD_FILE_PHP_TAGS');
                    }

                    if (!empty($err)) {
                        $this->setError(Text::_('COM_J2COMMERCE_UPLOAD_ERR_MEDIAHELPER_ERROR') . ' ' . $err);
                    } else {
                        $this->setError(Text::_('COM_J2COMMERCE_UPLOAD_ERR_GENERIC_ERROR'));
                    }

                    return false;
                }
            }
        }

        $attachmentRoot = ConfigHelper::getAttachmentAbsolutePath();

        if ($attachmentRoot === null) {
            $this->setError(Text::_('COM_J2COMMERCE_UPLOAD_ERROR_FOLDER_PERMISSION_ERROR'));
            return false;
        }

        $cartId       = $this->getCartId();
        $uploadFolder = $attachmentRoot . '/tmp/' . $cartId;

        if (!is_dir($uploadFolder) && !mkdir($uploadFolder, 0755, true) && !is_dir($uploadFolder)) {
            $this->setError(Text::_('COM_J2COMMERCE_UPLOAD_ERROR_FOLDER_PERMISSION_ERROR'));
            return false;
        }

        $extension   = strtolower(File::getExt($file['name']));
        $name        = UploadHelper::randomToken() . ($extension !== '' ? '.' . $extension : '');
        $mangledname = UploadHelper::randomToken();
        $filepath    = $uploadFolder . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->setError(Text::_('COM_J2COMMERCE_UPLOAD_ERR_CANTJFILEUPLOAD'));
            return false;
        }

        // Get MIME type
        if (\function_exists('mime_content_type')) {
            $mime = mime_content_type($filepath);
        } elseif (\function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $filepath);
        } else {
            $mime = 'application/octet-stream';
        }

        return [
            'original_name' => $originalName,
            'mangled_name'  => $mangledname,
            'saved_name'    => $name,
            'mime_type'     => $mime,
            'file_size'     => filesize($filepath) ?: 0,
        ];
    }

    /**
     * Sanitize an uploaded filename to satisfy Joomla's File::makeSafe rules
     * while preserving the original extension.
     */
    protected static function sanitizeUploadFileName(string $name): string
    {
        $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
        $info = pathinfo($name);
        $base = $info['filename'] ?? '';
        $ext  = isset($info['extension']) ? '.' . $info['extension'] : '';

        $base = preg_replace('/[^A-Za-z0-9_\-]/', '_', $base) ?? '';
        $base = trim($base, '_');

        if ($base === '') {
            $base = 'upload';
        }

        return $base . $ext;
    }

    /**
     * Get cart URL.
     *
     * @return  string  Cart URL.
     *
     * @since   6.0.0
     */
    public function getCartUrl(): string
    {
        $url = J2CommerceHelper::platform()->getCartUrl();

        J2CommerceHelper::plugin()->event('GetCartLink', [&$url]);

        return $url;
    }

    /**
     * Get checkout URL.
     *
     * @return  string  Checkout URL.
     *
     * @since   6.0.0
     */
    public function getCheckoutUrl(): string
    {
        $url = J2CommerceHelper::platform()->getCheckoutUrl();

        J2CommerceHelper::plugin()->event('GetCheckoutLink', [&$url]);

        return $url;
    }

    /**
     * Get continue shopping URL configuration.
     *
     * @return  object  Object with 'type' and 'url' properties.
     *
     * @since   6.0.0
     */
    public function getContinueShoppingUrl(): object
    {
        $params = J2CommerceHelper::config();
        $type   = $params->get('config_continue_shopping_page', 'previous');

        $item       = new CMSObject();
        $item->type = $type;

        switch ($type) {
            case 'menu':
                $menuItemid = $params->get('continue_shopping_page_menu', '');

                if (empty($menuItemid)) {
                    $item->url  = '';
                    $item->type = 'previous';
                } else {
                    $app      = Factory::getApplication();
                    $menu     = $app->getMenu('site');
                    $menuItem = $menu->getItem($menuItemid);

                    if (\is_object($menuItem)) {
                        $item->url = Route::_($menuItem->link . '&Itemid=' . $menuItem->id, false);
                    } else {
                        $item->url  = '';
                        $item->type = 'previous';
                    }
                }
                break;

            case 'url':
                $customUrl = $params->get('config_continue_shopping_page_url', '');

                if (empty($customUrl)) {
                    $item->url  = '';
                    $item->type = 'previous';
                } else {
                    $item->url = $customUrl;
                }
                break;

            case 'previous':
            default:
                $item->url = '';
                break;
        }

        J2CommerceHelper::plugin()->event('GetContinueShoppingUrl', [&$item]);

        return $item;
    }

    /**
     * Get empty cart redirect URL.
     *
     * @return  string|null  Redirect URL or null.
     *
     * @since   6.0.0
     */
    public function getEmptyCartRedirectUrl(): ?string
    {
        $params = J2CommerceHelper::config();
        $type   = $params->get('config_cart_empty_redirect', 'cart');
        $url    = '';

        switch ($type) {
            case 'homepage':
                $app     = Factory::getApplication();
                $menu    = $app->getMenu('site');
                $default = $menu->getDefault($app->getLanguage()->getTag());
                $url     = $default ? Route::_($default->link . '&Itemid=' . $default->id, false) : Route::_('index.php', false);
                break;

            case 'menu':
                $menuItemid = $params->get('continue_cart_redirect_menu', '');

                if (!empty($menuItemid)) {
                    $app      = Factory::getApplication();
                    $menu     = $app->getMenu('site');
                    $menuItem = $menu->getItem($menuItemid);

                    if (\is_object($menuItem)) {
                        $url = Route::_($menuItem->link . '&Itemid=' . $menuItem->id, false);
                    }
                }
                break;

            case 'url':
                $url = $params->get('config_cart_redirect_page_url', '');
                break;

            case 'cart':
            default:
                $url = '';
                break;
        }

        return $url ?: null;
    }

    /**
     * Set cart ID in session.
     *
     * @param   int  $cartId  Cart ID.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setCartId(int $cartId = 0): void
    {
        $session = Factory::getApplication()->getSession();
        $session->set('cart_id.' . $this->getCartType(), $cartId, 'j2commerce');
    }

    /**
     * Get cart ID from session.
     *
     * @return  int  Cart ID.
     *
     * @since   6.0.0
     */
    public function getCartId(): int
    {
        $session = Factory::getApplication()->getSession();

        return (int) $session->get('cart_id.' . $this->getCartType(), 0, 'j2commerce');
    }

    /**
     * Set cart type.
     *
     * @param   string  $type  Cart type (cart, wishlist, etc.).
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setCartType(string $type = 'cart'): void
    {
        $this->cart_type = $type;
    }

    /**
     * Get cart type.
     *
     * @return  string  Cart type.
     *
     * @since   6.0.0
     */
    public function getCartType(): string
    {
        return $this->cart_type;
    }

    /**
     * Get behavior class name for product type.
     *
     * @param   string  $productType  Product type (simple, variable, etc.).
     *
     * @return  string|null  Fully qualified class name or null.
     *
     * @since   6.0.0
     */
    protected function getBehaviorClass(string $productType): ?string
    {
        $className = 'J2Commerce\\Component\\J2commerce\\Administrator\\Model\\Behavior\\' .
            $this->behavior_prefix . ucfirst($productType);

        return class_exists($className) ? $className : null;
    }

    /**
     * Method to get a table object.
     *
     * @param   string  $name     The table name.
     * @param   string  $prefix   The table prefix.
     * @param   array   $options  Configuration array.
     *
     * @return  Table  A Table object.
     *
     * @since   6.0.0
     */
    public function getTable($name = 'Cart', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }
}
