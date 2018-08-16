<?php
/**
 * 2007-2017 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2017 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

use PrestaShop\PrestaShop\Core\Addon\Module\ModuleManagerBuilder;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @property Product $object
 */
class AdminProductsControllerCore extends AdminController
{
    /** @var int Max image size for upload
     * As of 1.5 it is recommended to not set a limit to max image size
     */
    protected $max_file_size = null;
    protected $max_image_size = null;

    protected $_category;
    /**
     * @var string name of the tab to display
     */
    protected $tab_display;
    protected $tab_display_module;

    /**
     * The order in the array decides the order in the list of tab. If an element's value is a number, it will be preloaded.
     * The tabs are preloaded from the smallest to the highest number.
     * @var array Product tabs.
     */
    protected $available_tabs = array();

    protected $default_tab = 'Informations';

    protected $available_tabs_lang = array();

    protected $position_identifier = 'id_product';

    protected $submitted_tabs;

    protected $id_current_category;

    public function __construct($theme_name = 'default')
    {
        $this->bootstrap = true;
        $this->table = 'product';
        $this->className = 'Product';
        parent::__construct('', $theme_name);
    }

    public function init()
    {
        global $kernel;

        if (Tools::getIsset('id_product')) {
            if (Tools::getIsset('addproduct') || Tools::getIsset('updateproduct')) {
                if ($kernel instanceof HttpKernelInterface) {
                    $sfRouter = $kernel->getContainer()->get('router');
                    Tools::redirectAdmin($sfRouter->generate('admin_product_form',
                        array('id' => Tools::getValue('id_product'))
                    ));
                }
            }
        }

        return parent::init();
    }

    public static function getQuantities($echo, $tr)
    {
        if ((int)$tr['is_virtual'] == 1 && $tr['nb_downloadable'] == 0) {
            return '&infin;';
        } else {
            return $echo;
        }
    }

    public function setMedia()
    {
        parent::setMedia();

        $bo_theme = ((Validate::isLoadedObject($this->context->employee)
            && $this->context->employee->bo_theme) ? $this->context->employee->bo_theme : 'default');

        if (!file_exists(_PS_BO_ALL_THEMES_DIR_.$bo_theme.DIRECTORY_SEPARATOR
            .'template')) {
            $bo_theme = 'default';
        }

        $this->addJs(__PS_BASE_URI__.$this->admin_webpath.'/themes/'.$bo_theme.'/js/jquery.iframe-transport.js');
        $this->addJs(__PS_BASE_URI__.$this->admin_webpath.'/themes/'.$bo_theme.'/js/jquery.fileupload.js');
        $this->addJs(__PS_BASE_URI__.$this->admin_webpath.'/themes/'.$bo_theme.'/js/jquery.fileupload-process.js');
        $this->addJs(__PS_BASE_URI__.$this->admin_webpath.'/themes/'.$bo_theme.'/js/jquery.fileupload-validate.js');
        $this->addJs(__PS_BASE_URI__.'js/vendor/spin.js');
        $this->addJs(__PS_BASE_URI__.'js/vendor/ladda.js');
    }

    protected function _cleanMetaKeywords($keywords)
    {
        if (!empty($keywords) && $keywords != '') {
            $out = array();
            $words = explode(',', $keywords);
            foreach ($words as $word_item) {
                $word_item = trim($word_item);
                if (!empty($word_item) && $word_item != '') {
                    $out[] = $word_item;
                }
            }
            return ((count($out) > 0) ? implode(',', $out) : '');
        } else {
            return '';
        }
    }

    /**
     * @param Product|ObjectModel $object
     * @param string              $table
     */
    protected function copyFromPost(&$object, $table)
    {
        parent::copyFromPost($object, $table);
        if (get_class($object) != 'Product') {
            return;
        }

        /* Additional fields */
        foreach (Language::getIDs(false) as $id_lang) {
            if (isset($_POST['meta_keywords_'.$id_lang])) {
                $_POST['meta_keywords_'.$id_lang] = $this->_cleanMetaKeywords(Tools::strtolower($_POST['meta_keywords_'.$id_lang]));
                // preg_replace('/ *,? +,* /', ',', strtolower($_POST['meta_keywords_'.$id_lang]));
                $object->meta_keywords[$id_lang] = $_POST['meta_keywords_'.$id_lang];
            }
        }
        $_POST['width'] = empty($_POST['width']) ? '0' : str_replace(',', '.', $_POST['width']);
        $_POST['height'] = empty($_POST['height']) ? '0' : str_replace(',', '.', $_POST['height']);
        $_POST['depth'] = empty($_POST['depth']) ? '0' : str_replace(',', '.', $_POST['depth']);
        $_POST['weight'] = empty($_POST['weight']) ? '0' : str_replace(',', '.', $_POST['weight']);

        if (Tools::getIsset('unit_price') != null) {
            $object->unit_price = str_replace(',', '.', Tools::getValue('unit_price'));
        }
        if (Tools::getIsset('ecotax') != null) {
            $object->ecotax = str_replace(',', '.', Tools::getValue('ecotax'));
        }

        if ($this->isTabSubmitted('Informations')) {
            if ($this->checkMultishopBox('available_for_order', $this->context)) {
                $object->available_for_order = (int)Tools::getValue('available_for_order');
            }

            if ($this->checkMultishopBox('show_price', $this->context)) {
                $object->show_price = $object->available_for_order ? 1 : (int)Tools::getValue('show_price');
            }

            if ($this->checkMultishopBox('online_only', $this->context)) {
                $object->online_only = (int)Tools::getValue('online_only');
            }

            if ($this->checkMultishopBox('show_condition', $this->context)) {
                $object->show_condition = (int)Tools::getValue('show_condition');
            }
        }
        if ($this->isTabSubmitted('Prices')) {
            $object->on_sale = (int)Tools::getValue('on_sale');
        }
    }

    public function checkMultishopBox($field, $context = null)
    {
        static $checkbox = null;
        static $shop_context = null;

        if ($context == null && $shop_context == null) {
            $context = Context::getContext();
        }

        if ($shop_context == null) {
            $shop_context = $context->shop->getContext();
        }

        if ($checkbox == null) {
            $checkbox = Tools::getValue('multishop_check', array());
        }

        if ($shop_context == Shop::CONTEXT_SHOP) {
            return true;
        }

        if (isset($checkbox[$field]) && $checkbox[$field] == 1) {
            return true;
        }

        return false;
    }

    public function getList($id_lang, $orderBy = null, $orderWay = null, $start = 0, $limit = null, $id_lang_shop = null)
    {
        $orderByPriceFinal = (empty($orderBy) ? ($this->context->cookie->__get($this->table.'Orderby') ? $this->context->cookie->__get($this->table.'Orderby') : 'id_'.$this->table) : $orderBy);
        $orderWayPriceFinal = (empty($orderWay) ? ($this->context->cookie->__get($this->table.'Orderway') ? $this->context->cookie->__get($this->table.'Orderby') : 'ASC') : $orderWay);
        if ($orderByPriceFinal == 'price_final') {
            $orderBy = 'id_'.$this->table;
            $orderWay = 'ASC';
        }
        parent::getList($id_lang, $orderBy, $orderWay, $start, $limit, $this->context->shop->id);

        /* update product quantity with attributes ...*/
        $nb = count($this->_list);
        if ($this->_list) {
            $context = $this->context->cloneContext();
            $context->shop = clone($context->shop);
            /* update product final price */
            for ($i = 0; $i < $nb; $i++) {
                if (Context::getContext()->shop->getContext() != Shop::CONTEXT_SHOP) {
                    $context->shop = new Shop((int)$this->_list[$i]['id_shop_default']);
                }

                // convert price with the currency from context
                $this->_list[$i]['price'] = Tools::convertPrice($this->_list[$i]['price'], $this->context->currency, true, $this->context);
                $this->_list[$i]['price_tmp'] = Product::getPriceStatic($this->_list[$i]['id_product'], true, null,
                    (int)Configuration::get('PS_PRICE_DISPLAY_PRECISION'), null, false, true, 1, true, null, null, null, $nothing, true, true,
                    $context);
            }
        }

        if ($orderByPriceFinal == 'price_final') {
            if (strtolower($orderWayPriceFinal) == 'desc') {
                uasort($this->_list, 'cmpPriceDesc');
            } else {
                uasort($this->_list, 'cmpPriceAsc');
            }
        }
        for ($i = 0; $this->_list && $i < $nb; $i++) {
            $this->_list[$i]['price_final'] = $this->_list[$i]['price_tmp'];
            unset($this->_list[$i]['price_tmp']);
        }
    }

    protected function loadObject($opt = false)
    {
        $result = parent::loadObject($opt);
        if ($result && Validate::isLoadedObject($this->object)) {
            if (Shop::getContext() == Shop::CONTEXT_SHOP && Shop::isFeatureActive() && !$this->object->isAssociatedToShop()) {
                $default_product = new Product((int)$this->object->id, false, null, (int)$this->object->id_shop_default);
                $def = ObjectModel::getDefinition($this->object);
                foreach ($def['fields'] as $field_name => $row) {
                    if (is_array($default_product->$field_name)) {
                        foreach ($default_product->$field_name as $key => $value) {
                            $this->object->{$field_name}[$key] = $value;
                        }
                    } else {
                        $this->object->$field_name = $default_product->$field_name;
                    }
                }
            }
            $this->object->loadStockData();
        }
        return $result;
    }

    public function ajaxProcessGetCategoryTree()
    {
        $category = Tools::getValue('category', Category::getRootCategory()->id);
        $full_tree = Tools::getValue('fullTree', 0);
        $use_check_box = Tools::getValue('useCheckBox', 1);
        $selected = Tools::getValue('selected', array());
        $id_tree = Tools::getValue('type');
        $input_name = str_replace(array('[', ']'), '', Tools::getValue('inputName', null));

        $tree = new HelperTreeCategories('subtree_associated_categories');
        $tree->setTemplate('subtree_associated_categories.tpl')
            ->setUseCheckBox($use_check_box)
            ->setUseSearch(true)
            ->setIdTree($id_tree)
            ->setSelectedCategories($selected)
            ->setFullTree($full_tree)
            ->setChildrenOnly(true)
            ->setNoJS(true)
            ->setRootCategory($category);

        if ($input_name) {
            $tree->setInputName($input_name);
        }

        die($tree->render());
    }

    public function ajaxProcessGetCountriesOptions()
    {
        if (!$res = Country::getCountriesByIdShop((int)Tools::getValue('id_shop'), (int)$this->context->language->id)) {
            return;
        }

        $tpl = $this->createTemplate('specific_prices_shop_update.tpl');
        $tpl->assign(array(
            'option_list' => $res,
            'key_id' => 'id_country',
            'key_value' => 'name'
            )
        );

        $this->content = $tpl->fetch();
    }

    public function ajaxProcessGetCurrenciesOptions()
    {
        if (!$res = Currency::getCurrenciesByIdShop((int)Tools::getValue('id_shop'))) {
            return;
        }

        $tpl = $this->createTemplate('specific_prices_shop_update.tpl');
        $tpl->assign(array(
            'option_list' => $res,
            'key_id' => 'id_currency',
            'key_value' => 'name'
            )
        );

        $this->content = $tpl->fetch();
    }

    public function ajaxProcessGetGroupsOptions()
    {
        if (!$res = Group::getGroups((int)$this->context->language->id, (int)Tools::getValue('id_shop'))) {
            return;
        }

        $tpl = $this->createTemplate('specific_prices_shop_update.tpl');
        $tpl->assign(array(
            'option_list' => $res,
            'key_id' => 'id_group',
            'key_value' => 'name'
            )
        );

        $this->content = $tpl->fetch();
    }

    public function processDeleteVirtualProduct()
    {
        if (!($id_product_download = ProductDownload::getIdFromIdProduct((int)Tools::getValue('id_product')))) {
            $this->errors[] = $this->trans('Cannot retrieve file.', array(), 'Admin.Notifications.Error');
        } else {
            $product_download = new ProductDownload((int)$id_product_download);

            if (!$product_download->deleteFile((int)$id_product_download)) {
                $this->errors[] = $this->trans('Cannot delete file', array(), 'Admin.Notifications.Error');
            } else {
                $this->redirect_after = self::$currentIndex.'&id_product='.(int)Tools::getValue('id_product').'&updateproduct&key_tab=VirtualProduct&conf=1&token='.$this->token;
            }
        }

        $this->display = 'edit';
        $this->tab_display = 'VirtualProduct';
    }

    public function ajaxProcessAddAttachment()
    {
        if (!$this->access('edit')) {
            return die(json_encode(array('error' => $this->l('You do not have the right permission'))));
        }
        if (isset($_FILES['attachment_file'])) {
            if ((int)$_FILES['attachment_file']['error'] === 1) {
                $_FILES['attachment_file']['error'] = array();

                $max_upload = (int)ini_get('upload_max_filesize');
                $max_post = (int)ini_get('post_max_size');
                $upload_mb = min($max_upload, $max_post);
                $_FILES['attachment_file']['error'][] = sprintf(
                    $this->l('File %1$s exceeds the size allowed by the server. The limit is set to %2$d MB.'),
                    '<b>'.$_FILES['attachment_file']['name'].'</b> ',
                    '<b>'.$upload_mb.'</b>'
                );
            }

            $_FILES['attachment_file']['error'] = array();

            $is_attachment_name_valid = false;
            $attachment_names = Tools::getValue('attachment_name');
            $attachment_descriptions = Tools::getValue('attachment_description');

            if (!isset($attachment_names) || !$attachment_names) {
                $attachment_names = array();
            }

            if (!isset($attachment_descriptions) || !$attachment_descriptions) {
                $attachment_descriptions = array();
            }

            foreach ($attachment_names as $lang => $name) {
                $language = Language::getLanguage((int)$lang);

                if (Tools::strlen($name) > 0) {
                    $is_attachment_name_valid = true;
                }

                if (!Validate::isGenericName($name)) {
                    $_FILES['attachment_file']['error'][] = $this->trans('Invalid name for %s language', array($language['name']), 'Admin.Notifications.Error');
                } elseif (Tools::strlen($name) > 32) {
                    $_FILES['attachment_file']['error'][] = $this->trans('The name for %1s language is too long (%2d chars max).', array($language['name'], 32), 'Admin.Notifications.Error');
                }
            }

            foreach ($attachment_descriptions as $lang => $description) {
                $language = Language::getLanguage((int)$lang);

                if (!Validate::isCleanHtml($description)) {
                    $_FILES['attachment_file']['error'][] = $this->trans('Invalid description for %s language', array($language['name']), 'Admin.Catalog.Notification');
                }
            }

            if (!$is_attachment_name_valid) {
                $_FILES['attachment_file']['error'][] = $this->trans('An attachment name is required.', array(), 'Admin.Catalog.Notification');
            }

            if (empty($_FILES['attachment_file']['error'])) {
                if (is_uploaded_file($_FILES['attachment_file']['tmp_name'])) {
                    if ($_FILES['attachment_file']['size'] > (Configuration::get('PS_ATTACHMENT_MAXIMUM_SIZE') * 1024 * 1024)) {
                        $_FILES['attachment_file']['error'][] = sprintf(
                            $this->l('The file is too large. Maximum size allowed is: %1$d kB. The file you are trying to upload is %2$d kB.'),
                            (Configuration::get('PS_ATTACHMENT_MAXIMUM_SIZE') * 1024),
                            number_format(($_FILES['attachment_file']['size'] / 1024), 2, '.', '')
                        );
                    } else {
                        do {
                            $uniqid = sha1(microtime());
                        } while (file_exists(_PS_DOWNLOAD_DIR_.$uniqid));
                        if (!copy($_FILES['attachment_file']['tmp_name'], _PS_DOWNLOAD_DIR_.$uniqid)) {
                            $_FILES['attachment_file']['error'][] = $this->l('File copy failed');
                        }
                        @unlink($_FILES['attachment_file']['tmp_name']);
                    }
                } else {
                    $_FILES['attachment_file']['error'][] = $this->trans('The file is missing.', array(), 'Admin.Notifications.Error');
                }

                if (empty($_FILES['attachment_file']['error']) && isset($uniqid)) {
                    $attachment = new Attachment();

                    foreach ($attachment_names as $lang => $name) {
                        $attachment->name[(int)$lang] = $name;
                    }

                    foreach ($attachment_descriptions as $lang => $description) {
                        $attachment->description[(int)$lang] = $description;
                    }

                    $attachment->file = $uniqid;
                    $attachment->mime = $_FILES['attachment_file']['type'];
                    $attachment->file_name = $_FILES['attachment_file']['name'];

                    if (empty($attachment->mime) || Tools::strlen($attachment->mime) > 128) {
                        $_FILES['attachment_file']['error'][] = $this->trans('Invalid file extension', array(), 'Admin.Notifications.Error');
                    }
                    if (!Validate::isGenericName($attachment->file_name)) {
                        $_FILES['attachment_file']['error'][] = $this->trans('Invalid file name', array(), 'Admin.Notifications.Error');
                    }
                    if (Tools::strlen($attachment->file_name) > 128) {
                        $_FILES['attachment_file']['error'][] = $this->trans('The file name is too long.', array(), 'Admin.Notifications.Error');
                    }
                    if (empty($this->errors)) {
                        $res = $attachment->add();
                        if (!$res) {
                            $_FILES['attachment_file']['error'][] = $this->trans('This attachment was unable to be loaded into the database.', array(), 'Admin.Catalog.Notification');
                        } else {
                            $_FILES['attachment_file']['id_attachment'] = $attachment->id;
                            $_FILES['attachment_file']['filename'] = $attachment->name[$this->context->employee->id_lang];
                            $id_product = (int)Tools::getValue($this->identifier);
                            $res = $attachment->attachProduct($id_product);
                            if (!$res) {
                                $_FILES['attachment_file']['error'][] = $this->trans('We were unable to associate this attachment to a product.', array(), 'Admin.Catalog.Notification');
                            }
                        }
                    } else {
                        $_FILES['attachment_file']['error'][] = $this->trans('Invalid file', array(), 'Admin.Notifications.Error');
                    }
                }
            }

            die(json_encode($_FILES));
        }
    }


    /**
     * Attach an existing attachment to the product
     *
     * @return void
     */
    public function processAttachments()
    {
        if ($id = (int)Tools::getValue($this->identifier)) {
            $attachments = trim(Tools::getValue('arrayAttachments'), ',');
            $attachments = explode(',', $attachments);
            if (!Attachment::attachToProduct($id, $attachments)) {
                $this->errors[] = $this->trans('An error occurred while saving product attachments.', array(), 'Admin.Catalog.Notification');
            }
        }
    }

    public function processDuplicate()
    {
        if (Validate::isLoadedObject($product = new Product((int)Tools::getValue('id_product')))) {
            $id_product_old = $product->id;
            if (empty($product->price) && Shop::getContext() == Shop::CONTEXT_GROUP) {
                $shops = ShopGroup::getShopsFromGroup(Shop::getContextShopGroupID());
                foreach ($shops as $shop) {
                    if ($product->isAssociatedToShop($shop['id_shop'])) {
                        $product_price = new Product($id_product_old, false, null, $shop['id_shop']);
                        $product->price = $product_price->price;
                    }
                }
            }
            unset($product->id);
            unset($product->id_product);
            $product->indexed = 0;
            $product->active = 0;
            if ($product->add()
            && Category::duplicateProductCategories($id_product_old, $product->id)
            && Product::duplicateSuppliers($id_product_old, $product->id)
            && ($combination_images = Product::duplicateAttributes($id_product_old, $product->id)) !== false
            && GroupReduction::duplicateReduction($id_product_old, $product->id)
            && Product::duplicateAccessories($id_product_old, $product->id)
            && Product::duplicateFeatures($id_product_old, $product->id)
            && Product::duplicateSpecificPrices($id_product_old, $product->id)
            && Pack::duplicate($id_product_old, $product->id)
            && Product::duplicateCustomizationFields($id_product_old, $product->id)
            && Product::duplicateTags($id_product_old, $product->id)
            && Product::duplicateDownload($id_product_old, $product->id)) {
                if ($product->hasAttributes()) {
                    Product::updateDefaultAttribute($product->id);
                }

                if (!Tools::getValue('noimage') && !Image::duplicateProductImages($id_product_old, $product->id, $combination_images)) {
                    $this->errors[] = $this->trans('An error occurred while copying the image.', array(), 'Admin.Notifications.Error');
                } else {
                    Hook::exec('actionProductAdd', array('id_product' => (int)$product->id, 'product' => $product));
                    if (in_array($product->visibility, array('both', 'search')) && Configuration::get('PS_SEARCH_INDEXATION')) {
                        Search::indexation(false, $product->id);
                    }
                    $this->redirect_after = self::$currentIndex.(Tools::getIsset('id_category') ? '&id_category='.(int)Tools::getValue('id_category') : '').'&conf=19&token='.$this->token;
                }
            } else {
                $this->errors[] = $this->trans('An error occurred while creating an object.', array(), 'Admin.Notifications.Error');
            }
        }
    }

