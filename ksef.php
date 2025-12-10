<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/KsefApi.php';
require_once dirname(__FILE__) . '/classes/KsefXml.php';

class Ksef extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'ksef';
        $this->tab = 'billing_invoicing';
        $this->version = '1.0.0';
        $this->author = 'GitHub Copilot';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('KSeF Integration');
        $this->description = $this->l('Integrates PrestaShop with the Polish National System of e-Invoices (KSeF).');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        Configuration::updateValue('KSEF_LIVE_MODE', false);
        Configuration::updateValue('KSEF_AUTH_METHOD', 'token');
        Configuration::updateValue('KSEF_AUTO_SEND', false);
        Configuration::updateValue('KSEF_SEND_B2C', false);

        return parent::install() &&
            $this->installDb() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('actionObjectOrderInvoiceAddAfter');
    }

    public function uninstall()
    {
        Configuration::deleteByName('KSEF_LIVE_MODE');
        Configuration::deleteByName('KSEF_NIP');
        Configuration::deleteByName('KSEF_TOKEN');
        Configuration::deleteByName('KSEF_AUTH_METHOD');
        Configuration::deleteByName('KSEF_AUTO_SEND');
        Configuration::deleteByName('KSEF_SEND_B2C');
        
        // Clean up mappings
        $taxes = Tax::getTaxes($this->context->language->id);
        foreach ($taxes as $tax) {
            Configuration::deleteByName('KSEF_VAT_MAP_' . $tax['id_tax']);
        }

        return parent::uninstall() && $this->uninstallDb();
    }

    public function installDb()
    {
        $sql = [];
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ksef_invoice` (
            `id_ksef_invoice` int(11) NOT NULL AUTO_INCREMENT,
            `id_order` int(11) NOT NULL,
            `id_order_invoice` int(11) NOT NULL,
            `ksef_number` varchar(64) DEFAULT NULL,
            `reference_number` varchar(64) DEFAULT NULL,
            `status` varchar(32) DEFAULT NULL,
            `upo_url` text DEFAULT NULL,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_ksef_invoice`),
            KEY `id_order` (`id_order`),
            KEY `id_order_invoice` (`id_order_invoice`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ksef_log` (
            `id_ksef_log` int(11) NOT NULL AUTO_INCREMENT,
            `id_order` int(11) DEFAULT NULL,
            `message` text NOT NULL,
            `type` varchar(32) NOT NULL,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_ksef_log`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (Db::getInstance()->execute($query) == false) {
                return false;
            }
        }

        return true;
    }

    public function uninstallDb()
    {
        $sql = [];
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'ksef_invoice`';
        $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'ksef_log`';

        foreach ($sql as $query) {
            if (Db::getInstance()->execute($query) == false) {
                return false;
            }
        }

        return true;
    }

    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitKsefModule')) == true) {
            $this->postProcess();
        }

        if (Tools::isSubmit('testKsefConnection')) {
            $this->testConnection();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    protected function testConnection()
    {
        $nip = Configuration::get('KSEF_NIP');
        $token = Configuration::get('KSEF_TOKEN');
        $isLive = Configuration::get('KSEF_LIVE_MODE');

        if (!$nip || !$token) {
            $this->context->smarty->assign([
                'test_result' => $this->l('Please save NIP and Token first.'),
                'test_success' => false
            ]);
            return;
        }

        $api = new KsefApi($isLive, $nip, $token);
        $result = $api->testConnection();

        $this->context->smarty->assign([
            'test_result' => $result['message'],
            'test_success' => $result['success']
        ]);
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitKsefModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm($this->getConfigForms());
    }

    protected function getConfigForms()
    {
        $forms = [];

        // 1. General Settings
        $forms[] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('General Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'KSEF_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode (Production)'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled (Demo/Test)')
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Authorization Method'),
                        'name' => 'KSEF_AUTH_METHOD',
                        'options' => array(
                            'query' => array(
                                array('id' => 'token', 'name' => $this->l('Token')),
                                array('id' => 'cert', 'name' => $this->l('Certificate (Not implemented yet)')),
                                array('id' => 'xml', 'name' => $this->l('Signed XML (Not implemented yet)')),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter your NIP (Tax ID)'),
                        'name' => 'KSEF_NIP',
                        'label' => $this->l('NIP'),
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'name' => 'KSEF_TOKEN',
                        'label' => $this->l('KSeF Authorization Token'),
                        'desc' => $this->l('Enter the authorization token generated in KSeF App'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        // 2. Automation
        $orderStatuses = OrderState::getOrderStates($this->context->language->id);
        $forms[] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Automation'),
                    'icon' => 'icon-bolt',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Automatic Invoice Submission'),
                        'name' => 'KSEF_AUTO_SEND',
                        'is_bool' => true,
                        'desc' => $this->l('Automatically send invoices to KSeF when order reaches specific status'),
                        'values' => array(
                            array('id' => 'active_on', 'value' => true, 'label' => $this->l('Enabled')),
                            array('id' => 'active_off', 'value' => false, 'label' => $this->l('Disabled'))
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Trigger Statuses'),
                        'name' => 'KSEF_AUTO_SEND_STATUSES[]',
                        'multiple' => true,
                        'class' => 'fixed-width-xxl',
                        'options' => array(
                            'query' => $orderStatuses,
                            'id' => 'id_order_state',
                            'name' => 'name',
                        ),
                        'desc' => $this->l('Select order statuses that trigger invoice submission (e.g. Payment Accepted)'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Send B2C Invoices'),
                        'name' => 'KSEF_SEND_B2C',
                        'is_bool' => true,
                        'desc' => $this->l('Send invoices for individual customers (non-business)'),
                        'values' => array(
                            array('id' => 'active_on', 'value' => true, 'label' => $this->l('Enabled')),
                            array('id' => 'active_off', 'value' => false, 'label' => $this->l('Disabled'))
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        // 3. VAT Mapping
        $taxes = Tax::getTaxes($this->context->language->id);
        $vatInputs = [];
        foreach ($taxes as $tax) {
            $vatInputs[] = array(
                'type' => 'text',
                'label' => $tax['name'] . ' (' . $tax['rate'] . '%)',
                'name' => 'KSEF_VAT_MAP_' . $tax['id_tax'],
                'col' => 2,
                'desc' => $this->l('Enter KSeF VAT code (e.g. 23, 8, zw, oo)'),
            );
        }

        $forms[] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('VAT Rate Mapping'),
                    'icon' => 'icon-money',
                ),
                'description' => $this->l('Map PrestaShop VAT rates to KSeF codes.'),
                'input' => $vatInputs,
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        return $forms;
    }

    protected function getConfigFormValues()
    {
        $values = array(
            'KSEF_LIVE_MODE' => Configuration::get('KSEF_LIVE_MODE', false),
            'KSEF_AUTH_METHOD' => Configuration::get('KSEF_AUTH_METHOD', 'token'),
            'KSEF_NIP' => Configuration::get('KSEF_NIP', ''),
            'KSEF_TOKEN' => Configuration::get('KSEF_TOKEN', ''),
            'KSEF_AUTO_SEND' => Configuration::get('KSEF_AUTO_SEND', false),
            'KSEF_SEND_B2C' => Configuration::get('KSEF_SEND_B2C', false),
            'KSEF_AUTO_SEND_STATUSES[]' => explode(',', Configuration::get('KSEF_AUTO_SEND_STATUSES', '')),
        );

        $taxes = Tax::getTaxes($this->context->language->id);
        foreach ($taxes as $tax) {
            $values['KSEF_VAT_MAP_' . $tax['id_tax']] = Configuration::get('KSEF_VAT_MAP_' . $tax['id_tax'], '');
        }

        return $values;
    }

    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        // Handle array values specifically
        if (Tools::isSubmit('submitKsefModule')) {
            $statuses = Tools::getValue('KSEF_AUTO_SEND_STATUSES');
            if (is_array($statuses)) {
                Configuration::updateValue('KSEF_AUTO_SEND_STATUSES', implode(',', $statuses));
                unset($form_values['KSEF_AUTO_SEND_STATUSES[]']); // Remove from generic loop
            } else {
                Configuration::updateValue('KSEF_AUTO_SEND_STATUSES', '');
            }
        }

        foreach (array_keys($form_values) as $key) {
            if ($key === 'KSEF_AUTO_SEND_STATUSES[]') continue; // Skip already handled
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    public function hookActionObjectOrderInvoiceAddAfter($params)
    {
        if (!Configuration::get('KSEF_AUTO_SEND')) {
            return;
        }

        $object = $params['object']; // OrderInvoice object
        if (!$object instanceof OrderInvoice) {
            return;
        }

        $order = new Order($object->id_order);
        
        // Check if order status is in the trigger list
        $triggerStatuses = explode(',', Configuration::get('KSEF_AUTO_SEND_STATUSES', ''));
        if (!in_array($order->current_state, $triggerStatuses)) {
            return;
        }

        // Check B2C
        $address = new Address($order->id_address_invoice);
        if (empty($address->vat_number) && !Configuration::get('KSEF_SEND_B2C')) {
            return;
        }

        $this->sendInvoiceToKsef($object, $order);
    }

    protected function sendInvoiceToKsef($invoice, $order)
    {
        try {
            // 1. Generate XML
            $xmlGenerator = new KsefXml($order, $invoice);
            $xml = $xmlGenerator->generate();

            // 2. Connect to API
            $nip = Configuration::get('KSEF_NIP');
            $token = Configuration::get('KSEF_TOKEN');
            $isLive = Configuration::get('KSEF_LIVE_MODE');
            
            $api = new KsefApi($isLive, $nip, $token);
            $sessionToken = $api->getSessionToken();

            // 3. Send Invoice
            $response = $api->sendInvoice($xml, $sessionToken);
            
            // 4. Save result
            $ksefNumber = isset($response['elementReferenceNumber']) ? $response['elementReferenceNumber'] : 'UNKNOWN';
            $referenceNumber = isset($response['referenceNumber']) ? $response['referenceNumber'] : 'UNKNOWN';
            
            Db::getInstance()->insert('ksef_invoice', [
                'id_order' => (int)$order->id,
                'id_order_invoice' => (int)$invoice->id,
                'ksef_number' => pSQL($ksefNumber),
                'reference_number' => pSQL($referenceNumber),
                'status' => 'sent',
                'date_add' => date('Y-m-d H:i:s'),
                'date_upd' => date('Y-m-d H:i:s'),
            ]);
            
            $this->log($order->id, 'Invoice sent successfully. KSeF Number: ' . $ksefNumber, 'success');

        } catch (Exception $e) {
            $this->log($order->id, 'Error sending invoice: ' . $e->getMessage(), 'error');
        }
    }

    protected function log($id_order, $message, $type)
    {
        Db::getInstance()->insert('ksef_log', [
            'id_order' => (int)$id_order,
            'message' => pSQL($message),
            'type' => pSQL($type),
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }
}