    public function processDelete()
    {
        if (Validate::isLoadedObject($object = $this->loadObject()) && isset($this->fieldImageSettings)) {
            /** @var Product $object */
            // check if request at least one object with noZeroObject
            if (isset($object->noZeroObject) && count($taxes = call_user_func(array($this->className, $object->noZeroObject))) <= 1) {
                $this->errors[] = $this->trans('You need at least one object.', array(), 'Admin.Notifications.Error').' <b>'.$this->table.'</b><br />'.$this->trans('You cannot delete all of the items.', array(), 'Admin.Notifications.Error');
            } else {
                /*
                 * @since 1.5.0
                 * It is NOT possible to delete a product if there are currently:
                 * - physical stock for this product
                 * - supply order(s) for this product
                 */
                if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $object->advanced_stock_management) {
                    $stock_manager = StockManagerFactory::getManager();
                    $physical_quantity = $stock_manager->getProductPhysicalQuantities($object->id, 0);
                    $real_quantity = $stock_manager->getProductRealQuantities($object->id, 0);
                    if ($physical_quantity > 0 || $real_quantity > $physical_quantity) {
                        $this->errors[] = $this->trans('You cannot delete this product because there is physical stock left.', array(), 'Admin.Catalog.Notification');
                    }
                }

                if (!count($this->errors)) {
                    if ($object->delete()) {
                        $id_category = (int)Tools::getValue('id_category');
                        $category_url = empty($id_category) ? '' : '&id_category='.(int)$id_category;
                        PrestaShopLogger::addLog(sprintf($this->l('%s deletion', 'AdminTab', false, false), $this->className), 1, null, $this->className, (int)$object->id, true, (int)$this->context->employee->id);
                        $this->redirect_after = self::$currentIndex.'&conf=1&token='.$this->token.$category_url;
                    } else {
                        $this->errors[] = $this->trans('An error occurred during deletion.', array(), 'Admin.Notifications.Error');
                    }
                }
            }
        } else {
            $this->errors[] = $this->trans('An error occurred while deleting the object.', array(), 'Admin.Notifications.Error').' <b>'.$this->table.'</b> '.$this->trans('(cannot load object)', array(), 'Admin.Notifications.Error');
        }
    }

    public function processImage()
    {
        $id_image = (int)Tools::getValue('id_image');
        $image = new Image((int)$id_image);
        if (Validate::isLoadedObject($image)) {
            /* Update product image/legend */
            // @todo : move in processEditProductImage
            if (Tools::getIsset('editImage')) {
                if ($image->cover) {
                    $_POST['cover'] = 1;
                }

                $_POST['id_image'] = $image->id;
            } elseif (Tools::getIsset('coverImage')) {
                /* Choose product cover image */
                Image::deleteCover($image->id_product);
                $image->cover = 1;
                if (!$image->update()) {
                    $this->errors[] = $this->trans('You cannot change the product\'s cover image.', array(), 'Admin.Catalog.Notification');
                } else {
                    $productId = (int)Tools::getValue('id_product');
                    @unlink(_PS_TMP_IMG_DIR_.'product_'.$productId.'.jpg');
                    @unlink(_PS_TMP_IMG_DIR_.'product_mini_'.$productId.'_'.$this->context->shop->id.'.jpg');
                    $this->redirect_after = self::$currentIndex.'&id_product='.$image->id_product.'&id_category='.(Tools::getIsset('id_category') ? '&id_category='.(int)Tools::getValue('id_category') : '').'&action=Images&addproduct'.'&token='.$this->token;
                }
            } elseif (Tools::getIsset('imgPosition') && Tools::getIsset('imgDirection')) {
                /* Choose product image position */
                $image->updatePosition(Tools::getValue('imgDirection'), Tools::getValue('imgPosition'));
                $this->redirect_after = self::$currentIndex.'&id_product='.$image->id_product.'&id_category='.(Tools::getIsset('id_category') ? '&id_category='.(int)Tools::getValue('id_category') : '').'&add'.$this->table.'&action=Images&token='.$this->token;
            }
        } else {
            $this->errors[] = $this->trans('The image could not be found. ', array(), 'Admin.Catalog.Notification');
        }
    }

    protected function processBulkDelete()
    {
        if ($this->access('delete')) {
            if (is_array($this->boxes) && !empty($this->boxes)) {
                $object = new $this->className();

                if (isset($object->noZeroObject) &&
                    // Check if all object will be deleted
                    (count(call_user_func(array($this->className, $object->noZeroObject))) <= 1 || count($_POST[$this->table.'Box']) == count(call_user_func(array($this->className, $object->noZeroObject))))) {
                    $this->errors[] = $this->trans('You need at least one object.', array(), 'Admin.Notifications.Error').' <b>'.$this->table.'</b><br />'.$this->trans('You cannot delete all of the items.', array(), 'Admin.Notifications.Error');
                } else {
                    $success = 1;
                    $products = Tools::getValue($this->table.'Box');
                    if (is_array($products) && ($count = count($products))) {
                        // Deleting products can be quite long on a cheap server. Let's say 1.5 seconds by product (I've seen it!).
                        if (intval(ini_get('max_execution_time')) < round($count * 1.5)) {
                            ini_set('max_execution_time', round($count * 1.5));
                        }

                        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                            $stock_manager = StockManagerFactory::getManager();
                        }

                        foreach ($products as $id_product) {
                            $product = new Product((int)$id_product);
                            /*
                             * @since 1.5.0
                             * It is NOT possible to delete a product if there are currently:
                             * - physical stock for this product
                             * - supply order(s) for this product
                             */
                            if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $product->advanced_stock_management) {
                                $physical_quantity = $stock_manager->getProductPhysicalQuantities($product->id, 0);
                                $real_quantity = $stock_manager->getProductRealQuantities($product->id, 0);
                                if ($physical_quantity > 0 || $real_quantity > $physical_quantity) {
                                    $this->errors[] = $this->trans('You cannot delete the product #%d because there is physical stock left.', array($product->id), 'Admin.Catalog.Notification');
                                }
                            }
                            if (!count($this->errors)) {
                                if ($product->delete()) {
                                    PrestaShopLogger::addLog(sprintf($this->l('%s deletion', 'AdminTab', false, false), $this->className), 1, null, $this->className, (int)$product->id, true, (int)$this->context->employee->id);
                                } else {
                                    $success = false;
                                }
                            } else {
                                $success = 0;
                            }
                        }
                    }

                    if ($success) {
                        $id_category = (int)Tools::getValue('id_category');
                        $category_url = empty($id_category) ? '' : '&id_category='.(int)$id_category;
                        $this->redirect_after = self::$currentIndex.'&conf=2&token='.$this->token.$category_url;
                    } else {
                        $this->errors[] = $this->trans('An error occurred while deleting this selection.', array(), 'Admin.Notifications.Error');
                    }
                }
            } else {
                $this->errors[] = $this->trans('You must select at least one element to delete.', array(), 'Admin.Notifications.Error');
            }
        } else {
            $this->errors[] = $this->trans('You do not have permission to delete this.', array(), 'Admin.Notifications.Error');
        }
    }

    public function processProductAttribute()
    {
        // Don't process if the combination fields have not been submitted
        if (!Combination::isFeatureActive() || !Tools::getValue('attribute_combination_list')) {
            return;
        }

        if (Validate::isLoadedObject($product = $this->object)) {
            if ($this->isProductFieldUpdated('attribute_price') && (!Tools::getIsset('attribute_price') || Tools::getIsset('attribute_price') == null)) {
                $this->errors[] = $this->trans('The price attribute is required.', array(), 'Admin.Catalog.Notification');
            }
            if (!Tools::getIsset('attribute_combination_list') || Tools::isEmpty(Tools::getValue('attribute_combination_list'))) {
                $this->errors[] = $this->trans('You must add at least one attribute.', array(), 'Admin.Catalog.Notification');
            }

            $array_checks = array(
                'reference' => 'isReference',
                'supplier_reference' => 'isReference',
                'location' => 'isReference',
                'ean13' => 'isEan13',
                'isbn' => 'isIsbn',
                'upc' => 'isUpc',
                'wholesale_price' => 'isPrice',
                'price' => 'isPrice',
                'ecotax' => 'isPrice',
                'quantity' => 'isInt',
                'weight' => 'isUnsignedFloat',
                'unit_price_impact' => 'isPrice',
                'default_on' => 'isBool',
                'minimal_quantity' => 'isUnsignedInt',
                'available_date' => 'isDateFormat'
            );
            foreach ($array_checks as $property => $check) {
                if (Tools::getValue('attribute_'.$property) !== false && !call_user_func(array('Validate', $check), Tools::getValue('attribute_'.$property))) {
                    $this->errors[] = $this->trans('The %s field is not valid', array($property), 'Admin.Notifications.Error');
                }
            }

            if (!count($this->errors)) {
                if (!isset($_POST['attribute_wholesale_price'])) {
                    $_POST['attribute_wholesale_price'] = 0;
                }
                if (!isset($_POST['attribute_price_impact'])) {
                    $_POST['attribute_price_impact'] = 0;
                }
                if (!isset($_POST['attribute_weight_impact'])) {
                    $_POST['attribute_weight_impact'] = 0;
                }
                if (!isset($_POST['attribute_ecotax'])) {
                    $_POST['attribute_ecotax'] = 0;
                }
                if (Tools::getValue('attribute_default')) {
                    $product->deleteDefaultAttributes();
                }

                // Change existing one
                if (($id_product_attribute = (int)Tools::getValue('id_product_attribute')) || ($id_product_attribute = $product->productAttributeExists(Tools::getValue('attribute_combination_list'), false, null, true, true))) {
                    if ($this->access('edit')) {
                        if ($this->isProductFieldUpdated('available_date_attribute') && (Tools::getValue('available_date_attribute') != '' &&!Validate::isDateFormat(Tools::getValue('available_date_attribute')))) {
                            $this->errors[] = $this->trans('Invalid date format.', array(), 'Admin.Notifications.Error');
                        } else {
                            $product->updateAttribute((int)$id_product_attribute,
                                $this->isProductFieldUpdated('attribute_wholesale_price') ? Tools::getValue('attribute_wholesale_price') : null,
                                $this->isProductFieldUpdated('attribute_price_impact') ? Tools::getValue('attribute_price') * Tools::getValue('attribute_price_impact') : null,
                                $this->isProductFieldUpdated('attribute_weight_impact') ? Tools::getValue('attribute_weight') * Tools::getValue('attribute_weight_impact') : null,
                                $this->isProductFieldUpdated('attribute_unit_impact') ? Tools::getValue('attribute_unity') * Tools::getValue('attribute_unit_impact') : null,
                                $this->isProductFieldUpdated('attribute_ecotax') ? Tools::getValue('attribute_ecotax') : null,
                                Tools::getValue('id_image_attr'),
                                Tools::getValue('attribute_reference'),
                                Tools::getValue('attribute_ean13'),
                                $this->isProductFieldUpdated('attribute_default') ? Tools::getValue('attribute_default') : null,
                                Tools::getValue('attribute_location'),
                                Tools::getValue('attribute_upc'),
                                $this->isProductFieldUpdated('attribute_minimal_quantity') ? Tools::getValue('attribute_minimal_quantity') : null,
                                $this->isProductFieldUpdated('available_date_attribute') ? Tools::getValue('available_date_attribute') : null,
                                false,
                                array(),
                                Tools::getValue('attribute_isbn'));
                            StockAvailable::setProductDependsOnStock((int)$product->id, $product->depends_on_stock, null, (int)$id_product_attribute);
                            StockAvailable::setProductOutOfStock((int)$product->id, $product->out_of_stock, null, (int)$id_product_attribute);
                        }
                    } else {
                        $this->errors[] = $this->trans('You do not have permission to add this.', array(), 'Admin.Notifications.Error');
                    }
                }
                // Add new
                else {
                    if ($this->access('add')) {
                        if ($product->productAttributeExists(Tools::getValue('attribute_combination_list'))) {
                            $this->errors[] = $this->trans('This combination already exists.', array(), 'Admin.Catalog.Notification');
                        } else {
                            $id_product_attribute = $product->addCombinationEntity(
                                Tools::getValue('attribute_wholesale_price'),
                                Tools::getValue('attribute_price') * Tools::getValue('attribute_price_impact'),
                                Tools::getValue('attribute_weight') * Tools::getValue('attribute_weight_impact'),
                                Tools::getValue('attribute_unity') * Tools::getValue('attribute_unit_impact'),
                                Tools::getValue('attribute_ecotax'),
                                0,
                                Tools::getValue('id_image_attr'),
                                Tools::getValue('attribute_reference'),
                                null,
                                Tools::getValue('attribute_ean13'),
                                Tools::getValue('attribute_default'),
                                Tools::getValue('attribute_location'),
                                Tools::getValue('attribute_upc'),
                                Tools::getValue('attribute_minimal_quantity'),
                                array(),
                                Tools::getValue('available_date_attribute'),
                                Tools::getValue('attribute_isbn')
                            );
                            StockAvailable::setProductDependsOnStock((int)$product->id, $product->depends_on_stock, null, (int)$id_product_attribute);
                            StockAvailable::setProductOutOfStock((int)$product->id, $product->out_of_stock, null, (int)$id_product_attribute);
                        }
                    } else {
                        $this->errors[] = $this->trans('You do not have permission to edit this.', array(), 'Admin.Notifications.Error');
                    }
                }
                if (!count($this->errors)) {
                    $combination = new Combination((int)$id_product_attribute);
                    $combination->setAttributes(Tools::getValue('attribute_combination_list'));

                    // images could be deleted before
                    $id_images = Tools::getValue('id_image_attr');
                    if (!empty($id_images)) {
                        $combination->setImages($id_images);
                    }

                    $product->checkDefaultAttributes();
                    if (Tools::getValue('attribute_default')) {
                        Product::updateDefaultAttribute((int)$product->id);
                        if (isset($id_product_attribute)) {
                            $product->cache_default_attribute = (int)$id_product_attribute;
                        }

                        if ($available_date = Tools::getValue('available_date_attribute')) {
                            $product->setAvailableDate($available_date);
                        } else {
                            $product->setAvailableDate();
                        }
                    }
                }
            }
        }
    }

    public function processFeatures($id_product = null)
    {
        if (!Feature::isFeatureActive()) {
            return;
        }

        $id_product = (int) $id_product ? $id_product : (int)Tools::getValue('id_product');

        if (Validate::isLoadedObject($product = new Product($id_product))) {
            // delete all objects
            $product->deleteFeatures();

            // add new objects
            $languages = Language::getLanguages(false);
            foreach ($_POST as $key => $val) {
                if (preg_match('/^feature_([0-9]+)_value/i', $key, $match)) {
                    if ($val) {
                        $product->addFeaturesToDB($match[1], $val);
                    } else {
                        if ($default_value = $this->checkFeatures($languages, $match[1])) {
                            $id_value = $product->addFeaturesToDB($match[1], 0, 1);
                            foreach ($languages as $language) {
                                if ($cust = Tools::getValue('custom_'.$match[1].'_'.(int)$language['id_lang'])) {
                                    $product->addFeaturesCustomToDB($id_value, (int)$language['id_lang'], $cust);
                                } else {
                                    $product->addFeaturesCustomToDB($id_value, (int)$language['id_lang'], $default_value);
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $this->errors[] = $this->trans('A product must be created before adding features.', array(), 'Admin.Catalog.Notification');
        }
    }

    /**
     * This function is never called at the moment (specific prices cannot be edited)
     */
    public function processPricesModification()
    {
        $id_specific_prices = Tools::getValue('spm_id_specific_price');
        $id_combinations = Tools::getValue('spm_id_product_attribute');
        $id_shops = Tools::getValue('spm_id_shop');
        $id_currencies = Tools::getValue('spm_id_currency');
        $id_countries = Tools::getValue('spm_id_country');
        $id_groups = Tools::getValue('spm_id_group');
        $id_customers = Tools::getValue('spm_id_customer');
        $prices = Tools::getValue('spm_price');
        $from_quantities = Tools::getValue('spm_from_quantity');
        $reductions = Tools::getValue('spm_reduction');
        $reduction_types = Tools::getValue('spm_reduction_type');
        $froms = Tools::getValue('spm_from');
        $tos = Tools::getValue('spm_to');

        foreach ($id_specific_prices as $key => $id_specific_price) {
            if ($reduction_types[$key] == 'percentage' && ((float)$reductions[$key] <= 0 || (float)$reductions[$key] > 100)) {
                $this->errors[] = $this->trans('Submitted reduction value (0-100) is out-of-range', array(), 'Admin.Catalog.Notification');
            } elseif ($this->_validateSpecificPrice($id_shops[$key], $id_currencies[$key], $id_countries[$key], $id_groups[$key], $id_customers[$key], $prices[$key], $from_quantities[$key], $reductions[$key], $reduction_types[$key], $froms[$key], $tos[$key], $id_combinations[$key])) {
                $specific_price = new SpecificPrice((int)($id_specific_price));
                $specific_price->id_shop = (int)$id_shops[$key];
                $specific_price->id_product_attribute = (int)$id_combinations[$key];
                $specific_price->id_currency = (int)($id_currencies[$key]);
                $specific_price->id_country = (int)($id_countries[$key]);
                $specific_price->id_group = (int)($id_groups[$key]);
                $specific_price->id_customer = (int)$id_customers[$key];
                $specific_price->price = (float)($prices[$key]);
                $specific_price->from_quantity = (int)($from_quantities[$key]);
                $specific_price->reduction = (float)($reduction_types[$key] == 'percentage' ? ($reductions[$key] / 100) : $reductions[$key]);
                $specific_price->reduction_type = !$reductions[$key] ? 'amount' : $reduction_types[$key];
                $specific_price->from = !$froms[$key] ? '0000-00-00 00:00:00' : $froms[$key];
                $specific_price->to = !$tos[$key] ? '0000-00-00 00:00:00' : $tos[$key];
                if (!$specific_price->update()) {
                    $this->errors[] = $this->trans('An error occurred while updating the specific price.', array(), 'Admin.Catalog.Notification');
                }
            }
        }
        if (!count($this->errors)) {
            $this->redirect_after = self::$currentIndex.'&id_product='.(int)(Tools::getValue('id_product')).(Tools::getIsset('id_category') ? '&id_category='.(int)Tools::getValue('id_category') : '').'&update'.$this->table.'&action=Prices&token='.$this->token;
        }
    }

    public function processPriceAddition()
    {
        // Check if a specific price has been submitted
        if (!Tools::getIsset('submitPriceAddition')) {
            return;
        }

        $id_product = Tools::getValue('id_product');
        $id_product_attribute = Tools::getValue('sp_id_product_attribute');
        $id_shop = Tools::getValue('sp_id_shop');
        $id_currency = Tools::getValue('sp_id_currency');
        $id_country = Tools::getValue('sp_id_country');
        $id_group = Tools::getValue('sp_id_group');
        $id_customer = Tools::getValue('sp_id_customer');
        $price = Tools::getValue('leave_bprice') ? '-1' : Tools::getValue('sp_price');
        $from_quantity = Tools::getValue('sp_from_quantity');
        $reduction = (float)(Tools::getValue('sp_reduction'));
        $reduction_tax = Tools::getValue('sp_reduction_tax');
        $reduction_type = !$reduction ? 'amount' : Tools::getValue('sp_reduction_type');
        $reduction_type = $reduction_type == '-' ? 'amount' : $reduction_type;
        $from = Tools::getValue('sp_from');
        if (!$from) {
            $from = '0000-00-00 00:00:00';
        }
        $to = Tools::getValue('sp_to');
        if (!$to) {
            $to = '0000-00-00 00:00:00';
        }

        if (($price == '-1') && ((float)$reduction == '0')) {
            $this->errors[] = $this->trans('No reduction value has been submitted', array(), 'Admin.Catalog.Notification');
        } elseif ($to != '0000-00-00 00:00:00' && strtotime($to) < strtotime($from)) {
            $this->errors[] = $this->trans('Invalid date range', array(), 'Admin.Notifications.Error');
        } elseif ($reduction_type == 'percentage' && ((float)$reduction <= 0 || (float)$reduction > 100)) {
            $this->errors[] = $this->trans('Submitted reduction value (0-100) is out-of-range', array(), 'Admin.Catalog.Notification');
        } elseif ($this->_validateSpecificPrice($id_shop, $id_currency, $id_country, $id_group, $id_customer, $price, $from_quantity, $reduction, $reduction_type, $from, $to, $id_product_attribute)) {
            $specificPrice = new SpecificPrice();
            $specificPrice->id_product = (int)$id_product;
            $specificPrice->id_product_attribute = (int)$id_product_attribute;
            $specificPrice->id_shop = (int)$id_shop;
            $specificPrice->id_currency = (int)($id_currency);
            $specificPrice->id_country = (int)($id_country);
            $specificPrice->id_group = (int)($id_group);
            $specificPrice->id_customer = (int)$id_customer;
            $specificPrice->price = (float)($price);
            $specificPrice->from_quantity = (int)($from_quantity);
            $specificPrice->reduction = (float)($reduction_type == 'percentage' ? $reduction / 100 : $reduction);
            $specificPrice->reduction_tax = $reduction_tax;
            $specificPrice->reduction_type = $reduction_type;
            $specificPrice->from = $from;
            $specificPrice->to = $to;
            if (!$specificPrice->add()) {
                $this->errors[] = $this->trans('An error occurred while updating the specific price.', array(), 'Admin.Catalog.Notification');
            }
        }
    }

    public function ajaxProcessDeleteSpecificPrice()
    {
        if ($this->access('delete')) {
            $id_specific_price = (int)Tools::getValue('id_specific_price');
            if (!$id_specific_price || !Validate::isUnsignedId($id_specific_price)) {
                $error = $this->trans('The specific price ID is invalid.', array(), 'Admin.Catalog.Notification');
            } else {
                $specificPrice = new SpecificPrice((int)$id_specific_price);
                if (!$specificPrice->delete()) {
                    $error = $this->trans('An error occurred while attempting to delete the specific price.', array(), 'Admin.Catalog.Notification');
                }
            }
        } else {
            $error = $this->trans('You do not have permission to delete this.', array(), 'Admin.Notifications.Error');
        }

        if (isset($error)) {
            $json = array(
                'status' => 'error',
                'message'=> $error
            );
        } else {
            $json = array(
                'status' => 'ok',
                'message'=> $this->_conf[1]
            );
        }

        die(json_encode($json));
    }

    public function processSpecificPricePriorities()
    {
        if (!($obj = $this->loadObject())) {
            return;
        }
        if (!$priorities = Tools::getValue('specificPricePriority')) {
            $this->errors[] = $this->trans('Please specify priorities.', array(), 'Admin.Catalog.Notification');
        } elseif (Tools::isSubmit('specificPricePriorityToAll')) {
            if (!SpecificPrice::setPriorities($priorities)) {
                $this->errors[] = $this->trans('An error occurred while updating priorities.', array(), 'Admin.Catalog.Notification');
            } else {
                $this->confirmations[] = $this->l('The price rule has successfully updated');
            }
        } elseif (!SpecificPrice::setSpecificPriority((int)$obj->id, $priorities)) {
            $this->errors[] = $this->trans('An error occurred while setting priorities.', array(), 'Admin.Catalog.Notification');
        }
    }

    public function processCustomizationConfiguration()
    {
        $product = $this->object;
        // Get the number of existing customization fields ($product->text_fields is the updated value, not the existing value)
        $current_customization = $product->getCustomizationFieldIds();
        $files_count = 0;
        $text_count = 0;
        if (is_array($current_customization)) {
            foreach ($current_customization as $field) {
                if ($field['type'] == 1) {
                    $text_count++;
                } else {
                    $files_count++;
                }
            }
        }

        if (!$product->createLabels((int)$product->uploadable_files - $files_count, (int)$product->text_fields - $text_count)) {
            $this->errors[] = $this->trans('An error occurred while creating customization fields.', array(), 'Admin.Catalog.Notification');
        }
        if (!count($this->errors) && !$product->updateLabels()) {
            $this->errors[] = $this->trans('An error occurred while updating customization fields.', array(), 'Admin.Catalog.Notification');
        }
        $product->customizable = ($product->uploadable_files > 0 || $product->text_fields > 0) ? 1 : 0;
        if (($product->uploadable_files != $files_count || $product->text_fields != $text_count) && !count($this->errors) && !$product->update()) {
            $this->errors[] = $this->trans('An error occurred while updating the custom configuration.', array(), 'Admin.Catalog.Notification');
        }
    }

    public function processProductCustomization()
    {
        if (Validate::isLoadedObject($product = new Product((int)Tools::getValue('id_product')))) {
            foreach ($_POST as $field => $value) {
                if (strncmp($field, 'label_', 6) == 0 && !Validate::isLabel($value)) {
                    $this->errors[] = $this->trans('The label fields defined are invalid.', array(), 'Admin.Catalog.Notification');
                }
            }
            if (empty($this->errors) && !$product->updateLabels()) {
                $this->errors[] = $this->trans('An error occurred while updating customization fields.', array(), 'Admin.Catalog.Notification');
            }
            if (empty($this->errors)) {
                $this->confirmations[] = $this->l('Update successful');
            }
        } else {
            $this->errors[] = $this->trans('A product must be created before adding customization.', array(), 'Admin.Catalog.Notification');
        }
    }

    /**
     * Overrides parent for custom redirect link
     */
    public function processPosition()
    {
        /** @var Product $object */
        if (!Validate::isLoadedObject($object = $this->loadObject())) {
            $this->errors[] = $this->trans('An error occurred while updating the status for an object.', array(), 'Admin.Notifications.Error').
                ' <b>'.$this->table.'</b> '.$this->trans('(cannot load object)', array(), 'Admin.Notifications.Error');
        } elseif (!$object->updatePosition((int)Tools::getValue('way'), (int)Tools::getValue('position'))) {
            $this->errors[] = $this->trans('Failed to update the position.', array(), 'Admin.Notifications.Error');
        } else {
            $category = new Category((int)Tools::getValue('id_category'));
            if (Validate::isLoadedObject($category)) {
                Hook::exec('actionCategoryUpdate', array('category' => $category));
            }
            $this->redirect_after = self::$currentIndex.'&'.$this->table.'Orderby=position&'.$this->table.'Orderway=asc&action=Customization&conf=5'.(($id_category = (Tools::getIsset('id_category') ? (int)Tools::getValue('id_category') : '')) ? ('&id_category='.$id_category) : '').'&token='.Tools::getAdminTokenLite('AdminProducts');
        }
    }

    public function initProcess()
    {
        if (Tools::isSubmit('submitAddproductAndStay') || Tools::isSubmit('submitAddproduct')) {
            $this->id_object = (int)Tools::getValue('id_product');
            $this->object = new Product($this->id_object);

            if ($this->isTabSubmitted('Informations') && $this->object->is_virtual && (int)Tools::getValue('type_product') != 2) {
                if ($id_product_download = (int)ProductDownload::getIdFromIdProduct($this->id_object)) {
                    $product_download = new ProductDownload($id_product_download);
                    if (!$product_download->deleteFile($id_product_download)) {
                        $this->errors[] = $this->trans('Cannot delete file', array(), 'Admin.Notifications.Error');
                    }
                }
            }
        }

        // Delete a product in the download folder
        if (Tools::getValue('deleteVirtualProduct')) {
            if ($this->access('delete')) {
                $this->action = 'deleteVirtualProduct';
            } else {
                $this->errors[] = $this->trans('You do not have permission to delete this.', array(), 'Admin.Notifications.Error');
            }
        }
        // Product preview
        elseif (Tools::isSubmit('submitAddProductAndPreview')) {
            $this->display = 'edit';
            $this->action = 'save';
            if (Tools::getValue('id_product')) {
                $this->id_object = Tools::getValue('id_product');
                $this->object = new Product((int)Tools::getValue('id_product'));
            }
        } elseif (Tools::isSubmit('submitAttachments')) {
            if ($this->access('edit')) {
                $this->action = 'attachments';
                $this->tab_display = 'attachments';
            } else {
                $this->errors[] = $this->trans('You do not have permission to edit this.', array(), 'Admin.Notifications.Error');
            }
        }
        // Product duplication
        elseif (Tools::getIsset('duplicate'.$this->table)) {
            if ($this->access('add')) {
                $this->action = 'duplicate';
            } else {
                $this->errors[] = $this->trans('You do not have permission to add this.', array(), 'Admin.Notifications.Error');
            }
        }
        // Product images management
        elseif (Tools::getValue('id_image') && Tools::getValue('ajax')) {
            if ($this->access('edit')) {
                $this->action = 'image';
            } else {
                $this->errors[] = $this->trans('You do not have permission to edit this.', array(), 'Admin.Notifications.Error');
            }
        }
        // Product attributes management
        elseif (Tools::isSubmit('submitProductAttribute')) {
            if ($this->access('edit')) {
                $this->action = 'productAttribute';
            } else {
                $this->errors[] = $this->trans('You do not have permission to edit this.', array(), 'Admin.Notifications.Error');
            }
        }
        // Product features management
        elseif (Tools::isSubmit('submitFeatures') || Tools::isSubmit('submitFeaturesAndStay')) {
            if ($this->access('edit')) {
                $this->action = 'features';
            } else {
                $this->errors[] = $this->trans('You do not have permission to edit this.', array(), 'Admin.Notifications.Error');
            }
        }
        // Product specific prices management NEVER USED
        elseif (Tools::isSubmit('submitPricesModification')) {
            if ($this->access('add')) {
                $this->action = 'pricesModification';
            } else {
                $this->errors[] = $this->trans('You do not have permission to add this.', array(), 'Admin.Notifications.Error');
            }
        } elseif (Tools::isSubmit('deleteSpecificPrice')) {
            if ($this->access('delete')) {
                $this->action = 'deleteSpecificPrice';
            } else {
                $this->errors[] = $this->trans('You do not have permission to delete this.', array(), 'Admin.Notifications.Error');
            }
        } elseif (Tools::isSubmit('submitSpecificPricePriorities')) {
            if ($this->access('edit')) {
                $this->action = 'specificPricePriorities';
                $this->tab_display = 'prices';
            } else {
                $this->errors[] = $this->trans('You do not have permission to edit this.', array(), 'Admin.Notifications.Error');
            }
        }
        // Customization management
        elseif (Tools::isSubmit('submitCustomizationConfiguration')) {
            if ($this->access('edit')) {
                $this->action = 'customizationConfiguration';
                $this->tab_display = 'customization';
                $this->display = 'edit';
            } else {
                $this->errors[] = $this->trans('You do not have permission to edit this.', array(), 'Admin.Notifications.Error');
            }
        } elseif (Tools::isSubmit('submitProductCustomization')) {
            if ($this->access('edit')) {
                $this->action = 'productCustomization';
                $this->tab_display = 'customization';
                $this->display = 'edit';
            } else {
                $this->errors[] = $this->trans('You do not have permission to edit this.', array(), 'Admin.Notifications.Error');
            }
        } elseif (Tools::isSubmit('id_product')) {
            $post_max_size = Tools::getMaxUploadSize(Configuration::get('PS_LIMIT_UPLOAD_FILE_VALUE') * 1024 * 1024);
            if ($post_max_size && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] && $_SERVER['CONTENT_LENGTH'] > $post_max_size) {
                $this->errors[] = $this->trans(
                    'The uploaded file exceeds the "Maximum size for a downloadable product" set in preferences (%1$dMB) or the post_max_size/ directive in php.ini (%2$dMB).',
                    array(
                        number_format((Configuration::get('PS_LIMIT_UPLOAD_FILE_VALUE'))),
                        ($post_max_size / 1024 / 1024)
                    ),
                    'Admin.Catalog.Notification'
                );
            }
        }

        if (!$this->action) {
            parent::initProcess();
        } else {
            $this->id_object = (int)Tools::getValue($this->identifier);
        }

        if (isset($this->available_tabs[Tools::getValue('key_tab')])) {
            $this->tab_display = Tools::getValue('key_tab');
        }

        // Set tab to display if not decided already
        if (!$this->tab_display && $this->action) {
            if (in_array($this->action, array_keys($this->available_tabs))) {
                $this->tab_display = $this->action;
            }
        }

        // And if still not set, use default
        if (!$this->tab_display) {
            if (in_array($this->default_tab, $this->available_tabs)) {
                $this->tab_display = $this->default_tab;
            } else {
                $this->tab_display = key($this->available_tabs);
            }
        }
    }

    /**
     * postProcess for new form archi (need object return)
     *
     * @return void
     */
    public function postCoreProcess()
    {
        return parent::postProcess();
    }

    /**
     * postProcess handle every checks before saving products information
     *
     * @return void
     */
    public function postProcess()
    {
        if (!$this->redirect_after) {
            parent::postProcess();
        }

        if ($this->display == 'edit' || $this->display == 'add') {
            $this->addJqueryUI(array(
                'ui.core',
                'ui.widget'
            ));

            $this->addjQueryPlugin(array(
                'autocomplete',
                'tablednd',
                'thickbox',
                'ajaxfileupload',
                'date',
                'tagify',
                'select2',
                'validate'
            ));

            $this->addJS(array(
                _PS_JS_DIR_.'admin/products.js',
                _PS_JS_DIR_.'admin/attributes.js',
                _PS_JS_DIR_.'admin/price.js',
                _PS_JS_DIR_.'tiny_mce/tiny_mce.js',
                _PS_JS_DIR_.'admin/tinymce.inc.js',
                _PS_JS_DIR_.'admin/dnd.js',
                _PS_JS_DIR_.'jquery/ui/jquery.ui.progressbar.min.js',
                _PS_JS_DIR_.'vendor/spin.js',
                _PS_JS_DIR_.'vendor/ladda.js'
            ));

            $this->addJS(_PS_JS_DIR_.'jquery/plugins/select2/select2_locale_'.$this->context->language->iso_code.'.js');
            $this->addJS(_PS_JS_DIR_.'jquery/plugins/validate/localization/messages_'.$this->context->language->iso_code.'.js');

            $this->addCSS(array(
                _PS_JS_DIR_.'jquery/plugins/timepicker/jquery-ui-timepicker-addon.css'
            ));
        }
    }

    public function ajaxProcessDeleteProductAttribute()
    {
        if (!Combination::isFeatureActive()) {
            return;
        }

        if ($this->access('delete')) {
            $id_product = (int)Tools::getValue('id_product');
            $id_product_attribute = (int)Tools::getValue('id_product_attribute');

            if ($id_product && Validate::isUnsignedId($id_product) && Validate::isLoadedObject($product = new Product($id_product))) {
                if (($depends_on_stock = StockAvailable::dependsOnStock($id_product)) && StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute)) {
                    $json = array(
                        'status' => 'error',
                        'message'=> $this->l('It is not possible to delete a combination while it still has some quantities in the Advanced Stock Management. You must delete its stock first.')
                    );
                } else {
                    $product->deleteAttributeCombination((int)$id_product_attribute);
                    $product->checkDefaultAttributes();
                    Tools::clearColorListCache((int)$product->id);
                    if (!$product->hasAttributes()) {
                        $product->cache_default_attribute = 0;
                        $product->update();
                    } else {
                        Product::updateDefaultAttribute($id_product);
                    }

                    if ($depends_on_stock && !Stock::deleteStockByIds($id_product, $id_product_attribute)) {
                        $json = array(
                            'status' => 'error',
                            'message'=> $this->l('Error while deleting the stock')
                        );
                    } else {
                        $json = array(
                            'status' => 'ok',
                            'message'=> $this->_conf[1],
                            'id_product_attribute' => (int)$id_product_attribute
                        );
                    }
                }
            } else {
                $json = array(
                    'status' => 'error',
                    'message'=> $this->l('You cannot delete this attribute.')
                );
            }
        } else {
            $json = array(
                'status' => 'error',
                'message'=> $this->l('You do not have permission to delete this.')
            );
        }

        die(json_encode($json));
    }

    public function ajaxProcessDefaultProductAttribute()
    {
        if ($this->access('edit')) {
            if (!Combination::isFeatureActive()) {
                return;
            }

            if (Validate::isLoadedObject($product = new Product((int)Tools::getValue('id_product')))) {
                $product->deleteDefaultAttributes();
                $product->setDefaultAttribute((int)Tools::getValue('id_product_attribute'));
                $json = array(
                    'status' => 'ok',
                    'message'=> $this->_conf[4]
                );
            } else {
                $json = array(
                    'status' => 'error',
                    'message'=> $this->l('You cannot make this the default attribute.')
                );
            }

            die(json_encode($json));
        }
    }

    public function ajaxProcessEditProductAttribute()
    {
        if ($this->access('edit')) {
            $id_product = (int)Tools::getValue('id_product');
            $id_product_attribute = (int)Tools::getValue('id_product_attribute');
            if ($id_product && Validate::isUnsignedId($id_product) && Validate::isLoadedObject($product = new Product((int)$id_product))) {
                $combinations = $product->getAttributeCombinationsById($id_product_attribute, $this->context->language->id);
                foreach ($combinations as $key => $combination) {
                    $combinations[$key]['attributes'][] = array($combination['group_name'], $combination['attribute_name'], $combination['id_attribute']);
                }

                die(json_encode($combinations));
            }
        }
    }

    public function ajaxPreProcess()
    {
        if (Tools::getIsset('update'.$this->table) && Tools::getIsset('id_'.$this->table)) {
            $this->display = 'edit';
            $this->action = Tools::getValue('action');
        }
    }

    public function ajaxProcessUpdateProductImageShopAsso()
    {
        $id_product = Tools::getValue('id_product');
        if (($id_image = Tools::getValue('id_image')) && ($id_shop = (int)Tools::getValue('id_shop'))) {
            if (Tools::getValue('active') == 'true') {
                $res = Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'image_shop (`id_product`, `id_image`, `id_shop`, `cover`) VALUES('.(int)$id_product.', '.(int)$id_image.', '.(int)$id_shop.', NULL)');
            } else {
                $res = Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'image_shop WHERE `id_image` = '.(int)$id_image.' AND `id_shop` = '.(int)$id_shop);
            }
        }

        // Clean covers in image table
        $count_cover_image = Db::getInstance()->getValue('
			SELECT COUNT(*) FROM '._DB_PREFIX_.'image i
			INNER JOIN '._DB_PREFIX_.'image_shop ish ON (i.id_image = ish.id_image AND ish.id_shop = '.(int)$id_shop.')
			WHERE i.cover = 1 AND i.`id_product` = '.(int)$id_product);

        if (!$id_image) {
            $id_image = Db::getInstance()->getValue('
                SELECT i.`id_image` FROM '._DB_PREFIX_.'image i
                INNER JOIN '._DB_PREFIX_.'image_shop ish ON (i.id_image = ish.id_image AND ish.id_shop = '.(int)$id_shop.')
                WHERE i.`id_product` = '.(int)$id_product);
        }

        if ($count_cover_image < 1) {
            Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'image i SET i.cover = 1 WHERE i.id_image = '.(int)$id_image.' AND i.`id_product` = '.(int)$id_product.' LIMIT 1');
        }

        // Clean covers in image_shop table
        $count_cover_image_shop = Db::getInstance()->getValue('
			SELECT COUNT(*)
			FROM '._DB_PREFIX_.'image_shop ish
			WHERE ish.`id_product` = '.(int)$id_product.' AND ish.id_shop = '.(int)$id_shop.' AND ish.cover = 1');

        if ($count_cover_image_shop < 1) {
            Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'image_shop ish SET ish.cover = 1 WHERE ish.id_image = '.(int)$id_image.' AND ish.`id_product` = '.(int)$id_product.' AND ish.id_shop =  '.(int)$id_shop.' LIMIT 1');
        }

        if ($res) {
            $this->jsonConfirmation($this->_conf[27]);
        } else {
            $this->jsonError($this->trans('An error occurred while attempting to associate this image with your shop. ', array(), 'Admin.Catalog.Notification'));
        }
    }

    public function ajaxProcessUpdateImagePosition()
    {
        if (!$this->access('edit')) {
            return die(json_encode(array('error' => $this->l('You do not have the right permission'))));
        }
        $res = false;
        if ($json = Tools::getValue('json')) {
            $res = true;
            $json = stripslashes($json);
            $images = json_decode($json, true);
            foreach ($images as $id => $position) {
                $img = new Image((int)$id);
                $img->position = (int)$position;
                $res &= $img->update();
            }
        }
        if ($res) {
            $this->jsonConfirmation($this->_conf[25]);
        } else {
            $this->jsonError($this->trans('An error occurred while attempting to move this picture.', array(), 'Admin.Catalog.Notification'));
        }
    }

    public function ajaxProcessUpdateCover()
    {
        if (!$this->access('edit')) {
            return die(json_encode(array('error' => $this->l('You do not have the right permission'))));
        }
        Image::deleteCover((int)Tools::getValue('id_product'));
        $img = new Image((int)Tools::getValue('id_image'));
        $img->cover = 1;

        @unlink(_PS_TMP_IMG_DIR_.'product_'.(int)$img->id_product.'.jpg');
        @unlink(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$img->id_product.'_'.$this->context->shop->id.'.jpg');

        if ($img->update()) {
            $this->jsonConfirmation($this->_conf[26]);
        } else {
            $this->jsonError($this->trans('An error occurred while attempting to update the cover picture.', array(), 'Admin.Catalog.Notification'));
        }
    }

    public function ajaxProcessDeleteProductImage($id_image = null)
    {
        $this->display = 'content';
        $res = true;
        /* Delete product image */
        $id_image = $id_image ? $id_image : (int)Tools::getValue('id_image');

        $image = new Image($id_image);
        $this->content['id'] = $image->id;
        $res &= $image->delete();
        // if deleted image was the cover, change it to the first one
        if (!Image::getCover($image->id_product)) {
            $res &= Db::getInstance()->execute('
			UPDATE `'._DB_PREFIX_.'image_shop` image_shop
			SET image_shop.`cover` = 1
			WHERE image_shop.`id_product` = '.(int)$image->id_product.'
			AND id_shop='.(int)$this->context->shop->id.' LIMIT 1');
        }

        if (!Image::getGlobalCover($image->id_product)) {
            $res &= Db::getInstance()->execute('
			UPDATE `'._DB_PREFIX_.'image` i
			SET i.`cover` = 1
			WHERE i.`id_product` = '.(int)$image->id_product.' LIMIT 1');
        }

        if (file_exists(_PS_TMP_IMG_DIR_.'product_'.$image->id_product.'.jpg')) {
            $res &= @unlink(_PS_TMP_IMG_DIR_.'product_'.$image->id_product.'.jpg');
        }
        if (file_exists(_PS_TMP_IMG_DIR_.'product_mini_'.$image->id_product.'_'.$this->context->shop->id.'.jpg')) {
            $res &= @unlink(_PS_TMP_IMG_DIR_.'product_mini_'.$image->id_product.'_'.$this->context->shop->id.'.jpg');
        }

        if ($res) {
            $this->jsonConfirmation($this->_conf[7]);
        } else {
            $this->jsonError($this->trans('An error occurred while attempting to delete the product image.', array(), 'Admin.Catalog.Notification'));
        }
    }

    protected function _validateSpecificPrice($id_shop, $id_currency, $id_country, $id_group, $id_customer, $price, $from_quantity, $reduction, $reduction_type, $from, $to, $id_combination = 0)
    {
        if (!Validate::isUnsignedId($id_shop) || !Validate::isUnsignedId($id_currency) || !Validate::isUnsignedId($id_country) || !Validate::isUnsignedId($id_group) || !Validate::isUnsignedId($id_customer)) {
            $this->errors[] = $this->trans('Wrong IDs', array(), 'Admin.Catalog.Notification');
        } elseif ((!isset($price) && !isset($reduction)) || (isset($price) && !Validate::isNegativePrice($price)) || (isset($reduction) && !Validate::isPrice($reduction))) {
            $this->errors[] = $this->trans('Invalid price/discount amount', array(), 'Admin.Catalog.Notification');
        } elseif (!Validate::isUnsignedInt($from_quantity)) {
            $this->errors[] = $this->trans('Invalid quantity', array(), 'Admin.Catalog.Notification');
        } elseif ($reduction && !Validate::isReductionType($reduction_type)) {
            $this->errors[] = $this->trans('Please select a discount type (amount or percentage).', array(), 'Admin.Catalog.Notification');
        } elseif ($from && $to && (!Validate::isDateFormat($from) || !Validate::isDateFormat($to))) {
            $this->errors[] = $this->trans('The from/to date is invalid.', array(), 'Admin.Catalog.Notification');
        } elseif (SpecificPrice::exists((int)$this->object->id, $id_combination, $id_shop, $id_group, $id_country, $id_currency, $id_customer, $from_quantity, $from, $to, false)) {
            $this->errors[] = $this->trans('A specific price already exists for these parameters.', array(), 'Admin.Catalog.Notification');
        } else {
            return true;
        }
        return false;
    }

    /* Checking customs feature */
    protected function checkFeatures($languages, $feature_id)
    {
        $rules = call_user_func(array('FeatureValue', 'getValidationRules'), 'FeatureValue');
        $feature = Feature::getFeature((int)Configuration::get('PS_LANG_DEFAULT'), $feature_id);

        foreach ($languages as $language) {
            if ($val = Tools::getValue('custom_'.$feature_id.'_'.$language['id_lang'])) {
                $current_language = new Language($language['id_lang']);
                if (Tools::strlen($val) > $rules['sizeLang']['value']) {
                    $this->errors[] = $this->trans(
                        'The name for feature %1$s is too long in %2$s.',
                        array(
                            ' <b>'.$feature['name'].'</b>',
                            $current_language->name
                        ),
                        'Admin.Catalog.Notification'
                    );
                } elseif (!call_user_func(array('Validate', $rules['validateLang']['value']), $val)) {
                    $this->errors[] = $this->trans('A valid name required for feature. %1$s in %2$s.',
                        array(
                            ' <b>'.$feature['name'].'</b>',
                            $current_language->name
                        ),
                        'Admin.Catalog.Notification'
                    );
                }
                if (count($this->errors)) {
                    return 0;
                }
                // Getting default language
                if ($language['id_lang'] == Configuration::get('PS_LANG_DEFAULT')) {
                    return $val;
                }
            }
        }
        return 0;
    }

    /**
     * Add or update a product image
     *
     * @param Product $product Product object to add image
     * @param string  $method
     *
     * @return int|false
     */
    public function addProductImage($product, $method = 'auto')
    {
        /* Updating an existing product image */
        if ($id_image = (int)Tools::getValue('id_image')) {
            $image = new Image((int)$id_image);
            if (!Validate::isLoadedObject($image)) {
                $this->errors[] = $this->trans('An error occurred while uploading the image.', array(), 'Admin.Notifications.Error');
            } else {
                if (($cover = Tools::getValue('cover')) == 1) {
                    Image::deleteCover($product->id);
                }
                $image->cover = $cover;
                $this->validateRules('Image');
                $this->copyFromPost($image, 'image');
                if (count($this->errors) || !$image->update()) {
                    $this->errors[] = $this->trans('An error occurred while updating the image.', array(), 'Admin.Notifications.Error');
                } elseif (isset($_FILES['image_product']['tmp_name']) && $_FILES['image_product']['tmp_name'] != null) {
                    $this->copyImage($product->id, $image->id, $method);
                }
            }
        }
        if (isset($image) && Validate::isLoadedObject($image) && !file_exists(_PS_PROD_IMG_DIR_.$image->getExistingImgPath().'.'.$image->image_format)) {
            $image->delete();
        }
        if (count($this->errors)) {
            return false;
        }
        @unlink(_PS_TMP_IMG_DIR_.'product_'.$product->id.'.jpg');
        @unlink(_PS_TMP_IMG_DIR_.'product_mini_'.$product->id.'_'.$this->context->shop->id.'.jpg');
        return ((isset($id_image) && is_int($id_image) && $id_image) ? $id_image : false);
    }

    /**
     * Copy a product image
     *
     * @param int    $id_product Product Id for product image filename
     * @param int    $id_image   Image Id for product image filename
     * @param string $method
     *
     * @return void|false
     * @throws PrestaShopException
     */
    public function copyImage($id_product, $id_image, $method = 'auto')
    {
        if (!isset($_FILES['image_product']['tmp_name'])) {
            return false;
        }
        if ($error = ImageManager::validateUpload($_FILES['image_product'])) {
            $this->errors[] = $error;
        } else {
            $image = new Image($id_image);

            if (!$new_path = $image->getPathForCreation()) {
                $this->errors[] = $this->trans('An error occurred while attempting to create a new folder.', array(), 'Admin.Notifications.Error');
            }
            if (!($tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS')) || !move_uploaded_file($_FILES['image_product']['tmp_name'], $tmpName)) {
                $this->errors[] = $this->trans('An error occurred while uploading the image.', array(), 'Admin.Notifications.Error');
            } elseif (!ImageManager::resize($tmpName, $new_path.'.'.$image->image_format)) {
                $this->errors[] = $this->trans('An error occurred while copying the image.', array(), 'Admin.Notifications.Error');
            } elseif ($method == 'auto') {
                $imagesTypes = ImageType::getImagesTypes('products');
                foreach ($imagesTypes as $k => $image_type) {
                    if (!ImageManager::resize($tmpName, $new_path.'-'.stripslashes($image_type['name']).'.'.$image->image_format, $image_type['width'], $image_type['height'], $image->image_format)) {
                        $this->errors[] = $this->trans('An error occurred while copying this image: %s', array(stripslashes($image_type['name'])), 'Admin.Notifications.Error');
                    }
                }
            }

            @unlink($tmpName);
            Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_product));
        }
    }

    protected function updateAssoShop($id_object)
    {
        //override AdminController::updateAssoShop() specifically for products because shop association is set with the context in ObjectModel
        return;
    }

    public function processAdd()
    {
        $this->checkProduct();

        if (!empty($this->errors)) {
            $this->display = 'add';
            return false;
        }

        $this->object = new $this->className();
        $this->_removeTaxFromEcotax();
        $this->copyFromPost($this->object, $this->table);
        if ($this->object->add()) {
            PrestaShopLogger::addLog(sprintf($this->l('%s addition', 'AdminTab', false, false), $this->className), 1, null, $this->className, (int)$this->object->id, true, (int)$this->context->employee->id);
            $this->addCarriers($this->object);
            $this->updateAccessories($this->object);
            $this->updatePackItems($this->object);
            $this->updateDownloadProduct($this->object);

            if (Configuration::get('PS_FORCE_ASM_NEW_PRODUCT') && Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $this->object->getType() != Product::PTYPE_VIRTUAL) {
                $this->object->advanced_stock_management = 1;
                $this->object->save();
                $id_shops = Shop::getContextListShopID();
                foreach ($id_shops as $id_shop) {
                    StockAvailable::setProductDependsOnStock($this->object->id, true, (int)$id_shop, 0);
                }
            }

            if (empty($this->errors)) {
                $languages = Language::getLanguages(false);
                if ($this->isProductFieldUpdated('category_box') && !$this->object->updateCategories(Tools::getValue('categoryBox'))) {
                    $this->errors[] = $this->trans(
                        'An error occurred while linking the object %table_name% to categories.',
                        array(
                            '%table_name%' => ' <b>'.$this->table.'</b> '
                        ),
                        'Admin.Notifications.Error'
                    );
                } elseif (!$this->updateTags($languages, $this->object)) {
                    $this->errors[] = $this->trans('An error occurred while adding tags.', array(), 'Admin.Catalog.Notification');
                } else {
                    Hook::exec('actionProductAdd', array('id_product' => (int)$this->object->id, 'product' => $this->object));
                    if (in_array($this->object->visibility, array('both', 'search')) && Configuration::get('PS_SEARCH_INDEXATION')) {
                        Search::indexation(false, $this->object->id);
                    }
                }

                if (Configuration::get('PS_DEFAULT_WAREHOUSE_NEW_PRODUCT') != 0 && Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                    $warehouse_location_entity = new WarehouseProductLocation();
                    $warehouse_location_entity->id_product = $this->object->id;
                    $warehouse_location_entity->id_product_attribute = 0;
                    $warehouse_location_entity->id_warehouse = Configuration::get('PS_DEFAULT_WAREHOUSE_NEW_PRODUCT');
                    $warehouse_location_entity->location = pSQL('');
                    $warehouse_location_entity->save();
                }

                // Apply groups reductions
                $this->object->setGroupReduction();

                // Save and preview
                if (Tools::isSubmit('submitAddProductAndPreview')) {
                    $this->redirect_after = $this->getPreviewUrl($this->object);
                }

                // Save and stay on same form
                if ($this->display == 'edit') {
                    $this->redirect_after = self::$currentIndex.'&id_product='.(int)$this->object->id
                        .(Tools::getIsset('id_category') ? '&id_category='.(int)Tools::getValue('id_category') : '')
                        .'&updateproduct&conf=3&key_tab='.Tools::safeOutput(Tools::getValue('key_tab')).'&token='.$this->token;
                } else {
                    // Default behavior (save and back)
                    $this->redirect_after = self::$currentIndex
                        .(Tools::getIsset('id_category') ? '&id_category='.(int)Tools::getValue('id_category') : '')
                        .'&conf=3&token='.$this->token;
                }
            } else {
                $this->object->delete();
                // if errors : stay on edit page
                $this->display = 'edit';
            }
        } else {
            $this->errors[] = $this->trans('An error occurred while creating an object.', array(), 'Admin.Notifications.Error').' <b>'.$this->table.'</b>';
        }

        return $this->object;
    }

    protected function isTabSubmitted($tab_name)
    {
        if (!is_array($this->submitted_tabs)) {
            $this->submitted_tabs = Tools::getValue('submitted_tabs');
        }

        if (is_array($this->submitted_tabs) && in_array($tab_name, $this->submitted_tabs)) {
            return true;
        }

        return false;
    }

    public function processStatus()
    {
        $this->loadObject(true);
        if (!Validate::isLoadedObject($this->object)) {
            return false;
        }
        if (($error = $this->object->validateFields(false, true)) !== true) {
            $this->errors[] = $error;
        }
        if (($error = $this->object->validateFieldsLang(false, true)) !== true) {
            $this->errors[] = $error;
        }

        if (count($this->errors)) {
            return false;
        }

        $res = parent::processStatus();

        $query = trim(Tools::getValue('bo_query'));
        $searchType = (int)Tools::getValue('bo_search_type');

        if ($query) {
            $this->redirect_after = preg_replace('/[\?|&](bo_query|bo_search_type)=([^&]*)/i', '', $this->redirect_after);
            $this->redirect_after .= '&bo_query='.$query.'&bo_search_type='.$searchType;
        }

        return $res;
    }

    public function processUpdate()
    {
        $existing_product = $this->object;

        $this->checkProduct();

        if (!empty($this->errors)) {
            $this->display = 'edit';
            return false;
        }

        $id = (int)Tools::getValue('id_'.$this->table);
        /* Update an existing product */
        if (isset($id) && !empty($id)) {
            /** @var Product $object */
            $object = new $this->className((int)$id);
            $this->object = $object;

            if (Validate::isLoadedObject($object)) {
                $this->_removeTaxFromEcotax();
                $product_type_before = $object->getType();
                $this->copyFromPost($object, $this->table);
                $object->indexed = 0;

                if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP) {
                    $object->setFieldsToUpdate((array)Tools::getValue('multishop_check', array()));
                }

                // Duplicate combinations if not associated to shop
                if ($this->context->shop->getContext() == Shop::CONTEXT_SHOP && !$object->isAssociatedToShop()) {
                    $is_associated_to_shop = false;
                    $combinations = Product::getProductAttributesIds($object->id);
                    if ($combinations) {
                        foreach ($combinations as $id_combination) {
                            $combination = new Combination((int)$id_combination['id_product_attribute']);
                            $default_combination = new Combination((int)$id_combination['id_product_attribute'], null, (int)$this->object->id_shop_default);

                            $def = ObjectModel::getDefinition($default_combination);
                            foreach ($def['fields'] as $field_name => $row) {
                                $combination->$field_name = ObjectModel::formatValue($default_combination->$field_name, $def['fields'][$field_name]['type']);
                            }

                            $combination->save();
                        }
                    }
                } else {
                    $is_associated_to_shop = true;
                }

                if ($object->update()) {
                    // If the product doesn't exist in the current shop but exists in another shop
                    if (Shop::getContext() == Shop::CONTEXT_SHOP && !$existing_product->isAssociatedToShop($this->context->shop->id)) {
                        $out_of_stock = StockAvailable::outOfStock($existing_product->id, $existing_product->id_shop_default);
                        $depends_on_stock = StockAvailable::dependsOnStock($existing_product->id, $existing_product->id_shop_default);
                        StockAvailable::setProductOutOfStock((int)$this->object->id, $out_of_stock, $this->context->shop->id);
                        StockAvailable::setProductDependsOnStock((int)$this->object->id, $depends_on_stock, $this->context->shop->id);
                    }

                    PrestaShopLogger::addLog(sprintf($this->l('%s modification', 'AdminTab', false, false), $this->className), 1, null, $this->className, (int)$this->object->id, true, (int)$this->context->employee->id);
                    if (in_array($this->context->shop->getContext(), array(Shop::CONTEXT_SHOP, Shop::CONTEXT_ALL))) {
                        if ($this->isTabSubmitted('Shipping')) {
                            $this->addCarriers();
                        }
                        if ($this->isTabSubmitted('Associations')) {
                            $this->updateAccessories($object);
                        }
                        if ($this->isTabSubmitted('Suppliers')) {
                            $this->processSuppliers();
                        }
                        if ($this->isTabSubmitted('Features')) {
                            $this->processFeatures();
                        }
                        if ($this->isTabSubmitted('Combinations')) {
                            $this->processProductAttribute();
                        }
                        if ($this->isTabSubmitted('Prices')) {
                            $this->processPriceAddition();
                            $this->processSpecificPricePriorities();
                        }
                        if ($this->isTabSubmitted('Customization')) {
                            $this->processCustomizationConfiguration();
                        }
                        if ($this->isTabSubmitted('Attachments')) {
                            $this->processAttachments();
                        }
                        if ($this->isTabSubmitted('Images')) {
                            $this->processImageLegends();
                        }

                        $this->updatePackItems($object);
                        // Disallow avanced stock management if the product become a pack
                        if ($product_type_before == Product::PTYPE_SIMPLE && $object->getType() == Product::PTYPE_PACK) {
                            StockAvailable::setProductDependsOnStock((int)$object->id, false);
                        }
                        $this->updateDownloadProduct($object, 1);
                        $this->updateTags(Language::getLanguages(false), $object);

                        if ($this->isProductFieldUpdated('category_box') && !$object->updateCategories(Tools::getValue('categoryBox'))) {
                            $this->errors[] = $this->trans(
                                'An error occurred while linking the object %table_name% to categories.',
                                    array(
                                        '%table_name%' => ' <b>'.$this->table.'</b> ',
                                    ),
                                    'Admin.Notifications.Error'
                                );
                        }
                    }

                    if ($this->isTabSubmitted('Warehouses')) {
                        $this->processWarehouses();
                    }
                    if (empty($this->errors)) {
                        if (in_array($object->visibility, array('both', 'search')) && Configuration::get('PS_SEARCH_INDEXATION')) {
                            Search::indexation(false, $object->id);
                        }

                        // Save and preview
                        if (Tools::isSubmit('submitAddProductAndPreview')) {
                            $this->redirect_after = $this->getPreviewUrl($object);
                        } else {
                            $page = (int)Tools::getValue('page');
                            // Save and stay on same form
                            if ($this->display == 'edit') {
                                $this->confirmations[] = $this->l('Update successful');
                                $this->redirect_after = self::$currentIndex.'&id_product='.(int)$this->object->id
                                    .(Tools::getIsset('id_category') ? '&id_category='.(int)Tools::getValue('id_category') : '')
                                    .'&updateproduct&conf=4&key_tab='.Tools::safeOutput(Tools::getValue('key_tab')).($page > 1 ? '&page='.(int)$page : '').'&token='.$this->token;
                            } else {
                                // Default behavior (save and back)
                                $this->redirect_after = self::$currentIndex.(Tools::getIsset('id_category') ? '&id_category='.(int)Tools::getValue('id_category') : '').'&conf=4'.($page > 1 ? '&submitFilterproduct='.(int)$page : '').'&token='.$this->token;
                            }
                        }
                    }
                    // if errors : stay on edit page
                    else {
                        $this->display = 'edit';
                    }
                } else {
                    if (!$is_associated_to_shop && $combinations) {
                        foreach ($combinations as $id_combination) {
                            $combination = new Combination((int)$id_combination['id_product_attribute']);
                            $combination->delete();
                        }
                    }
                    $this->errors[] = $this->trans('An error occurred while updating an object.', array(), 'Admin.Notifications.Error').' <b>'.$this->table.'</b> ('.Db::getInstance()->getMsgError().')';
                }
            } else {
                $this->errors[] = $this->trans('An error occurred while updating an object.', array(), 'Admin.Notifications.Error').' <b>'.$this->table.'</b> ('.$this->trans('The object cannot be loaded. ', array(), 'Admin.Notifications.Error').')';
            }
            return $object;
        }
    }

    /**
     * Check that a saved product is valid
     */
    public function checkProduct()
    {
        // @todo : the call_user_func seems to contains only statics values (className = 'Product')
        $rules = call_user_func(array($this->className, 'getValidationRules'), $this->className);
        $default_language = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $languages = Language::getLanguages(false);

        // Check required fields
        foreach ($rules['required'] as $field) {
            if (!$this->isProductFieldUpdated($field)) {
                continue;
            }

            if (($value = Tools::getValue($field)) == false && $value != '0') {
                if (Tools::getValue('id_'.$this->table) && $field == 'passwd') {
                    continue;
                }
                $this->errors[] = sprintf(
                    $this->trans('The %s field is required.', array(), 'Admin.Notifications.Error'),
                    call_user_func(array($this->className, 'displayFieldName'), $field, $this->className)
                );
            }
        }

        // Check multilingual required fields
        foreach ($rules['requiredLang'] as $fieldLang) {
            if ($this->isProductFieldUpdated($fieldLang, $default_language->id) && !Tools::getValue($fieldLang.'_'.$default_language->id)) {
                $this->errors[] = $this->trans(
                    'This %1$s field is required at least in %2$s',
                    array(
                        call_user_func(array($this->className, 'displayFieldName'), $fieldLang, $this->className),
                        $default_language->name
                    ),
                    'Admin.Catalog.Notification'
                );
            }
        }

        // Check fields sizes
        foreach ($rules['size'] as $field => $maxLength) {
            if ($this->isProductFieldUpdated($field) && ($value = Tools::getValue($field)) && Tools::strlen($value) > $maxLength) {
                $this->errors[] = $this->trans(
                    'The %1$s field is too long (%2$d chars max).',
                    array(
                        call_user_func(array($this->className, 'displayFieldName'), $field, $this->className),
                        $maxLength
                    ),
                    'Admin.Catalog.Notification'
                );
            }
        }

        if (Tools::getIsset('description_short') && $this->isProductFieldUpdated('description_short')) {
            $saveShort = Tools::getValue('description_short');
            $_POST['description_short'] = strip_tags(Tools::getValue('description_short'));
        }

        // Check description short size without html
        $limit = (int)Configuration::get('PS_PRODUCT_SHORT_DESC_LIMIT');
        if ($limit <= 0) {
            $limit = 400;
        }
        foreach ($languages as $language) {
            if ($this->isProductFieldUpdated('description_short', $language['id_lang']) && ($value = Tools::getValue('description_short_'.$language['id_lang']))) {
                if (Tools::strlen(strip_tags($value)) > $limit) {
                    $this->errors[] = $this->trans(
                        'This %1$s field (%2$s) is too long: %3$d chars max (current count %4$d).',
                        array(
                            call_user_func(array($this->className, 'displayFieldName'), 'description_short'),
                            $language['name'],
                            $limit,
                            Tools::strlen(strip_tags($value))
                        ),
                        'Admin.Catalog.Notification'
                    );
                }
            }
        }

        // Check multilingual fields sizes
        foreach ($rules['sizeLang'] as $fieldLang => $maxLength) {
            foreach ($languages as $language) {
                $value = Tools::getValue($fieldLang.'_'.$language['id_lang']);
                if ($value && Tools::strlen($value) > $maxLength) {
                    $this->errors[] = $this->trans(
                        'The %1$s field is too long (%2$d chars max).',
                        array(
                            call_user_func(array($this->className, 'displayFieldName'), $fieldLang, $this->className),
                            $maxLength
                        ),
                        'Admin.Catalog.Notification'
                    );
                }
            }
        }

        if ($this->isProductFieldUpdated('description_short') && isset($_POST['description_short'])) {
            $_POST['description_short'] = $saveShort;
        }

        // Check fields validity
        foreach ($rules['validate'] as $field => $function) {
            if ($this->isProductFieldUpdated($field) && ($value = Tools::getValue($field))) {
                $res = true;
                if (Tools::strtolower($function) == 'iscleanhtml') {
                    if (!Validate::$function($value, (int)Configuration::get('PS_ALLOW_HTML_IFRAME'))) {
                        $res = false;
                    }
                } elseif (!Validate::$function($value)) {
                    $res = false;
                }

                if (!$res) {
                    $this->errors[] = $this->trans(
                        'The %s field is invalid.',
                        array(
                            call_user_func(array($this->className, 'displayFieldName'), $field, $this->className)
                        ),
                        'Admin.Notifications.Error'
                    );
                }
            }
        }
        // Check multilingual fields validity
        foreach ($rules['validateLang'] as $fieldLang => $function) {
            foreach ($languages as $language) {
                if ($this->isProductFieldUpdated($fieldLang, $language['id_lang']) && ($value = Tools::getValue($fieldLang.'_'.$language['id_lang']))) {
                    if (!Validate::$function($value, (int)Configuration::get('PS_ALLOW_HTML_IFRAME'))) {
                        $this->errors[] = $this->trans(
                            'The %1$s field (%2$s) is invalid.',
                            array(
                                call_user_func(array($this->className, 'displayFieldName'), $fieldLang, $this->className),
                                $language['name']
                            ),
                            'Admin.Notifications.Error'
                        );
                    }
                }
            }
        }

        // Categories
        if ($this->isProductFieldUpdated('id_category_default') && (!Tools::isSubmit('categoryBox') || !count(Tools::getValue('categoryBox')))) {
            $this->errors[] = $this->l('Products must be in at least one category.');
        }

        if ($this->isProductFieldUpdated('id_category_default') && (!is_array(Tools::getValue('categoryBox')) || !in_array(Tools::getValue('id_category_default'), Tools::getValue('categoryBox')))) {
            $this->errors[] = $this->l('This product must be in the default category.');
        }

        // Tags
        foreach ($languages as $language) {
            if ($value = Tools::getValue('tags_'.$language['id_lang'])) {
                if (!Validate::isTagsList($value)) {
                    $this->errors[] = $this->trans(
                        'The tags list (%s) is invalid.',
                        array(
                            $language['name']
                        ),
                        'Admin.Notifications.Error'
                    );
                }
            }
        }
    }

    /**
     * Check if a field is edited (if the checkbox is checked)
     * This method will do something only for multishop with a context all / group
     *
     * @param string $field Name of field
     * @param int $id_lang
     * @return bool
     */
    protected function isProductFieldUpdated($field, $id_lang = null)
    {
        // Cache this condition to improve performances
        static $is_activated = null;
        if (is_null($is_activated)) {
            $is_activated = Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP && $this->id_object;
        }

        if (!$is_activated) {
            return true;
        }

        $def = ObjectModel::getDefinition($this->object);
        if (!$this->object->isMultiShopField($field) && is_null($id_lang) && isset($def['fields'][$field])) {
            return true;
        }

        if (is_null($id_lang)) {
            return !empty($_POST['multishop_check'][$field]);
        } else {
            return !empty($_POST['multishop_check'][$field][$id_lang]);
        }
    }

    protected function _removeTaxFromEcotax()
    {
        if ($ecotax = Tools::getValue('ecotax')) {
            $_POST['ecotax'] = Tools::ps_round($ecotax / (1 + Tax::getProductEcotaxRate() / 100), 6);
        }
    }

    protected function _applyTaxToEcotax($product)
    {
        if ($product->ecotax) {
            $product->ecotax = Tools::ps_round($product->ecotax * (1 + Tax::getProductEcotaxRate() / 100), 2);
        }
    }

    /**
     * Update product download
     *
     * @param Product $product
     * @param int     $edit
     *
     * @return bool
     */
    public function updateDownloadProduct($product, $edit = 0)
    {
        //legacy/sf2 form workaround
        //if is_virtual_file parameter was not send (SF2 form case), don't process virtual file
        if (Tools::getValue('is_virtual_file') === false) {
            return false;
        }

        if ((int)Tools::getValue('is_virtual_file') == 1) {
            if (isset($_FILES['virtual_product_file_uploader']) && $_FILES['virtual_product_file_uploader']['size'] > 0) {
                $virtual_product_filename = ProductDownload::getNewFilename();
                $helper = new HelperUploader('virtual_product_file_uploader');
                $helper->setPostMaxSize(Tools::getOctets(ini_get('upload_max_filesize')))
                    ->setSavePath(_PS_DOWNLOAD_DIR_)->upload($_FILES['virtual_product_file_uploader'], $virtual_product_filename);
            } else {
                $virtual_product_filename = Tools::getValue('virtual_product_filename', ProductDownload::getNewFilename());
            }

            $product->setDefaultAttribute(0);//reset cache_default_attribute
            if (Tools::getValue('virtual_product_expiration_date') && !Validate::isDate(Tools::getValue('virtual_product_expiration_date'))) {
                if (!Tools::getValue('virtual_product_expiration_date')) {
                    $this->errors[] = $this->trans('The expiration-date attribute is required.', array(), 'Admin.Catalog.Notification');
                    return false;
                }
            }

            // Trick's
            if ($edit == 1) {
                $id_product_download = (int)ProductDownload::getIdFromIdProduct((int)$product->id, false);
                if (!$id_product_download) {
                    $id_product_download = (int)Tools::getValue('virtual_product_id');
                }
            } else {
                $id_product_download = Tools::getValue('virtual_product_id');
            }

            $is_shareable = Tools::getValue('virtual_product_is_shareable');
            $virtual_product_name = Tools::getValue('virtual_product_name');
            $virtual_product_nb_days = Tools::getValue('virtual_product_nb_days');
            $virtual_product_nb_downloable = Tools::getValue('virtual_product_nb_downloable');
            $virtual_product_expiration_date = Tools::getValue('virtual_product_expiration_date');

            $download = new ProductDownload((int)$id_product_download);
            $download->id_product = (int)$product->id;
            $download->display_filename = $virtual_product_name;
            $download->filename = $virtual_product_filename;
            $download->date_add = date('Y-m-d H:i:s');
            $download->date_expiration = $virtual_product_expiration_date ? $virtual_product_expiration_date.' 23:59:59' : '';
            $download->nb_days_accessible = (int)$virtual_product_nb_days;
            $download->nb_downloadable = (int)$virtual_product_nb_downloable;
            $download->active = 1;
            $download->is_shareable = (int)$is_shareable;
            if ($download->save()) {
                return true;
            }
        } else {
            /* unactive download product if checkbox not checked */
            if ($edit == 1) {
                $id_product_download = (int)ProductDownload::getIdFromIdProduct((int)$product->id);
                if (!$id_product_download) {
                    $id_product_download = (int)Tools::getValue('virtual_product_id');
                }
            } else {
                $id_product_download = ProductDownload::getIdFromIdProduct($product->id);
            }

            if (!empty($id_product_download)) {
                $product_download = new ProductDownload((int)$id_product_download);
                $product_download->date_expiration = date('Y-m-d H:i:s', time() - 1);
                $product_download->active = 0;
                return $product_download->save();
            }
        }
        return false;
    }

    /**
     * Update product accessories
     *
     * @param object $product Product
     */
    public function updateAccessories($product)
    {
        $product->deleteAccessories();
        if ($accessories = Tools::getValue('inputAccessories')) {
            $accessories_id = array_unique(explode('-', $accessories));
            if (count($accessories_id)) {
                array_pop($accessories_id);
                $product->changeAccessories($accessories_id);
            }
        }
    }

    /**
     * Update product tags
     *
     * @param array $languages Array languages
     * @param object $product Product
     * @return bool Update result
     */
    public function updateTags($languages, $product)
    {
        $tag_success = true;
        /* Reset all tags for THIS product */
        if (!Tag::deleteTagsForProduct((int)$product->id)) {
            $this->errors[] = $this->trans('An error occurred while attempting to delete previous tags.', array(), 'Admin.Catalog.Notification');
        }
        /* Assign tags to this product */
        foreach ($languages as $language) {
            if ($value = Tools::getValue('tags_'.$language['id_lang'])) {
                $tag_success &= Tag::addTags($language['id_lang'], (int)$product->id, $value);
            }
        }

        if (!$tag_success) {
            $this->errors[] = $this->trans('An error occurred while adding tags.', array(), 'Admin.Catalog.Notification');
        }

        return $tag_success;
    }

    public function initContent($token = null)
    {
        if ($this->display == 'edit' || $this->display == 'add') {
            $this->fields_form = array();

            // Check if Module
            if (substr($this->tab_display, 0, 6) == 'Module') {
                $this->tab_display_module = strtolower(substr($this->tab_display, 6, Tools::strlen($this->tab_display) - 6));
                $this->tab_display = 'Modules';
            }
            if (method_exists($this, 'initForm'.$this->tab_display)) {
                $this->tpl_form = strtolower($this->tab_display).'.tpl';
            }

            if ($this->ajax) {
                $this->content_only = true;
            } else {
                $product_tabs = array();

                // tab_display defines which tab to display first
                if (!method_exists($this, 'initForm'.$this->tab_display)) {
                    $this->tab_display = $this->default_tab;
                }

                $advanced_stock_management_active = Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT');
                foreach ($this->available_tabs as $product_tab => $value) {
                    // if it's the warehouses tab and advanced stock management is disabled, continue
                    if ($advanced_stock_management_active == 0 && $product_tab == 'Warehouses') {
                        continue;
                    }

                    $product_tabs[$product_tab] = array(
                        'id' => $product_tab,
                        'selected' => (strtolower($product_tab) == strtolower($this->tab_display) || (isset($this->tab_display_module) && 'module'.$this->tab_display_module == Tools::strtolower($product_tab))),
                        'name' => $this->available_tabs_lang[$product_tab],
                        'href' => $this->context->link->getAdminLink('AdminProducts').'&id_product='.(int)Tools::getValue('id_product').'&action='.$product_tab,
                    );
                }
                $this->tpl_form_vars['product_tabs'] = $product_tabs;
            }
        } else {
            if ($id_category = (int)$this->id_current_category) {
                self::$currentIndex .= '&id_category='.(int)$this->id_current_category;
            }

            // If products from all categories are displayed, we don't want to use sorting by position
            if (!$id_category) {
                $this->_defaultOrderBy = $this->identifier;
                if ($this->context->cookie->{$this->table.'Orderby'} == 'position') {
                    unset($this->context->cookie->{$this->table.'Orderby'});
                    unset($this->context->cookie->{$this->table.'Orderway'});
                }
            }
            if (!$id_category) {
                $id_category = Configuration::get('PS_ROOT_CATEGORY');
            }
            $this->tpl_list_vars['is_category_filter'] = (bool)$this->id_current_category;

            // Generate category selection tree
            $tree = new HelperTreeCategories('categories-tree', $this->l('Filter by category'));
            $tree->setAttribute('is_category_filter', (bool)$this->id_current_category)
                ->setAttribute('base_url', preg_replace('#&id_category=[0-9]*#', '', self::$currentIndex).'&token='.$this->token)
                ->setInputName('id-category')
                ->setRootCategory(Category::getRootCategory()->id)
                ->setSelectedCategories(array((int)$id_category));
            $this->tpl_list_vars['category_tree'] = $tree->render();

            // used to build the new url when changing category
            $this->tpl_list_vars['base_url'] = preg_replace('#&id_category=[0-9]*#', '', self::$currentIndex).'&token='.$this->token;
        }
        // @todo module free
        $this->tpl_form_vars['vat_number'] = file_exists(_PS_MODULE_DIR_.'vatnumber/ajax.php');

        parent::initContent();
    }

    public function renderKpis()
    {
        $time = time();
        $kpis = array();

        /* The data generation is located in AdminStatsControllerCore */

        if (Configuration::get('PS_STOCK_MANAGEMENT')) {
            $helper = new HelperKpi();
            $helper->id = 'box-products-stock';
            $helper->icon = 'local_shipping';
            $helper->color = 'color1';
            $helper->title = $this->l('Out of stock items', null, null, false);
            if (ConfigurationKPI::get('PERCENT_PRODUCT_OUT_OF_STOCK') !== false) {
                $helper->value = ConfigurationKPI::get('PERCENT_PRODUCT_OUT_OF_STOCK');
            }
            $helper->source = $this->context->link->getAdminLink('AdminStats').'&ajax=1&action=getKpi&kpi=percent_product_out_of_stock';
            $helper->tooltip = sprintf($this->l('%s of your products for sale are out of stock.', null, null, false), $helper->value);
            $helper->refresh = (bool)(ConfigurationKPI::get('PERCENT_PRODUCT_OUT_OF_STOCK_EXPIRE') < $time);
            $product_token = Tools::getAdminToken('AdminProducts'.(int)Tab::getIdFromClassName('AdminProducts').(int)Context::getContext()->employee->id);
            $urlParams = ['filter_column_sav_quantity' => '<=0', 'filter_column_active' => '1', 'token' => $product_token, 'submitFilterproduct' => 1];
            $helper->href = preg_replace("/\\?.*$/", '?tab=AdminProducts&productFilter_sav!quantity=0&productFilter_active=1&submitFilterproduct=1&token='. $product_token, Context::getContext()->link->getAdminLink('AdminProducts', true, $urlParams));
            $kpis[] = $helper->generate();
        }

        $helper = new HelperKpi();
        $helper->id = 'box-avg-gross-margin';
        $helper->icon = 'label';
        $helper->color = 'color2';
        $helper->title = $this->l('Average Gross Margin %', null, null, false);
        if (ConfigurationKPI::get('PRODUCT_AVG_GROSS_MARGIN') !== false) {
            $helper->value = ConfigurationKPI::get('PRODUCT_AVG_GROSS_MARGIN');
        }
        $helper->source = $this->context->link->getAdminLink('AdminStats').'&ajax=1&action=getKpi&kpi=product_avg_gross_margin';
        $helper->tooltip = sprintf($this->l('Gross margin expressed in percentage assesses how cost-effectively you sell your goods. Out of $100, you will retain $%s to cover profit and expenses.', null, null, false), str_replace('%', '', $helper->value));
        $helper->refresh = (bool)(ConfigurationKPI::get('PRODUCT_AVG_GROSS_MARGIN_EXPIRE') < $time);
        $kpis[] = $helper->generate();

        $helper = new HelperKpi();
        $helper->id = 'box-8020-sales-catalog';
        $helper->icon = 'whatshot';
        $helper->color = 'color3';
        $helper->title = $this->l('Catalog popularity', null, null, false);
        $helper->subtitle = $this->l('30 days', null, null, false);
        if (ConfigurationKPI::get('8020_SALES_CATALOG') !== false) {
            $helper->value = ConfigurationKPI::get('8020_SALES_CATALOG');
        }
        $helper->source = $this->context->link->getAdminLink('AdminStats').'&ajax=1&action=getKpi&kpi=8020_sales_catalog';
        $helper->tooltip = sprintf($this->l('Within your catalog, %s of your products have had sales in the last 30 days', null, null, false), $helper->value);
        $helper->refresh = (bool)(ConfigurationKPI::get('8020_SALES_CATALOG_EXPIRE') < $time);
        $moduleManagerBuilder = ModuleManagerBuilder::getInstance();
        $moduleManager = $moduleManagerBuilder->build();

        if ($moduleManager->isInstalled('statsbestproducts')) {
            $helper->href = Context::getContext()->link->getAdminLink('AdminStats').'&module=statsbestproducts&datepickerFrom='.date('Y-m-d', strtotime('-30 days')).'&datepickerTo='.date('Y-m-d');
        }
        $kpis[] = $helper->generate();

        $helper = new HelperKpi();
        $helper->id = 'box-disabled-products';
        $helper->icon = 'visibility_off';
        $helper->color = 'color4';
        $helper->title = $this->l('Disabled Products', null, null, false);
        if (ConfigurationKPI::get('DISABLED_PRODUCTS') !== false) {
            $helper->value = ConfigurationKPI::get('DISABLED_PRODUCTS');
        }
        $helper->source = $this->context->link->getAdminLink('AdminStats').'&ajax=1&action=getKpi&kpi=disabled_products';
        $helper->refresh = (bool)(ConfigurationKPI::get('DISABLED_PRODUCTS_EXPIRE') < $time);
        $helper->tooltip = sprintf($this->l('%s of your products are disabled and not visible to your customers', null, null, false), $helper->value);
        $product_token = Tools::getAdminToken('AdminProducts'.(int)Tab::getIdFromClassName('AdminProducts').(int)Context::getContext()->employee->id);
        $urlParams = ['filter_column_active' => '0', 'token' => $product_token, 'submitFilterproduct' => 1];
        $helper->href = preg_replace("/\\?.*$/", '?tab=AdminProducts&productFilter_active=0&submitFilterproduct=1&token='. $product_token, Context::getContext()->link->getAdminLink('AdminProducts', true, $urlParams));
        $kpis[] = $helper->generate();

        $helper = new HelperKpiRow();
        $helper->kpis = $kpis;
        return $helper->generate();
    }

    public function renderList()
    {
        $this->addRowAction('edit');
        $this->addRowAction('preview');
        $this->addRowAction('duplicate');
        $this->addRowAction('delete');
        return parent::renderList();
    }

    public function ajaxProcessProductManufacturers()
    {
        $manufacturers = Manufacturer::getManufacturers(false, 0, true, false, false, false, true);
        $jsonArray = array();

        if ($manufacturers) {
            foreach ($manufacturers as $manufacturer) {
                $tmp = array("optionValue" => $manufacturer['id_manufacturer'], "optionDisplay" => htmlspecialchars(trim($manufacturer['name'])));
                $jsonArray[] = json_encode($tmp);
            }
        }

        die('['.implode(',', $jsonArray).']');
    }

    /**
     * Build a categories tree
     *
     * @param       $id_obj
     * @param array $indexedCategories   Array with categories where product is indexed (in order to check checkbox)
     * @param array $categories          Categories to list
     * @param       $current
     * @param null  $id_category         Current category ID
     * @param null  $id_category_default
     * @param array $has_suite
     *
     * @return string
     */
    public static function recurseCategoryForInclude($id_obj, $indexedCategories, $categories, $current, $id_category = null, $id_category_default = null, $has_suite = array())
    {
        static $irow;
        static $done;
        $content = '';

        if (!$id_category) {
            $id_category = (int)Configuration::get('PS_ROOT_CATEGORY');
        }

        if (!isset(self::$done[$current['infos']['id_parent']])) {
            self::$done[$current['infos']['id_parent']] = 0;
        }
        self::$done[$current['infos']['id_parent']] += 1;

        $todo = count($categories[$current['infos']['id_parent']]);
        $doneC = self::$done[$current['infos']['id_parent']];

        $level = $current['infos']['level_depth'] + 1;

        $content .= '
		<tr class="'.($irow++ % 2 ? 'alt_row' : '').'">
			<td>
				<input type="checkbox" name="categoryBox[]" class="categoryBox'.($id_category_default == $id_category ? ' id_category_default' : '').'" id="categoryBox_'.$id_category.'" value="'.$id_category.'"'.((in_array($id_category, $indexedCategories) || ((int)(Tools::getValue('id_category')) == $id_category && !(int)($id_obj))) ? ' checked="checked"' : '').' />
			</td>
			<td>
				'.$id_category.'
			</td>
			<td>';
        for ($i = 2; $i < $level; $i++) {
            $content .= '<img src="../img/admin/lvl_'.$has_suite[$i - 2].'.gif" alt="" />';
        }
        $content .= '<img src="../img/admin/'.($level == 1 ? 'lv1.gif' : 'lv2_'.($todo == $doneC ? 'f' : 'b').'.gif').'" alt="" /> &nbsp;
			<label for="categoryBox_'.$id_category.'" class="t">'.stripslashes($current['infos']['name']).'</label></td>
		</tr>';

        if ($level > 1) {
            $has_suite[] = ($todo == $doneC ? 0 : 1);
        }
        if (isset($categories[$id_category])) {
            foreach ($categories[$id_category] as $key => $row) {
                if ($key != 'infos') {
                    $content .= AdminProductsController::recurseCategoryForInclude($id_obj, $indexedCategories, $categories, $categories[$id_category][$key], $key, $id_category_default, $has_suite);
                }
            }
        }
        return $content;
    }

    protected function _displayDraftWarning($active)
    {
        $content = '<div class="warn draft" style="'.($active ? 'display:none' : '').'">
				<span>'.$this->l('Your product will be saved as a draft.').'</span>
				<a href="#" class="btn btn-default pull-right" onclick="submitAddProductAndPreview()" ><i class="icon-external-link-sign"></i> '.$this->l('Save and preview').'</a>
				<input type="hidden" name="fakeSubmitAddProductAndPreview" id="fakeSubmitAddProductAndPreview" />
	 		</div>';
        $this->tpl_form_vars['draft_warning'] = $content;
    }

    public function initPageHeaderToolbar()
    {
        if (empty($this->display)) {
            // New architecture modification: temporary behavior to switch between old and new controllers.
            $this->page_header_toolbar_btn['legacy'] = array(
                'href' => __PS_BASE_URI__.basename(_PS_ADMIN_DIR_).'/product/uselegacy/0',
                'desc' => $this->l('Switch again to new Page', null, null, false),
                'icon' => 'process-icon-toggle-off'
            );

            $this->page_header_toolbar_btn['new_product'] = array(
                    'href' => self::$currentIndex.'&addproduct&token='.$this->token,
                    'desc' => $this->l('Add new product', null, null, false),
                    'icon' => 'process-icon-new'
                );
        }
        if ($this->display == 'edit') {
            if (($product = $this->loadObject(true)) && $product->isAssociatedToShop()) {
                // adding button for preview this product
                if ($url_preview = $this->getPreviewUrl($product)) {
                    $this->page_header_toolbar_btn['preview'] = array(
                        'short' => $this->l('Preview', null, null, false),
                        'href' => $url_preview,
                        'desc' => $this->l('Preview', null, null, false),
                        'target' => true,
                        'class' => 'previewUrl'
                    );
                }

                $js = (bool)Image::getImages($this->context->language->id, (int)$product->id) ?
                    'confirm_link(\'\', \''.$this->l('This will copy the images too. If you wish to proceed, click "Yes". If not, click "No".', null, true, false).'\', \''.$this->l('Yes', null, true, false).'\', \''.$this->l('No', null, true, false).'\', \''.$this->context->link->getAdminLink('AdminProducts', true).'&id_product='.(int)$product->id.'&duplicateproduct'.'\', \''.$this->context->link->getAdminLink('AdminProducts', true).'&id_product='.(int)$product->id.'&duplicateproduct&noimage=1'.'\')'
                    :
                    'document.location = \''.$this->context->link->getAdminLink('AdminProducts', true).'&id_product='.(int)$product->id.'&duplicateproduct&noimage=1'.'\'';

                // adding button for duplicate this product
                if ($this->access('add')) {
                    $this->page_header_toolbar_btn['duplicate'] = array(
                        'short' => $this->l('Duplicate', null, null, false),
                        'desc' => $this->l('Duplicate', null, null, false),
                        'confirm' => 1,
                        'js' => $js
                    );
                }

                // adding button for preview this product statistics
                if (file_exists(_PS_MODULE_DIR_.'statsproduct/statsproduct.php')) {
                    $this->page_header_toolbar_btn['stats'] = array(
                    'short' => $this->l('Statistics', null, null, false),
                    'href' => $this->context->link->getAdminLink('AdminStats').'&module=statsproduct&id_product='.(int)$product->id,
                    'desc' => $this->l('Product sales', null, null, false),
                );
                }

                // adding button for delete this product
                if ($this->access('delete')) {
                    $this->page_header_toolbar_btn['delete'] = array(
                        'short' => $this->l('Delete', null, null, false),
                        'href' => $this->context->link->getAdminLink('AdminProducts').'&id_product='.(int)$product->id.'&deleteproduct',
                        'desc' => $this->l('Delete this product', null, null, false),
                        'confirm' => 1,
                        'js' => 'if (confirm(\''.$this->l('Delete product?', null, true, false).'\')){return true;}else{event.preventDefault();}'
                    );
                }
            }
        }
        parent::initPageHeaderToolbar();
    }

    public function initToolbar()
    {
        parent::initToolbar();
        if ($this->display == 'edit' || $this->display == 'add') {
            $this->toolbar_btn['save'] = array(
                'short' => 'Save',
                'href' => '#',
                'desc' => $this->trans('Save', array(), 'Admin.Actions'),
            );

            $this->toolbar_btn['save-and-stay'] = array(
                'short' => 'SaveAndStay',
                'href' => '#',
                'desc' => $this->l('Save and stay'),
            );

            // adding button for adding a new combination in Combination tab
            $this->toolbar_btn['newCombination'] = array(
                'short' => 'New combination',
                'desc' => $this->l('New combination'),
                'class' => 'toolbar-new'
            );
        } elseif ($this->can_import) {
            $this->toolbar_btn['import'] = array(
                'href' => $this->context->link->getAdminLink('AdminImport', true).'&import_type=products',
                'desc' => $this->trans('Import', array(), 'Admin.Actions')
            );
        }

        $this->context->smarty->assign('toolbar_scroll', 1);
        $this->context->smarty->assign('show_toolbar', 1);
        $this->context->smarty->assign('toolbar_btn', $this->toolbar_btn);
    }

    public function getPreviewUrl(Product $product)
    {
        $id_lang = Configuration::get('PS_LANG_DEFAULT', null, null, Context::getContext()->shop->id);

        if (!ShopUrl::getMainShopDomain()) {
            return false;
        }

        $is_rewrite_active = (bool)Configuration::get('PS_REWRITING_SETTINGS');
        $preview_url = $this->context->link->getProductLink(
            $product,
            $this->getFieldValue($product, 'link_rewrite', $this->context->language->id),
            Category::getLinkRewrite($this->getFieldValue($product, 'id_category_default'), $this->context->language->id),
            null,
            $id_lang,
            (int)Context::getContext()->shop->id,
            0,
            $is_rewrite_active
        );

        if (!$product->active) {
            $admin_dir = dirname($_SERVER['PHP_SELF']);
            $admin_dir = substr($admin_dir, strrpos($admin_dir, '/') + 1);
            $preview_url .= ((strpos($preview_url, '?') === false) ? '?' : '&').'adtoken='.$this->token.'&ad='.$admin_dir.'&id_employee='.(int)$this->context->employee->id;
        }

        return $preview_url;
    }

    /**
     * Post treatment for suppliers
     *
     * @param null|int $id_product
     */
    public function processSuppliers($id_product = null)
    {
        $id_product = (int) $id_product ? $id_product : (int)Tools::getValue('id_product');

        if ((int)Tools::getValue('supplier_loaded') === 1 && Validate::isLoadedObject($product = new Product($id_product))) {
            // Get all id_product_attribute
            $attributes = $product->getAttributesResume($this->context->language->id);
            if (empty($attributes)) {
                $attributes[] = array(
                    'id_product_attribute' => 0,
                    'attribute_designation' => ''
                );
            }

            // Get all available suppliers
            $suppliers = Supplier::getSuppliers();

            // Get already associated suppliers
            $associated_suppliers = ProductSupplier::getSupplierCollection($product->id);

            $suppliers_to_associate = array();
            $new_default_supplier = 0;

            if (Tools::isSubmit('default_supplier')) {
                $new_default_supplier = (int)Tools::getValue('default_supplier');
            }

            // Get new associations
            foreach ($suppliers as $supplier) {
                if (Tools::isSubmit('check_supplier_'.$supplier['id_supplier'])) {
                    $suppliers_to_associate[] = $supplier['id_supplier'];
                }
            }

            // Delete already associated suppliers if needed
            foreach ($associated_suppliers as $key => $associated_supplier) {
                /** @var ProductSupplier $associated_supplier */
                if (!in_array($associated_supplier->id_supplier, $suppliers_to_associate)) {
                    $associated_supplier->delete();
                    unset($associated_suppliers[$key]);
                }
            }

            // Associate suppliers
            foreach ($suppliers_to_associate as $id) {
                $to_add = true;
                foreach ($associated_suppliers as $as) {
                    /** @var ProductSupplier $as */
                    if ($id == $as->id_supplier) {
                        $to_add = false;
                    }
                }

                if ($to_add) {
                    $product_supplier = new ProductSupplier();
                    $product_supplier->id_product = $product->id;
                    $product_supplier->id_product_attribute = 0;
                    $product_supplier->id_supplier = $id;
                    if ($this->context->currency->id) {
                        $product_supplier->id_currency = (int)$this->context->currency->id;
                    } else {
                        $product_supplier->id_currency = (int)Configuration::get('PS_CURRENCY_DEFAULT');
                    }
                    $product_supplier->save();

                    $associated_suppliers[] = $product_supplier;
                    foreach ($attributes as $attribute) {
                        if ((int)$attribute['id_product_attribute'] > 0) {
                            $product_supplier = new ProductSupplier();
                            $product_supplier->id_product = $product->id;
                            $product_supplier->id_product_attribute = (int)$attribute['id_product_attribute'];
                            $product_supplier->id_supplier = $id;
                            $product_supplier->save();
                        }
                    }
                }
            }

            // Manage references and prices
            foreach ($attributes as $attribute) {
                foreach ($associated_suppliers as $supplier) {
                    /** @var ProductSupplier $supplier */
                    if (Tools::isSubmit('supplier_reference_'.$product->id.'_'.$attribute['id_product_attribute'].'_'.$supplier->id_supplier) ||
                        (Tools::isSubmit('product_price_'.$product->id.'_'.$attribute['id_product_attribute'].'_'.$supplier->id_supplier) &&
                         Tools::isSubmit('product_price_currency_'.$product->id.'_'.$attribute['id_product_attribute'].'_'.$supplier->id_supplier))) {
                        $reference = pSQL(
                            Tools::getValue(
                                'supplier_reference_'.$product->id.'_'.$attribute['id_product_attribute'].'_'.$supplier->id_supplier,
                                ''
                            )
                        );

                        $price = (float)str_replace(
                            array(' ', ','),
                            array('', '.'),
                            Tools::getValue(
                                'product_price_'.$product->id.'_'.$attribute['id_product_attribute'].'_'.$supplier->id_supplier,
                                0
                            )
                        );

                        $price = Tools::ps_round($price, 6);

                        $id_currency = (int)Tools::getValue(
                            'product_price_currency_'.$product->id.'_'.$attribute['id_product_attribute'].'_'.$supplier->id_supplier,
                            0
                        );

                        if ($id_currency <= 0 || (!($result = Currency::getCurrency($id_currency)) || empty($result))) {
                            $this->errors[] = $this->trans('The selected currency is not valid', array(), 'Admin.Catalog.Notification');
                        }

                        // Save product-supplier data
                        $product_supplier_id = (int)ProductSupplier::getIdByProductAndSupplier($product->id, $attribute['id_product_attribute'], $supplier->id_supplier);

                        if (!$product_supplier_id) {
                            $product->addSupplierReference($supplier->id_supplier, (int)$attribute['id_product_attribute'], $reference, (float)$price, (int)$id_currency);
                            if ($product->id_supplier == $supplier->id_supplier) {
                                if ((int)$attribute['id_product_attribute'] > 0) {
                                    $data = array(
                                        'supplier_reference' => pSQL($reference),
                                        'wholesale_price' => (float)Tools::convertPrice($price, $id_currency)
                                    );
                                    $where = '
										a.id_product = '.(int)$product->id.'
										AND a.id_product_attribute = '.(int)$attribute['id_product_attribute'];
                                    ObjectModel::updateMultishopTable('Combination', $data, $where);
                                } else {
                                    $product->wholesale_price = (float)Tools::convertPrice($price, $id_currency); //converted in the default currency
                                    $product->supplier_reference = pSQL($reference);
                                    $product->update();
                                }
                            }
                        } else {
                            $product_supplier = new ProductSupplier($product_supplier_id);
                            $product_supplier->id_currency = (int)$id_currency;
                            $product_supplier->product_supplier_price_te = (float)$price;
                            $product_supplier->product_supplier_reference = pSQL($reference);
                            $product_supplier->update();
                        }
                    } elseif (Tools::isSubmit('supplier_reference_'.$product->id.'_'.$attribute['id_product_attribute'].'_'.$supplier->id_supplier)) {
                        //int attribute with default values if possible
                        if ((int)$attribute['id_product_attribute'] > 0) {
                            $product_supplier = new ProductSupplier();
                            $product_supplier->id_product = $product->id;
                            $product_supplier->id_product_attribute = (int)$attribute['id_product_attribute'];
                            $product_supplier->id_supplier = $supplier->id_supplier;
                            $product_supplier->save();
                        }
                    }
                }
            }
            // Manage defaut supplier for product
            if ($this->object && $new_default_supplier != $product->id_supplier) {
                $this->object->id_supplier = $new_default_supplier;
                $this->object->update();
            }
        }
    }

    /**
    * Post treatment for warehouses
    */
    public function processWarehouses()
    {
        if ((int)Tools::getValue('warehouse_loaded') === 1 && Validate::isLoadedObject($product = new Product((int)$id_product = Tools::getValue('id_product')))) {
            // Get all id_product_attribute
            $attributes = $product->getAttributesResume($this->context->language->id);
            if (empty($attributes)) {
                $attributes[] = array(
                    'id_product_attribute' => 0,
                    'attribute_designation' => ''
                );
            }

            // Get all available warehouses
            $warehouses = Warehouse::getWarehouses(true);

            // Get already associated warehouses
            $associated_warehouses_collection = WarehouseProductLocation::getCollection($product->id);

            $elements_to_manage = array();

            // get form inforamtion
            foreach ($attributes as $attribute) {
                foreach ($warehouses as $warehouse) {
                    $key = $warehouse['id_warehouse'].'_'.$product->id.'_'.$attribute['id_product_attribute'];

                    // get elements to manage
                    if (Tools::isSubmit('check_warehouse_'.$key)) {
                        $location = Tools::getValue('location_warehouse_'.$key, '');
                        $elements_to_manage[$key] = $location;
                    }
                }
            }

            // Delete entry if necessary
            foreach ($associated_warehouses_collection as $awc) {
                /** @var WarehouseProductLocation $awc */
                if (!array_key_exists($awc->id_warehouse.'_'.$awc->id_product.'_'.$awc->id_product_attribute, $elements_to_manage)) {
                    $awc->delete();
                }
            }

            // Manage locations
            foreach ($elements_to_manage as $key => $location) {
                $params = explode('_', $key);

                $wpl_id = (int)WarehouseProductLocation::getIdByProductAndWarehouse((int)$params[1], (int)$params[2], (int)$params[0]);

                if (empty($wpl_id)) {
                    //create new record
                    $warehouse_location_entity = new WarehouseProductLocation();
                    $warehouse_location_entity->id_product = (int)$params[1];
                    $warehouse_location_entity->id_product_attribute = (int)$params[2];
                    $warehouse_location_entity->id_warehouse = (int)$params[0];
                    $warehouse_location_entity->location = pSQL($location);
                    $warehouse_location_entity->save();
                } else {
                    $warehouse_location_entity = new WarehouseProductLocation((int)$wpl_id);

                    $location = pSQL($location);

                    if ($location != $warehouse_location_entity->location) {
                        $warehouse_location_entity->location = pSQL($location);
                        $warehouse_location_entity->update();
                    }
                }
            }
            StockAvailable::synchronize((int)$id_product);
        }
    }

    /**
     * Get an array of pack items for display from the product object if specified, else from POST/GET values
     *
     * @param Product $product
     * @return array of pack items
     */
    public function getPackItems($product = null)
    {
        $pack_items = array();

        if (!$product) {
            $names_input = Tools::getValue('namePackItems');
            $ids_input = Tools::getValue('inputPackItems');
            if (!$names_input || !$ids_input) {
                return array();
            }
            // ids is an array of string with format : QTYxID
            $ids = array_unique(explode('-', $ids_input));
            $names = array_unique(explode('¤', $names_input));

            if (!empty($ids)) {
                $length = count($ids);
                for ($i = 0; $i < $length; $i++) {
                    if (!empty($ids[$i]) && !empty($names[$i])) {
                        list($pack_items[$i]['pack_quantity'], $pack_items[$i]['id']) = explode('x', $ids[$i]);
                        $exploded_name = explode('x', $names[$i]);
                        $pack_items[$i]['name'] = $exploded_name[1];
                    }
                }
            }
        } else {
            $i = 0;
            foreach ($product->packItems as $pack_item) {
                $pack_items[$i]['id'] = $pack_item->id;
                $pack_items[$i]['pack_quantity'] = $pack_item->pack_quantity;
                $pack_items[$i]['name']    = $pack_item->name;
                $pack_items[$i]['reference'] = $pack_item->reference;
                $pack_items[$i]['id_product_attribute'] = isset($pack_item->id_pack_product_attribute) && $pack_item->id_pack_product_attribute ? $pack_item->id_pack_product_attribute : 0;
                $cover = $pack_item->id_pack_product_attribute ? Product::getCombinationImageById($pack_item->id_pack_product_attribute, Context::getContext()->language->id) : Product::getCover($pack_item->id);
                $pack_items[$i]['image'] = Context::getContext()->link->getImageLink($pack_item->link_rewrite, $cover['id_image'], 'home_default');
                // @todo: don't rely on 'home_default'
                //$path_to_image = _PS_IMG_DIR_.'p/'.Image::getImgFolderStatic($cover['id_image']).(int)$cover['id_image'].'.jpg';
                //$pack_items[$i]['image'] = ImageManager::thumbnail($path_to_image, 'pack_mini_'.$pack_item->id.'_'.$this->context->shop->id.'.jpg', 120);
                $i++;
            }
        }
        return $pack_items;
    }

    protected function _getFinalPrice($specific_price, $product_price, $tax_rate)
    {
        return $this->object->getPrice(false, $specific_price['id_product_attribute'], 2);
    }

    protected function _displaySpecificPriceModificationForm($defaultCurrency, $shops, $currencies, $countries, $groups)
    {
        /** @var Product $obj */
        if (!($obj = $this->loadObject())) {
            return;
        }

        $page = (int)Tools::getValue('page');
        $content = '';
        $specific_prices = SpecificPrice::getByProductId((int)$obj->id);
        $specific_price_priorities = SpecificPrice::getPriority((int)$obj->id);

        $tmp = array();
        foreach ($shops as $shop) {
            $tmp[$shop['id_shop']] = $shop;
        }
        $shops = $tmp;
        $tmp = array();
        foreach ($currencies as $currency) {
            $tmp[$currency['id_currency']] = $currency;
        }
        $currencies = $tmp;

        $tmp = array();
        foreach ($countries as $country) {
            $tmp[$country['id_country']] = $country;
        }
        $countries = $tmp;

        $tmp = array();
        foreach ($groups as $group) {
            $tmp[$group['id_group']] = $group;
        }
        $groups = $tmp;

        $length_before = strlen($content);
        if (is_array($specific_prices) && count($specific_prices)) {
            $i = 0;
            foreach ($specific_prices as $specific_price) {
                $id_currency = $specific_price['id_currency'] ? $specific_price['id_currency'] : $defaultCurrency->id;
                if (!isset($currencies[$id_currency])) {
                    continue;
                }

                $current_specific_currency = $currencies[$id_currency];
                if ($specific_price['reduction_type'] == 'percentage') {
                    $impact = '- '.($specific_price['reduction'] * 100).' %';
                } elseif ($specific_price['reduction'] > 0) {
                    $impact = '- '.Tools::displayPrice(Tools::ps_round($specific_price['reduction'], 2), $current_specific_currency).' ';
                    if ($specific_price['reduction_tax']) {
                        $impact .= '('.$this->l('Tax incl.').')';
                    } else {
                        $impact .= '('.$this->l('Tax excl.').')';
                    }
                } else {
                    $impact = '--';
                }

                if ($specific_price['from'] == '0000-00-00 00:00:00' && $specific_price['to'] == '0000-00-00 00:00:00') {
                    $period = $this->l('Unlimited');
                } else {
                    $period = $this->l('From').' '.($specific_price['from'] != '0000-00-00 00:00:00' ? $specific_price['from'] : '0000-00-00 00:00:00').'<br />'.$this->l('To').' '.($specific_price['to'] != '0000-00-00 00:00:00' ? $specific_price['to'] : '0000-00-00 00:00:00');
                }
                if ($specific_price['id_product_attribute']) {
                    $combination = new Combination((int)$specific_price['id_product_attribute']);
                    $attributes = $combination->getAttributesName((int)$this->context->language->id);
                    $attributes_name = '';
                    foreach ($attributes as $attribute) {
                        $attributes_name .= $attribute['name'].' - ';
                    }
                    $attributes_name = rtrim($attributes_name, ' - ');
                } else {
                    $attributes_name = $this->l('All combinations');
                }

                $rule = new SpecificPriceRule((int)$specific_price['id_specific_price_rule']);
                $rule_name = ($rule->id ? $rule->name : '--');

                if ($specific_price['id_customer']) {
                    $customer = new Customer((int)$specific_price['id_customer']);
                    if (Validate::isLoadedObject($customer)) {
                        $customer_full_name = $customer->firstname.' '.$customer->lastname;
                    }
                    unset($customer);
                }

                if (!$specific_price['id_shop'] || in_array($specific_price['id_shop'], Shop::getContextListShopID())) {
                    $content .= '
					<tr '.($i % 2 ? 'class="alt_row"' : '').'>
						<td>'.$rule_name.'</td>
						<td>'.$attributes_name.'</td>';

                    $can_delete_specific_prices = true;
                    if (Shop::isFeatureActive()) {
                        $id_shop_sp = $specific_price['id_shop'];
                        $can_delete_specific_prices = (count($this->context->employee->getAssociatedShops()) > 1 && !$id_shop_sp) || $id_shop_sp;
                        $content .= '
						<td>'.($id_shop_sp ? $shops[$id_shop_sp]['name'] : $this->l('All shops')).'</td>';
                    }
                    $price = Tools::ps_round($specific_price['price'], 2);
                    $fixed_price = ($price == Tools::ps_round($obj->price, 2) || $specific_price['price'] == -1) ? '--' : Tools::displayPrice($price, $current_specific_currency);
                    $content .= '
						<td>'.($specific_price['id_currency'] ? $currencies[$specific_price['id_currency']]['name'] : $this->l('All currencies')).'</td>
						<td>'.($specific_price['id_country'] ? $countries[$specific_price['id_country']]['name'] : $this->l('All countries')).'</td>
						<td>'.($specific_price['id_group'] ? $groups[$specific_price['id_group']]['name'] : $this->l('All groups')).'</td>
						<td title="'.$this->l('ID:').' '.$specific_price['id_customer'].'">'.(isset($customer_full_name) ? $customer_full_name : $this->l('All customers')).'</td>
						<td>'.$fixed_price.'</td>
						<td>'.$impact.'</td>
						<td>'.$period.'</td>
						<td>'.$specific_price['from_quantity'].'</th>
						<td>'.((!$rule->id && $can_delete_specific_prices) ? '<a class="btn btn-default" name="delete_link" href="'.self::$currentIndex.'&id_product='.(int)Tools::getValue('id_product').'&action=deleteSpecificPrice&id_specific_price='.(int)($specific_price['id_specific_price']).'&token='.Tools::getValue('token').'"><i class="icon-trash"></i></a>': '').'</td>
					</tr>';
                    $i++;
                    unset($customer_full_name);
                }
            }
        }

        if ($length_before === strlen($content)) {
            $content .= '
				<tr>
					<td class="text-center" colspan="13"><i class="icon-warning-sign"></i>&nbsp;'.$this->l('No specific prices.').'</td>
				</tr>';
        }

        $content .= '
				</tbody>
			</table>
			</div>
			<div class="panel-footer">
				<a href="'.$this->context->link->getAdminLink('AdminProducts').($page > 1 ? '&submitFilter'.$this->table.'='.(int)$page : '').'" class="btn btn-default"><i class="process-icon-cancel"></i> '.$this->trans('Cancel', array(), 'Admin.Actions').'</a>
				<button id="product_form_submit_btn"  type="submit" name="submitAddproduct" class="btn btn-default pull-right" disabled="disabled"><i class="process-icon-loading"></i> '.$this->trans('Save', array(), 'Admin.Actions') .'</button>
				<button id="product_form_submit_btn"  type="submit" name="submitAddproductAndStay" class="btn btn-default pull-right" disabled="disabled"><i class="process-icon-loading"></i> '.$this->l('Save and stay') .'</button>
			</div>
		</div>';

        $content .= '
		<script type="text/javascript">
			var currencies = new Array();
			currencies[0] = new Array();
			currencies[0]["sign"] = "'.$defaultCurrency->sign.'";
			currencies[0]["format"] = "'.intval($defaultCurrency->format).'";
			';
        foreach ($currencies as $currency) {
            $content .= '
				currencies['.$currency['id_currency'].'] = new Array();
				currencies['.$currency['id_currency'].']["sign"] = "'.$currency['sign'].'";
				currencies['.$currency['id_currency'].']["format"] = "";
				';
        }
        $content .= '
		</script>
		';

        // Not use id_customer
        if ($specific_price_priorities[0] == 'id_customer') {
            unset($specific_price_priorities[0]);
        }
        // Reindex array starting from 0
        $specific_price_priorities = array_values($specific_price_priorities);

        $content .= '<div class="panel">
		<h3>'.$this->l('Priority management').'</h3>
		<div class="alert alert-info">
				'.$this->l('Sometimes one customer can fit into multiple price rules. Priorities allow you to define which rule applies to the customer.').'
		</div>';

        $content .= '
		<div class="form-group">
			<label class="control-label col-lg-3" for="specificPricePriority1">'.$this->l('Priorities').'</label>
			<div class="input-group col-lg-9">
				<select id="specificPricePriority1" name="specificPricePriority[]">
					<option value="id_shop"'.($specific_price_priorities[0] == 'id_shop' ? ' selected="selected"' : '').'>'.$this->trans('Shop', array(), 'Admin.Global').'</option>
					<option value="id_currency"'.($specific_price_priorities[0] == 'id_currency' ? ' selected="selected"' : '').'>'.$this->l('Currency').'</option>
					<option value="id_country"'.($specific_price_priorities[0] == 'id_country' ? ' selected="selected"' : '').'>'.$this->trans('Country', array(), 'Admin.Global').'</option>
					<option value="id_group"'.($specific_price_priorities[0] == 'id_group' ? ' selected="selected"' : '').'>'.$this->trans('Group', array(), 'Admin.Global').'</option>
				</select>
				<span class="input-group-addon"><i class="icon-chevron-right"></i></span>
				<select name="specificPricePriority[]">
					<option value="id_shop"'.($specific_price_priorities[1] == 'id_shop' ? ' selected="selected"' : '').'>'.$this->trans('Shop', array(), 'Admin.Global').'</option>
					<option value="id_currency"'.($specific_price_priorities[1] == 'id_currency' ? ' selected="selected"' : '').'>'.$this->l('Currency').'</option>
					<option value="id_country"'.($specific_price_priorities[1] == 'id_country' ? ' selected="selected"' : '').'>'.$this->trans('Country', array(), 'Admin.Global').'</option>
					<option value="id_group"'.($specific_price_priorities[1] == 'id_group' ? ' selected="selected"' : '').'>'.$this->trans('Group', array(), 'Admin.Global').'</option>
				</select>
				<span class="input-group-addon"><i class="icon-chevron-right"></i></span>
				<select name="specificPricePriority[]">
					<option value="id_shop"'.($specific_price_priorities[2] == 'id_shop' ? ' selected="selected"' : '').'>'.$this->trans('Shop', array(), 'Admin.Global').'</option>
					<option value="id_currency"'.($specific_price_priorities[2] == 'id_currency' ? ' selected="selected"' : '').'>'.$this->l('Currency').'</option>
					<option value="id_country"'.($specific_price_priorities[2] == 'id_country' ? ' selected="selected"' : '').'>'.$this->trans('Country', array(), 'Admin.Global').'</option>
					<option value="id_group"'.($specific_price_priorities[2] == 'id_group' ? ' selected="selected"' : '').'>'.$this->trans('Group', array(), 'Admin.Global').'</option>
				</select>
				<span class="input-group-addon"><i class="icon-chevron-right"></i></span>
				<select name="specificPricePriority[]">
					<option value="id_shop"'.($specific_price_priorities[3] == 'id_shop' ? ' selected="selected"' : '').'>'.$this->trans('Shop', array(), 'Admin.Global').'</option>
					<option value="id_currency"'.($specific_price_priorities[3] == 'id_currency' ? ' selected="selected"' : '').'>'.$this->l('Currency').'</option>
					<option value="id_country"'.($specific_price_priorities[3] == 'id_country' ? ' selected="selected"' : '').'>'.$this->trans('Country', array(), 'Admin.Global').'</option>
					<option value="id_group"'.($specific_price_priorities[3] == 'id_group' ? ' selected="selected"' : '').'>'.$this->trans('Group', array(), 'Admin.Global').'</option>
				</select>
			</div>
		</div>
		<div class="form-group">
			<div class="col-lg-9 col-lg-offset-3">
				<p class="checkbox">
					<label for="specificPricePriorityToAll"><input type="checkbox" name="specificPricePriorityToAll" id="specificPricePriorityToAll" />'.$this->l('Apply to all products').'</label>
				</p>
			</div>
		</div>
		<div class="panel-footer">
				<a href="'.$this->context->link->getAdminLink('AdminProducts').($page > 1 ? '&submitFilter'.$this->table.'='.(int)$page : '').'" class="btn btn-default"><i class="process-icon-cancel"></i> '.$this->trans('Cancel', array(), 'Admin.Actions').'</a>
				<button id="product_form_submit_btn"  type="submit" name="submitAddproduct" class="btn btn-default pull-right" disabled="disabled"><i class="process-icon-loading"></i> '.$this->trans('Save', array(), 'Admin.Actions') .'</button>
				<button id="product_form_submit_btn"  type="submit" name="submitAddproductAndStay" class="btn btn-default pull-right" disabled="disabled"><i class="process-icon-loading"></i> '.$this->l('Save and stay') .'</button>
			</div>
		</div>
		';
        return $content;
    }

    protected function _getCustomizationFieldIds($labels, $alreadyGenerated, $obj)
    {
        $customizableFieldIds = array();
        if (isset($labels[Product::CUSTOMIZE_FILE])) {
            foreach ($labels[Product::CUSTOMIZE_FILE] as $id_customization_field => $label) {
                $customizableFieldIds[] = 'label_'.Product::CUSTOMIZE_FILE.'_'.(int)($id_customization_field);
            }
        }
        if (isset($labels[Product::CUSTOMIZE_TEXTFIELD])) {
            foreach ($labels[Product::CUSTOMIZE_TEXTFIELD] as $id_customization_field => $label) {
                $customizableFieldIds[] = 'label_'.Product::CUSTOMIZE_TEXTFIELD.'_'.(int)($id_customization_field);
            }
        }
        $j = 0;
        for ($i = $alreadyGenerated[Product::CUSTOMIZE_FILE]; $i < (int)($this->getFieldValue($obj, 'uploadable_files')); $i++) {
            $customizableFieldIds[] = 'newLabel_'.Product::CUSTOMIZE_FILE.'_'.$j++;
        }
        $j = 0;
        for ($i = $alreadyGenerated[Product::CUSTOMIZE_TEXTFIELD]; $i < (int)($this->getFieldValue($obj, 'text_fields')); $i++) {
            $customizableFieldIds[] = 'newLabel_'.Product::CUSTOMIZE_TEXTFIELD.'_'.$j++;
        }
        return implode('¤', $customizableFieldIds);
    }

    protected function _displayLabelField(&$label, $languages, $default_language, $type, $fieldIds, $id_customization_field)
    {
        foreach ($languages as $language) {
            $input_value[$language['id_lang']] = (isset($label[(int)($language['id_lang'])])) ? $label[(int)($language['id_lang'])]['name'] : '';
        }

        $required = (isset($label[(int)($language['id_lang'])])) ? $label[(int)($language['id_lang'])]['required'] : false;

        $template = $this->context->smarty->createTemplate('controllers/products/input_text_lang.tpl',
            $this->context->smarty);
        return '<div class="form-group">'
            .'<div class="col-lg-6">'
            .$template->assign(array(
                'languages' => $languages,
                'input_name'  => 'label_'.$type.'_'.(int)($id_customization_field),
                'input_value' => $input_value
            ))->fetch()
            .'</div>'
            .'<div class="col-lg-6">'
            .'<div class="checkbox">'
            .'<label for="require_'.$type.'_'.(int)($id_customization_field).'">'
            .'<input type="checkbox" name="require_'.$type.'_'.(int)($id_customization_field).'" id="require_'.$type.'_'.(int)($id_customization_field).'" value="1" '.($required ? 'checked="checked"' : '').'/>'
            .$this->trans('Required', array(), 'Admin.Global')
            .'</label>'
            .'</div>'
            .'</div>'
            .'</div>';
    }

    protected function _displayLabelFields(&$obj, &$labels, $languages, $default_language, $type)
    {
        $content = '';
        $type = (int)($type);
        $labelGenerated = array(Product::CUSTOMIZE_FILE => (isset($labels[Product::CUSTOMIZE_FILE]) ? count($labels[Product::CUSTOMIZE_FILE]) : 0), Product::CUSTOMIZE_TEXTFIELD => (isset($labels[Product::CUSTOMIZE_TEXTFIELD]) ? count($labels[Product::CUSTOMIZE_TEXTFIELD]) : 0));

        $fieldIds = $this->_getCustomizationFieldIds($labels, $labelGenerated, $obj);
        if (isset($labels[$type])) {
            foreach ($labels[$type] as $id_customization_field => $label) {
                $content .= $this->_displayLabelField($label, $languages, $default_language, $type, $fieldIds, (int)($id_customization_field));
            }
        }
        return $content;
    }

    protected function getCarrierList()
    {
        $carrier_list = Carrier::getCarriers($this->context->language->id, false, false, false, null, Carrier::ALL_CARRIERS);

        if ($product = $this->loadObject(true)) {
            /** @var Product $product */
            $carrier_selected_list = $product->getCarriers();
            foreach ($carrier_list as &$carrier) {
                foreach ($carrier_selected_list as $carrier_selected) {
                    if ($carrier_selected['id_reference'] == $carrier['id_reference']) {
                        $carrier['selected'] = true;
                        continue;
                    }
                }
            }
        }
        return $carrier_list;
    }

    protected function addCarriers($product = null)
    {
        if (!isset($product)) {
            $product = new Product((int)Tools::getValue('id_product'));
        }

        if (Validate::isLoadedObject($product)) {
            $carriers = array();

            if (Tools::getValue('selectedCarriers')) {
                $carriers = Tools::getValue('selectedCarriers');
            }

            $product->setCarriers($carriers);
        }
    }

    /**
     * Ajax process upload images
     *
     * @param int|null $idProduct
     * @param string $inputFileName
     * @param bool $die If method must die or return values
     *
     * @return array
     */
    public function ajaxProcessaddProductImage($idProduct = null, $inputFileName='file', $die = true)
    {
        $idProduct = $idProduct ? $idProduct : Tools::getValue('id_product');

        self::$currentIndex = 'index.php?tab=AdminProducts';
        $product = new Product((int)$idProduct);
        $legends = Tools::getValue('legend');

        if (!is_array($legends)) {
            $legends = (array)$legends;
        }

        if (!Validate::isLoadedObject($product)) {
            $files = array();
            $files[0]['error'] = $this->trans('Cannot add image because product creation failed.', array(), 'Admin.Catalog.Notification');
        }

        $image_uploader = new HelperImageUploader($inputFileName);
        $image_uploader->setAcceptTypes(array('jpeg', 'gif', 'png', 'jpg'))->setMaxSize($this->max_image_size);
        $files = $image_uploader->process();

        foreach ($files as &$file) {
            $image = new Image();
            $image->id_product = (int)($product->id);
            $image->position = Image::getHighestPosition($product->id) + 1;

            foreach ($legends as $key => $legend) {
                if (!empty($legend)) {
                    $image->legend[(int)$key] = $legend;
                }
            }

            if (!Image::getCover($image->id_product)) {
                $image->cover = 1;
            } else {
                $image->cover = 0;
            }

            if (($validate = $image->validateFieldsLang(false, true)) !== true) {
                $file['error'] = $validate;
            }

            if (isset($file['error']) && (!is_numeric($file['error']) || $file['error'] != 0)) {
                continue;
            }

            if (!$image->add()) {
                $file['error'] = $this->trans('Error while creating additional image', array(), 'Admin.Catalog.Notification');
            } else {
                if (!$new_path = $image->getPathForCreation()) {
                    $file['error'] = $this->trans('An error occurred while attempting to create a new folder.', array(), 'Admin.Notifications.Error');
                    continue;
                }

                $error = 0;

                if (!ImageManager::resize($file['save_path'], $new_path.'.'.$image->image_format, null, null, 'jpg', false, $error)) {
                    switch ($error) {
                        case ImageManager::ERROR_FILE_NOT_EXIST :
                            $file['error'] = $this->trans('An error occurred while copying image, the file does not exist anymore.', array(), 'Admin.Catalog.Notification');
                            break;

                        case ImageManager::ERROR_FILE_WIDTH :
                            $file['error'] = $this->trans('An error occurred while copying image, the file width is 0px.', array(), 'Admin.Catalog.Notification');
                            break;

                        case ImageManager::ERROR_MEMORY_LIMIT :
                            $file['error'] = $this->trans('An error occurred while copying image, check your memory limit.', array(), 'Admin.Catalog.Notification');
                            break;

                        default:
                            $file['error'] = $this->trans('An error occurred while copying the image.', array(), 'Admin.Catalog.Notification');
                            break;
                    }
                    continue;
                } else {
                    $imagesTypes = ImageType::getImagesTypes('products');
                    $generate_hight_dpi_images = (bool)Configuration::get('PS_HIGHT_DPI');

                    foreach ($imagesTypes as $imageType) {
                        if (!ImageManager::resize($file['save_path'], $new_path.'-'.stripslashes($imageType['name']).'.'.$image->image_format, $imageType['width'], $imageType['height'], $image->image_format)) {
                            $file['error'] = $this->trans('An error occurred while copying this image:', array(), 'Admin.Notifications.Error').' '.stripslashes($imageType['name']);
                            continue;
                        }

                        if ($generate_hight_dpi_images) {
                            if (!ImageManager::resize($file['save_path'], $new_path.'-'.stripslashes($imageType['name']).'2x.'.$image->image_format, (int)$imageType['width']*2, (int)$imageType['height']*2, $image->image_format)) {
                                $file['error'] = $this->trans('An error occurred while copying this image:', array(), 'Admin.Notifications.Error').' '.stripslashes($imageType['name']);
                                continue;
                            }
                        }
                    }
                }

                unlink($file['save_path']);
                //Necesary to prevent hacking
                unset($file['save_path']);
                Hook::exec('actionWatermark', array('id_image' => $image->id, 'id_product' => $product->id));

                if (!$image->update()) {
                    $file['error'] = $this->trans('Error while updating the status.', array(), 'Admin.Notifications.Error');
                    continue;
                }

                // Associate image to shop from context
                $shops = Shop::getContextListShopID();
                $image->associateTo($shops);
                $json_shops = array();

                foreach ($shops as $id_shop) {
                    $json_shops[$id_shop] = true;
                }

                $file['status']   = 'ok';
                $file['id']       = $image->id;
                $file['position'] = $image->position;
                $file['cover']    = $image->cover;
                $file['legend']   = $image->legend;
                $file['path']     = $image->getExistingImgPath();
                $file['shops']    = $json_shops;

                @unlink(_PS_TMP_IMG_DIR_.'product_'.(int)$product->id.'.jpg');
                @unlink(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$product->id.'_'.$this->context->shop->id.'.jpg');
            }
        }

        if ($die) {
            die(json_encode(array($image_uploader->getName() => $files)));
        } else {
            return $files;
        }
    }

    /**
     * @param Product $product
     * @param Currency|array|int $currency
     * @return string
     */
    public function renderListAttributes($product, $currency)
    {
        $this->bulk_actions = array('delete' => array('text' => $this->l('Delete selected'), 'confirm' => $this->l('Delete selected items?')));
        $this->addRowAction('edit');
        $this->addRowAction('default');
        $this->addRowAction('delete');

        $default_class = 'highlighted';

        $this->fields_list = array(
            'attributes' => array('title' => $this->l('Attribute - value pair'), 'align' => 'left'),
            'price' => array('title' => $this->l('Impact on price'), 'type' => 'price', 'align' => 'left'),
            'weight' => array('title' => $this->l('Impact on weight'), 'align' => 'left'),
            'reference' => array('title' => $this->trans('Reference', array(), 'Admin.Global'), 'align' => 'left'),
            'ean13' => array('title' => $this->l('EAN-13'), 'align' => 'left'),
            'isbn' => array('title' => $this->l('ISBN'), 'align' => 'left'),
            'upc' => array('title' => $this->l('UPC'), 'align' => 'left')
        );

        if ($product->id) {
            /* Build attributes combinations */
            $combinations = $product->getAttributeCombinations($this->context->language->id);
            $groups = array();
            $comb_array = array();
            if (is_array($combinations)) {
                $combination_images = $product->getCombinationImages($this->context->language->id);
                foreach ($combinations as $k => $combination) {
                    $price_to_convert = Tools::convertPrice($combination['price'], $currency);
                    $price = Tools::displayPrice($price_to_convert, $currency);

                    $comb_array[$combination['id_product_attribute']]['id_product_attribute'] = $combination['id_product_attribute'];
                    $comb_array[$combination['id_product_attribute']]['attributes'][] = array($combination['group_name'], $combination['attribute_name'], $combination['id_attribute']);
                    $comb_array[$combination['id_product_attribute']]['wholesale_price'] = $combination['wholesale_price'];
                    $comb_array[$combination['id_product_attribute']]['price'] = $price;
                    $comb_array[$combination['id_product_attribute']]['weight'] = $combination['weight'].Configuration::get('PS_WEIGHT_UNIT');
                    $comb_array[$combination['id_product_attribute']]['unit_impact'] = $combination['unit_price_impact'];
                    $comb_array[$combination['id_product_attribute']]['reference'] = $combination['reference'];
                    $comb_array[$combination['id_product_attribute']]['ean13'] = $combination['ean13'];
                    $comb_array[$combination['id_product_attribute']]['isbn'] = $combination['isbn'];
                    $comb_array[$combination['id_product_attribute']]['upc'] = $combination['upc'];
                    $comb_array[$combination['id_product_attribute']]['id_image'] = isset($combination_images[$combination['id_product_attribute']][0]['id_image']) ? $combination_images[$combination['id_product_attribute']][0]['id_image'] : 0;
                    $comb_array[$combination['id_product_attribute']]['available_date'] = strftime($combination['available_date']);
                    $comb_array[$combination['id_product_attribute']]['default_on'] = $combination['default_on'];
                    if ($combination['is_color_group']) {
                        $groups[$combination['id_attribute_group']] = $combination['group_name'];
                    }
                }
            }

            if (isset($comb_array)) {
                foreach ($comb_array as $id_product_attribute => $product_attribute) {
                    $list = '';

                    /* In order to keep the same attributes order */
                    asort($product_attribute['attributes']);

                    foreach ($product_attribute['attributes'] as $attribute) {
                        $list .= $attribute[0].' - '.$attribute[1].', ';
                    }

                    $list = rtrim($list, ', ');
                    $comb_array[$id_product_attribute]['image'] = $product_attribute['id_image'] ? new Image($product_attribute['id_image']) : false;
                    $comb_array[$id_product_attribute]['available_date'] = $product_attribute['available_date'] != 0 ? date('Y-m-d', strtotime($product_attribute['available_date'])) : '0000-00-00';
                    $comb_array[$id_product_attribute]['attributes'] = $list;
                    $comb_array[$id_product_attribute]['name'] = $list;

                    if ($product_attribute['default_on']) {
                        $comb_array[$id_product_attribute]['class'] = $default_class;
                    }
                }
            }
        }

        foreach ($this->actions_available as $action) {
            if (!in_array($action, $this->actions) && isset($this->$action) && $this->$action) {
                $this->actions[] = $action;
            }
        }

        $helper = new HelperList();
        $helper->identifier = 'id_product_attribute';
        $helper->table_id = 'combinations-list';
        $helper->token = $this->token;
        $helper->currentIndex = self::$currentIndex;
        $helper->no_link = true;
        $helper->simple_header = true;
        $helper->show_toolbar = false;
        $helper->shopLinkType = $this->shopLinkType;
        $helper->actions = $this->actions;
        $helper->list_skip_actions = $this->list_skip_actions;
        $helper->colorOnBackground = true;
        $helper->override_folder = $this->tpl_folder.'combination/';

        return $helper->generateList($comb_array, $this->fields_list);
    }

    public function ajaxProcessProductQuantity()
    {
        if (!$this->access('edit')) {
            return die(json_encode(array('error' => $this->l('You do not have the right permission'))));
        }
        if (!Tools::getValue('actionQty')) {
            return json_encode(array('error' => $this->l('Undefined action')));
        }

        $product = new Product((int)Tools::getValue('id_product'), true);
        switch (Tools::getValue('actionQty')) {
            case 'depends_on_stock':
                if (Tools::getValue('value') === false) {
                    die(json_encode(array('error' =>  $this->l('Undefined value'))));
                }
                if ((int)Tools::getValue('value') != 0 && (int)Tools::getValue('value') != 1) {
                    die(json_encode(array('error' =>  $this->l('Incorrect value'))));
                }
                if (!$product->advanced_stock_management && (int)Tools::getValue('value') == 1) {
                    die(json_encode(array('error' =>  $this->l('Not possible if advanced stock management is disabled. '))));
                }
                if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && (int)Tools::getValue('value') == 1 && (Pack::isPack($product->id) && !Pack::allUsesAdvancedStockManagement($product->id)
                    && ($product->pack_stock_type == 2 || $product->pack_stock_type == 1 ||
                        ($product->pack_stock_type == 3 && (Configuration::get('PS_PACK_STOCK_TYPE') == 1 || Configuration::get('PS_PACK_STOCK_TYPE') == 2))))) {
                    die(json_encode(array('error' => $this->l('You cannot use advanced stock management for this pack because').'<br />'.
                        $this->l('- advanced stock management is not enabled for these products').'<br />'.
                        $this->l('- you have chosen to decrement products quantities.'))));
                }

                StockAvailable::setProductDependsOnStock($product->id, (int)Tools::getValue('value'));
                break;

            case 'pack_stock_type':
                $value = Tools::getValue('value');
                if ($value === false) {
                    die(json_encode(array('error' =>  $this->l('Undefined value'))));
                }
                if ((int)$value != 0 && (int)$value != 1
                    && (int)$value != 2 && (int)$value != 3) {
                    die(json_encode(array('error' =>  $this->l('Incorrect value'))));
                }
                if ($product->depends_on_stock && !Pack::allUsesAdvancedStockManagement($product->id) && ((int)$value == 1
                    || (int)$value == 2 || ((int)$value == 3 && (Configuration::get('PS_PACK_STOCK_TYPE') == 1 || Configuration::get('PS_PACK_STOCK_TYPE') == 2)))) {
                    die(json_encode(array('error' => $this->l('You cannot use this stock management option because:').'<br />'.
                        $this->l('- advanced stock management is not enabled for these products').'<br />'.
                        $this->l('- advanced stock management is enabled for the pack'))));
                }

                Product::setPackStockType($product->id, $value);
                break;

            case 'out_of_stock':
                if (Tools::getValue('value') === false) {
                    die(json_encode(array('error' =>  $this->l('Undefined value'))));
                }
                if (!in_array((int)Tools::getValue('value'), array(0, 1, 2))) {
                    die(json_encode(array('error' =>  $this->l('Incorrect value'))));
                }

                StockAvailable::setProductOutOfStock($product->id, (int)Tools::getValue('value'));
                break;

            case 'set_qty':
                if (Tools::getValue('value') === false || (!is_numeric(trim(Tools::getValue('value'))))) {
                    die(json_encode(array('error' =>  $this->l('Undefined value'))));
                }
                if (Tools::getValue('id_product_attribute') === false) {
                    die(json_encode(array('error' =>  $this->l('Undefined id product attribute'))));
                }

                StockAvailable::setQuantity($product->id, (int)Tools::getValue('id_product_attribute'), (int)Tools::getValue('value'));
                Hook::exec('actionProductUpdate', array('id_product' => (int)$product->id, 'product' => $product));

                // Catch potential echo from modules
                // This echoed error is kept for legacy controllers, but is dropped during sf refactoring of the hook.
                $error = ob_get_contents();
                if (!empty($error)) {
                    ob_end_clean();
                    die(json_encode(array('error' => $error)));
                }
                break;
            case 'advanced_stock_management' :
                if (Tools::getValue('value') === false) {
                    die(json_encode(array('error' =>  $this->l('Undefined value'))));
                }
                if ((int)Tools::getValue('value') != 1 && (int)Tools::getValue('value') != 0) {
                    die(json_encode(array('error' =>  $this->l('Incorrect value'))));
                }
                if (!Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && (int)Tools::getValue('value') == 1) {
                    die(json_encode(array('error' =>  $this->l('Not possible if advanced stock management is disabled. '))));
                }

                $product->setAdvancedStockManagement((int)Tools::getValue('value'));
                if (StockAvailable::dependsOnStock($product->id) == 1 && (int)Tools::getValue('value') == 0) {
                    StockAvailable::setProductDependsOnStock($product->id, 0);
                }
                break;

        }
        die(json_encode(array('error' => false)));
    }

    public function getCombinationImagesJS()
    {
        /** @var Product $obj */
        if (!($obj = $this->loadObject(true))) {
            return;
        }

        $content = 'var combination_images = new Array();';
        if (!$allCombinationImages = $obj->getCombinationImages($this->context->language->id)) {
            return $content;
        }
        foreach ($allCombinationImages as $id_product_attribute => $combination_images) {
            $i = 0;
            $content .= 'combination_images['.(int)$id_product_attribute.'] = new Array();';
            foreach ($combination_images as $combination_image) {
                $content .= 'combination_images['.(int)$id_product_attribute.']['.$i++.'] = '.(int)$combination_image['id_image'].';';
            }
        }
        return $content;
    }

    public function haveThisAccessory($accessory_id, $accessories)
    {
        foreach ($accessories as $accessory) {
            if ((int)$accessory['id_product'] == (int)$accessory_id) {
                return true;
            }
        }
        return false;
    }

    protected function initPack(Product $product)
    {
        $this->tpl_form_vars['is_pack'] = ($product->id && Pack::isPack($product->id)) || Tools::getValue('type_product') == Product::PTYPE_PACK;
        $product->packItems = Pack::getItems($product->id, $this->context->language->id);

        $input_pack_items = '';
        if (Tools::getValue('inputPackItems')) {
            $input_pack_items = Tools::getValue('inputPackItems');
        } else {
            foreach ($product->packItems as $pack_item) {
                $input_pack_items .= $pack_item->pack_quantity.'x'.$pack_item->id.'-';
            }
        }
        $this->tpl_form_vars['input_pack_items'] = $input_pack_items;

        $input_namepack_items = '';
        if (Tools::getValue('namePackItems')) {
            $input_namepack_items = Tools::getValue('namePackItems');
        } else {
            foreach ($product->packItems as $pack_item) {
                $input_namepack_items .= $pack_item->pack_quantity.' x '.$pack_item->name.'¤';
            }
        }
        $this->tpl_form_vars['input_namepack_items'] = $input_namepack_items;
    }

    /**
     * delete all items in pack, then check if type_product value is 2.
     * if yes, add the pack items from input "inputPackItems"
     *
     * @param Product $product
     * @return bool
     */
    public function updatePackItems($product)
    {
        Pack::deleteItems($product->id);
        // lines format: QTY x ID-QTY x ID
        if (Tools::getValue('type_product') == Product::PTYPE_PACK) {
            $product->setDefaultAttribute(0);//reset cache_default_attribute
            $items = Tools::getValue('inputPackItems');
            $lines = array_unique(explode('-', $items));

            // lines is an array of string with format : QTYxIDxID_PRODUCT_ATTRIBUTE
            if (count($lines)) {
                foreach ($lines as $line) {
                    if (!empty($line)) {
                        $item_id_attribute = 0;
                        count($array = explode('x', $line)) == 3 ? list($qty, $item_id, $item_id_attribute) = $array : list($qty, $item_id) = $array;
                        if ($qty > 0 && isset($item_id)) {
                            if (Pack::isPack((int)$item_id)) {
                                $this->errors[] = $this->trans('You can\'t add product packs into a pack', array(), 'Admin.Catalog.Notification');
                            } elseif (!Pack::addItem((int)$product->id, (int)$item_id, (int)$qty, (int)$item_id_attribute)) {
                                $this->errors[] = $this->trans('An error occurred while attempting to add products to the pack.', array(), 'Admin.Catalog.Notification');
                            }
                        }
                    }
                }
            }
        }
    }

    public function getL($key)
    {
        $trad = array(
            'Default category:' => $this->l('Default category'),
            'Catalog:' => $this->l('Catalog'),
            'Consider changing the default category.' => $this->l('Consider changing the default category.'),
            'ID' => $this->trans('ID', array(), 'Admin.Global'),
            'Name' => $this->trans('Name', array(), 'Admin.Global'),
            'Mark all checkbox(es) of categories in which product is to appear' => $this->l('Mark the checkbox of each categories in which this product will appear.')
        );
        return $trad[$key];
    }

    public function ajaxProcessCheckProductName()
    {
        if ($this->access('view')) {
            $search = Tools::getValue('q');
            $id_lang = Tools::getValue('id_lang');
            $limit = Tools::getValue('limit');
            if (Context::getContext()->shop->getContext() != Shop::CONTEXT_SHOP) {
                $result = false;
            } else {
                $result = Db::getInstance()->executeS('
					SELECT DISTINCT pl.`name`, p.`id_product`, pl.`id_shop`
					FROM `'._DB_PREFIX_.'product` p
					LEFT JOIN `'._DB_PREFIX_.'product_shop` ps ON (ps.id_product = p.id_product AND ps.id_shop ='.(int)Context::getContext()->shop->id.')
					LEFT JOIN `'._DB_PREFIX_.'product_lang` pl
						ON (pl.`id_product` = p.`id_product` AND pl.`id_lang` = '.(int)$id_lang.')
					WHERE pl.`name` LIKE "%'.pSQL($search).'%" AND ps.id_product IS NULL
					GROUP BY pl.`id_product`
					LIMIT '.(int)$limit);
            }
            die(json_encode($result));
        }
    }

    public function ajaxProcessUpdatePositions()
    {
        if ($this->access('edit')) {
            $way = (int)(Tools::getValue('way'));
            $id_product = (int)Tools::getValue('id_product');
            $id_category = (int)Tools::getValue('id_category');
            $positions = Tools::getValue('product');
            $page = (int)Tools::getValue('page');
            $selected_pagination = (int)Tools::getValue('selected_pagination');

            if (is_array($positions)) {
                foreach ($positions as $position => $value) {
                    $pos = explode('_', $value);

                    if ((isset($pos[1]) && isset($pos[2])) && ($pos[1] == $id_category && (int)$pos[2] === $id_product)) {
                        if ($page > 1) {
                            $position = $position + (($page - 1) * $selected_pagination);
                        }

                        if ($product = new Product((int)$pos[2])) {
                            if (isset($position) && $product->updatePosition($way, $position)) {
                                $category = new Category((int)$id_category);
                                if (Validate::isLoadedObject($category)) {
                                    hook::Exec('categoryUpdate', array('category' => $category));
                                }
                                echo 'ok position '.(int)$position.' for product '.(int)$pos[2]."\r\n";
                            } else {
                                echo '{"hasError" : true, "errors" : "Can not update product '.(int)$id_product.' to position '.(int)$position.' "}';
                            }
                        } else {
                            echo '{"hasError" : true, "errors" : "This product ('.(int)$id_product.') can t be loaded"}';
                        }

                        break;
                    }
                }
            }
        }
    }

    public function ajaxProcessPublishProduct()
    {
        if ($this->access('edit')) {
            if ($id_product = (int)Tools::getValue('id_product')) {
                $bo_product_url = dirname($_SERVER['PHP_SELF']).'/index.php?tab=AdminProducts&id_product='.$id_product.'&updateproduct&token='.$this->token;

                if (Tools::getValue('redirect')) {
                    die($bo_product_url);
                }

                $product = new Product((int)$id_product);
                if (!Validate::isLoadedObject($product)) {
                    die('error: invalid id');
                }

                $product->active = 1;

                if ($product->save()) {
                    die($bo_product_url);
                } else {
                    die('error: saving');
                }
            }
        }
    }

    public function processImageLegends()
    {
        if (Tools::getValue('key_tab') == 'Images' && Tools::getValue('submitAddproductAndStay') == 'update_legends' && Validate::isLoadedObject($product = new Product((int)Tools::getValue('id_product')))) {
            $id_image = (int)Tools::getValue('id_caption');
            $language_ids = Language::getIDs(false);
            foreach ($_POST as $key => $val) {
                if (preg_match('/^legend_([0-9]+)/i', $key, $match)) {
                    foreach ($language_ids as $id_lang) {
                        if ($val && $id_lang == $match[1]) {
                            Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'image_lang SET legend = "'.pSQL($val).'" WHERE '.($id_image ? 'id_image = '.(int)$id_image : 'EXISTS (SELECT 1 FROM '._DB_PREFIX_.'image WHERE '._DB_PREFIX_.'image.id_image = '._DB_PREFIX_.'image_lang.id_image AND id_product = '.(int)$product->id.')').' AND id_lang = '.(int)$id_lang);
                        }
                    }
                }
            }
        }
    }

    public function displayPreviewLink($token = null, $id, $name = null)
    {
        $tpl = $this->createTemplate('helpers/list/list_action_preview.tpl');
        if (!array_key_exists('Bad SQL query', self::$cache_lang)) {
            self::$cache_lang['Preview'] = $this->l('Preview', 'Helper');
        }

        $tpl->assign(array(
            'href' => $this->getPreviewUrl(new Product((int)$id)),
            'action' => self::$cache_lang['Preview'],
        ));

        return $tpl->fetch();
    }
}
