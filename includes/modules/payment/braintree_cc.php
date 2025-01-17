<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

  class braintree_cc extends abstract_payment_module {

    const REQUIRES = [
      'firstname',
      'lastname',
      'street_address',
      'city',
      'postcode',
      'country',
      'telephone',
      'email_address',
    ];

    const CONFIG_KEY_BASE = 'MODULE_PAYMENT_BRAINTREE_CC_';

    private $signature = 'braintree|braintree_cc|1.1|2.3';
    private $api_version = '1';
    private $token;
    private $result;

    public function __construct() {
      parent::__construct();

      if ( defined('MODULE_PAYMENT_BRAINTREE_CC_STATUS') ) {
        if ( MODULE_PAYMENT_BRAINTREE_CC_TRANSACTION_SERVER == 'Sandbox' ) {
          $this->title .= ' [Sandbox]';
          $this->public_title .= ' (' . $this->code . '; Sandbox)';
        }
      }

      $braintree_error = $this->check_for_errors();
      if ( isset($braintree_error) ) {
        $this->description = '<div class="alert alert-warning">' . $braintree_error . '</div>' . $this->description;

        $this->enabled = false;
      } else {
        if ( !class_exists('Braintree') ) {
          require DIR_FS_CATALOG . 'includes/apps/braintree_cc/Braintree.php';
        }

        spl_autoload_register('braintree_cc::autoload');

        $this->api_version .= ' [' . Braintree_Version::get() . ']';
      }
    }

    public static function autoload($class) {
      if ( Text::is_prefixed_by($class, 'Braintree_') ) {
        $file = dirname(__DIR__, 2) . '/apps/braintree_cc/' . str_replace('_', '/', $class) . '.php';

        if ( file_exists($file) ) {
          include $file;
        }
      }
    }

    public function check_for_errors() {
      $exts = array_filter(['xmlwriter', 'SimpleXML', 'openssl', 'dom', 'hash', 'curl'], function ($extension) {
        return !extension_loaded($extension);
      });

      if ($exts) {
        return sprintf(MODULE_PAYMENT_BRAINTREE_CC_ERROR_ADMIN_PHP_EXTENSIONS, implode('<br>', $exts));
      }

      if ( defined('MODULE_PAYMENT_BRAINTREE_CC_STATUS') ) {
        if ( Text::is_empty(MODULE_PAYMENT_BRAINTREE_CC_MERCHANT_ID)
          || Text::is_empty(MODULE_PAYMENT_BRAINTREE_CC_PUBLIC_KEY)
          || Text::is_empty(MODULE_PAYMENT_BRAINTREE_CC_PRIVATE_KEY)
          || Text::is_empty(MODULE_PAYMENT_BRAINTREE_CC_CLIENT_KEY) )
        {
          return MODULE_PAYMENT_BRAINTREE_CC_ERROR_ADMIN_CONFIGURATION;
        }
      }

      if ( defined('MODULE_PAYMENT_BRAINTREE_CC_STATUS') ) {
        if ( !Text::is_empty(MODULE_PAYMENT_BRAINTREE_CC_MERCHANT_ACCOUNTS) ) {
          foreach ( explode(';', MODULE_PAYMENT_BRAINTREE_CC_MERCHANT_ACCOUNTS) as $a ) {
            $ac = explode(':', $a, 2);

            if ( isset($ac[1]) && ($ac[1] == DEFAULT_CURRENCY) ) {
              return;
            }
          }
        }

        return sprintf(MODULE_PAYMENT_BRAINTREE_CC_ERROR_ADMIN_MERCHANT_ACCOUNTS, DEFAULT_CURRENCY);
      }
    }

    public function pre_confirmation_check() {
      if ( isset($GLOBALS['Template']) && ($GLOBALS['Template'] instanceof Template) ) {
        $GLOBALS['Template']->add_block('<style>.date-fields .form-control {width:auto;display:inline-block}</style>', 'header_tags');
        $GLOBALS['Template']->add_block($this->getSubmitCardDetailsJavascript(), 'footer_scripts');
      }
    }

    public function confirmation() {
      global $order, $currencies;

      $months = [];

      for ($i = 1; $i <= 12; $i++) {
        $months[] = [
          'id' => Text::output(sprintf('%02d', $i)),
          'text' => htmlspecialchars(sprintf('%02d', $i)),
        ];
      }

      $today = getdate();
      $years = [];

      for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
        $years[] = [
          'id' => Text::output(strftime('%Y',mktime(0, 0, 0, 1, 1, $i))),
          'text' => htmlspecialchars(strftime('%Y',mktime(0, 0, 0, 1, 1, $i))),
        ];
      }

      $content = '';

      if ( !$this->isValidCurrency($_SESSION['currency']) ) {
        $content .= sprintf(MODULE_PAYMENT_BRAINTREE_CC_CURRENCY_CHARGE, $currencies->format($order->info['total'], true, DEFAULT_CURRENCY), DEFAULT_CURRENCY, $_SESSION['currency']);
      }

      if ( MODULE_PAYMENT_BRAINTREE_CC_TOKENS == 'True' ) {
        $tokens_query = $GLOBALS['db']->query("SELECT id, card_type, number_filtered, expiry_date FROM customers_braintree_tokens WHERE customers_id = '" . (int)$_SESSION['customer_id'] . "' ORDER BY date_added");

        if ( mysqli_num_rows($tokens_query) > 0 ) {
          $content .= '<table class="table" id="braintree_table">';

          while ( $tokens = $tokens_query->fetch_assoc() ) {
            $content .= '<tr class="moduleRow" id="braintree_card_' . (int)$tokens['id'] . '">'
                      . '  <td><input type="radio" name="braintree_card" value="' . (int)$tokens['id'] . '" /></td>'
                      . '  <td>' . MODULE_PAYMENT_BRAINTREE_CC_CREDITCARD_LAST_4 . '&nbsp;' . htmlspecialchars($tokens['number_filtered']) . '&nbsp;&nbsp;' . htmlspecialchars(substr($tokens['expiry_date'], 0, 2) . '/' . substr($tokens['expiry_date'], 2)) . '&nbsp;&nbsp;' . htmlspecialchars($tokens['card_type']) . '</td>'
                      . '</tr>';

            if ( MODULE_PAYMENT_BRAINTREE_CC_VERIFY_WITH_CVV == 'True' ) {
              $content .= '<tr class="moduleRowExtra" id="braintree_card_cvv_' . (int)$tokens['id'] . '">'
                        . '  <td>&nbsp;</td>'
                        . '  <td>' . MODULE_PAYMENT_BRAINTREE_CC_CREDITCARD_CVV . '&nbsp;<input type="text" size="5" maxlength="4" autocomplete="off" data-encrypted-name="token_cvv[' . (int)$tokens['id'] . ']" /></td>'
                        . '</tr>';
            }
          }

          $content .= '<tr class="moduleRow" id="braintree_card_0">'
                    . '  <td><input type="radio" name="braintree_card" value="0" /></td>'
                    . '  <td>' . MODULE_PAYMENT_BRAINTREE_CC_CREDITCARD_NEW . '</td>'
                    . '</tr>'
                    . '</table>';
        }
      }

      $content .= '<table class="table" id="braintree_table_new_card">'
                . '<tr>'
                . '  <td class="w-25">' . MODULE_PAYMENT_BRAINTREE_CC_CREDITCARD_OWNER . '</td>'
                . '  <td>' . new Input('name', ['value' => $GLOBALS['customer_data']->get('name', $order->billing)]) . '</td>'
                . '</tr>'
                . '<tr>'
                . '  <td class="w-25">' . MODULE_PAYMENT_BRAINTREE_CC_CREDITCARD_NUMBER . '</td>'
                . '  <td><input type="text" maxlength="20" autocomplete="off" data-encrypted-name="number" /></td>'
                . '</tr>'
                . '<tr>'
                . '  <td class="w-25">' . MODULE_PAYMENT_BRAINTREE_CC_CREDITCARD_EXPIRY . '</td>'
                . '  <td class="date-fields">' . new Select('month', $months) . ' / ' . new Select('year', $years) . '</td>'
                . '</tr>';

      if ( MODULE_PAYMENT_BRAINTREE_CC_VERIFY_WITH_CVV == 'True' ) {
        $content .= '<tr>'
                  . '  <td class="w-25">' . MODULE_PAYMENT_BRAINTREE_CC_CREDITCARD_CVV . '</td>'
                  . '  <td><input type="text" size="5" maxlength="4" autocomplete="off" data-encrypted-name="cvv" /></td>'
                  . '</tr>';
      }

      if ( MODULE_PAYMENT_BRAINTREE_CC_TOKENS == 'True' ) {
        $content .= '<tr>'
                  . '  <td class="w-25">&nbsp;</td>'
                  . '  <td>' . new Tickable('cc_save', ['value' => 'true'], 'checkbox') . ' ' . MODULE_PAYMENT_BRAINTREE_CC_CREDITCARD_SAVE . '</td>'
                  . '</tr>';
      }

      $content .= '</table>';

      if ( !(($GLOBALS['Template'] ?? null) instanceof Template) ) {
        $content .= $this->getSubmitCardDetailsJavascript();
      }

      $confirmation = ['title' => $content];

      return $confirmation;
    }

    public function before_process() {
      global $order, $customer_data;

      $this->token = null;
      $braintree_token_cvv = null;

      if ( MODULE_PAYMENT_BRAINTREE_CC_TOKENS == 'True' ) {
        if ( isset($_POST['braintree_card']) && is_numeric($_POST['braintree_card']) && ($_POST['braintree_card'] > 0) ) {
          $token_query = $GLOBALS['db']->query("SELECT braintree_token FROM customers_braintree_tokens WHERE id = '" . (int)$_POST['braintree_card'] . "' AND customers_id = '" . (int)$_SESSION['customer_id'] . "'");

          if ( mysqli_num_rows($token_query) === 1 ) {
            $token = $token_query->fetch_assoc();

            $this->token = $token['braintree_token'];

            if ( MODULE_PAYMENT_BRAINTREE_CC_VERIFY_WITH_CVV == 'True' ) {

              if ( isset($_POST['token_cvv'][$_POST['braintree_card']]) ) {
                $braintree_token_cvv = $_POST['token_cvv'][$_POST['braintree_card']];
              }

              if ( empty($braintree_token_cvv) ) {
                Href::redirect($GLOBALS['Linker']->build('checkout_payment.php', ['payment_error' => $this->code, 'error' => 'cardcvv']));
              }
            }
          }
        }
      }

      if ( !isset($this->token) ) {
        $cc_owner = $_POST['name'] ?? null;
        $cc_number = $_POST['number'] ?? null;
        $cc_expires_month = $_POST['month'] ?? null;
        $cc_expires_year = $_POST['year'] ?? null;

        if ( MODULE_PAYMENT_BRAINTREE_CC_VERIFY_WITH_CVV == 'True' ) {
          $cc_cvv = $_POST['cvv'] ?? null;
        }

        $months = [];

        for ($i = 1; $i <= 12; $i++) {
          $months[] = sprintf('%02d', $i);
        }

        $today = getdate();
        $years = [];

        for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
          $years[] = strftime('%Y',mktime(0, 0, 0, 1, 1, $i));
        }

        if ( empty($cc_owner) ) {
          Href::redirect($GLOBALS['Linker']->build('checkout_payment.php', ['payment_error' => $this->code, 'error' => 'cardowner']));
        }

        if ( empty($cc_number) ) {
          Href::redirect($GLOBALS['Linker']->build('checkout_payment.php', ['payment_error' => $this->code, 'error' => 'cardnumber']));
        }

        if ( !isset($cc_expires_month) || !in_array($cc_expires_month, $months) ) {
          Href::redirect($GLOBALS['Linker']->build('checkout_payment.php', ['payment_error' => $this->code, 'error' => 'cardexpires']));
        }

        if ( !isset($cc_expires_year) || !in_array($cc_expires_year, $years) ) {
          Href::redirect($GLOBALS['Linker']->build('checkout_payment.php', ['payment_error' => $this->code, 'error' => 'cardexpires']));
        }

        if ( ($cc_expires_year == date('Y')) && ($cc_expires_month < date('m')) ) {
          Href::redirect($GLOBALS['Linker']->build('checkout_payment.php', ['payment_error' => $this->code, 'error' => 'cardexpires']));
        }

        if ( MODULE_PAYMENT_BRAINTREE_CC_VERIFY_WITH_CVV == 'True' ) {
          if ( empty($cc_cvv) ) {
            Href::redirect($GLOBALS['Linker']->build('checkout_payment.php', ['payment_error' => $this->code, 'error' => 'cardcvv']));
          }
        }
      }

      $this->result = null;

      Braintree_Configuration::environment(MODULE_PAYMENT_BRAINTREE_CC_TRANSACTION_SERVER == 'Live' ? 'production' : 'sandbox');
      Braintree_Configuration::merchantId(MODULE_PAYMENT_BRAINTREE_CC_MERCHANT_ID);
      Braintree_Configuration::publicKey(MODULE_PAYMENT_BRAINTREE_CC_PUBLIC_KEY);
      Braintree_Configuration::privateKey(MODULE_PAYMENT_BRAINTREE_CC_PRIVATE_KEY);

      $_SESSION['currency'] = $this->getTransactionCurrency();

      $customer_data->get('country', $order->billing);
      $data = [
        'amount' => $GLOBALS['currencies']->format_raw($order->info['total'], true, $_SESSION['currency']),
        'merchantAccountId' => $this->getMerchantAccountId($_SESSION['currency']),
        'creditCard' => ['cardholderName' => $cc_owner],
        'customer' => [
          'firstName' => $customer_data->get('firstname', $order->customer),
          'lastName' => $customer_data->get('lastname', $order->customer),
          'company' => $customer_data->get('company', $order->customer),
          'phone' => $customer_data->get('telephone', $order->customer),
          'email' => $customer_data->get('email_address', $order->customer),
        ],
        'billing' => [
          'firstName' => $customer_data->get('firstname', $order->billing),
          'lastName' => $customer_data->get('lastname', $order->billing),
          'company' => $customer_data->get('company', $order->billing),
          'streetAddress' => $customer_data->get('street_address', $order->billing),
          'extendedAddress' => $customer_data->get('suburb', $order->billing),
          'locality' => $customer_data->get('city', $order->billing),
          'region' => Zone::fetch_name($customer_data->get('zone_id', $order->billing), $customer_data->get('country_id', $order->billing), $customer_data->get('state', $order->billing)),
          'postalCode' => $customer_data->get('postcode', $order->billing),
          'countryCodeAlpha2' => $customer_data->get('country_iso_code_2', $order->billing),
        ],
        'options' => [],
      ];

      if ( MODULE_PAYMENT_BRAINTREE_CC_TRANSACTION_METHOD == 'Payment' ) {
        $data['options']['submitForSettlement'] = true;
      }

      if ( $order->content_type != 'virtual' ) {
        $customer_data->get('country', $order->delivery);
        $data['shipping'] = [
          'firstName' => $customer_data->get('firstname', $order->delivery),
          'lastName' => $customer_data->get('lastname', $order->delivery),
          'company' => $customer_data->get('company', $order->delivery),
          'streetAddress' => $customer_data->get('street_address', $order->delivery),
          'extendedAddress' => $customer_data->get('suburb', $order->delivery),
          'locality' => $customer_data->get('city', $order->delivery),
          'region' => Zone::fetch_name(
            $customer_data->get('zone_id', $order->delivery),
            $customer_data->get('country_id', $order->delivery),
            $customer_data->get('state', $order->delivery)),
          'postalCode' => $customer_data->get('postcode', $order->delivery),
          'countryCodeAlpha2' => $customer_data->get('country_iso_code_2', $order->delivery),
        ];
      }

      if ( isset($this->token) ) {
        $data['paymentMethodToken'] = $this->token;

        if ( MODULE_PAYMENT_BRAINTREE_CC_VERIFY_WITH_CVV == 'True' ) {
          $data['creditCard']['cvv'] = $braintree_token_cvv;
        }
      } else {
        $data['creditCard']['number'] = $cc_number;
        $data['creditCard']['expirationMonth'] = $cc_expires_month;
        $data['creditCard']['expirationYear'] = $cc_expires_year;

        if ( MODULE_PAYMENT_BRAINTREE_CC_VERIFY_WITH_CVV == 'True' ) {
          $data['creditCard']['cvv'] = $cc_cvv;
        }

        if ( (MODULE_PAYMENT_BRAINTREE_CC_TOKENS == 'True') && isset($_POST['cc_save']) && ($_POST['cc_save'] == 'true') ) {
          $data['options']['storeInVaultOnSuccess'] = true;
        }
      }

      $error = false;

      try {
        $this->result = Braintree_Transaction::sale($data);
      } catch ( Exception $e ) {
        $error = true;
      }

      if ( ($error === false) && ($this->result->success) ) {
        return true;
      }

      if ( $this->result->transaction) {
        if ( !empty($this->result->message) ) {
          $_SESSION['braintree_error'] = $this->result->message;
        }
      } else {
        $braintree_error = '';

        if ( isset($this->result->errors) ) {
          foreach ( $this->result->errors->deepAll() as $error ) {
            $braintree_error .= $error->message . ' ';
          }

          if ( !empty($braintree_error) ) {
            $braintree_error = substr($braintree_error, 0, -1);
          }
        }

        if ( !empty($braintree_error) ) {
          $_SESSION['braintree_error'] = $braintree_error;
        }
      }

      Href::redirect($GLOBALS['Linker']->build('checkout_payment.php', ['payment_error' => $this->code]));
    }

    public function after_process() {
      global $order_id;

      $status_comment = ['Transaction ID: ' . $this->result->transaction->id];

      if ( (MODULE_PAYMENT_BRAINTREE_CC_TOKENS == 'True') && isset($_POST['cc_save']) && ($_POST['cc_save'] == 'true') && !isset($this->token) && isset($this->result->transaction->creditCard['token']) ) {
        $token = Text::input($this->result->transaction->creditCard['token']);
        $type = Text::input($this->result->transaction->creditCard['cardType']);
        $number = Text::input($this->result->transaction->creditCard['last4']);
        $expiry = Text::input($this->result->transaction->creditCard['expirationMonth'] . $this->result->transaction->creditCard['expirationYear']);

        $check_query = $GLOBALS['db']->query("SELECT id FROM customers_braintree_tokens WHERE customers_id = '" . (int)$_SESSION['customer_id'] . "' AND braintree_token = '" . $GLOBALS['db']->escape($token) . "' LIMIT 1");
        if ( mysqli_num_rows($check_query) < 1 ) {
          $sql_data = [
            'customers_id' => (int)$_SESSION['customer_id'],
            'braintree_token' => $token,
            'card_type' => $type,
            'number_filtered' => $number,
            'expiry_date' => $expiry,
            'date_added' => 'NOW()',
          ];

          $GLOBALS['db']->perform('customers_braintree_tokens', $sql_data);
        }

        $status_comment[] = 'Token Created: Yes';
      } elseif ( isset($this->token) ) {
        $status_comment[] = 'Token Used: Yes';
      }

      $sql_data = [
        'orders_id' => $order_id,
        'orders_status_id' => MODULE_PAYMENT_BRAINTREE_CC_TRANSACTION_ORDER_STATUS_ID,
        'date_added' => 'NOW()',
        'customer_notified' => '0',
        'comments' => implode("\n", $status_comment),
      ];

      $GLOBALS['db']->perform('orders_status_history', $sql_data);
    }

    public function get_error() {
      $message = MODULE_PAYMENT_BRAINTREE_CC_ERROR_GENERAL;

      if ( empty($_GET['error']) ) {
        if ( isset($_SESSION['braintree_error']) ) {
          $message = $_SESSION['braintree_error'] . ' ' . $message;

          unset($_SESSION['braintree_error']);
        }
      } else {
        switch ($_GET['error']) {
          case 'cardowner':
            $message = MODULE_PAYMENT_BRAINTREE_CC_ERROR_CARDOWNER;
            break;

          case 'cardnumber':
            $message = MODULE_PAYMENT_BRAINTREE_CC_ERROR_CARDNUMBER;
            break;

          case 'cardexpires':
            $message = MODULE_PAYMENT_BRAINTREE_CC_ERROR_CARDEXPIRES;
            break;

          case 'cardcvv':
            $message = MODULE_PAYMENT_BRAINTREE_CC_ERROR_CARDCVV;
            break;
        }
      }

      return [
        'title' => MODULE_PAYMENT_BRAINTREE_CC_ERROR_TITLE,
        'error' => $message,
      ];
    }

    protected function get_parameters() {
      if ( mysqli_num_rows($GLOBALS['db']->query("SHOW TABLES LIKE 'customers_braintree_tokens'")) != 1 ) {
        $sql = <<<EOSQL
CREATE TABLE customers_braintree_tokens (
  id int NOT NULL auto_increment,
  customers_id int NOT NULL,
  braintree_token varchar(255) NOT NULL,
  card_type varchar(32) NOT NULL,
  number_filtered varchar(20) NOT NULL,
  expiry_date char(6) NOT NULL,
  date_added datetime NOT NULL,
  PRIMARY KEY (id),
  KEY idx_cbraintreet_customers_id (customers_id),
  KEY idx_cbraintreet_token (braintree_token)
);
EOSQL;

        $GLOBALS['db']->query($sql);
      }

      return [
        'MODULE_PAYMENT_BRAINTREE_CC_STATUS' => [
          'title' => 'Enable Braintree Module',
          'desc' => 'Do you want to accept Braintree payments?',
          'value' => 'True',
          'set_func' => "Config::select_one(['True', 'False'], ",
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_MERCHANT_ID' => [
          'title' => 'Merchant ID',
          'desc' => 'The Braintree account Merchant ID to use.',
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_PUBLIC_KEY' => [
          'title' => 'Public Key',
          'desc' => 'The Braintree account public key to use.',
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_PRIVATE_KEY' => [
          'title' => 'Private Key',
          'desc' => 'The Braintree account private key to use.',
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_CLIENT_KEY' => [
          'title' => 'Client Side Encryption Key',
          'desc' => 'The client side encryption key to use.',
          'set_func' => 'braintree_cc::set_client_key(',
          'use_func' => 'braintree_cc::show_client_key',
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_MERCHANT_ACCOUNTS' => [
          'title' => 'Merchant Accounts',
          'desc' => 'Merchant accounts and defined currencies.',
          'set_func' => 'braintree_cc::set_merchant_accounts(',
          'use_func' => 'braintree_cc::show_merchant_accounts',
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_TOKENS' => [
          'title' => 'Create Tokens',
          'desc' => 'Create and store tokens for card payments customers can use on their next purchase?',
          'value' => 'False',
          'set_func' => "Config::select_one(['True', 'False'], ",
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_VERIFY_WITH_CVV' => [
          'title' => 'Verify With CVV',
          'desc' => 'Verify the credit card with the billing address with the Card Verification Value (CVV)?',
          'value' => 'True',
          'set_func' => "Config::select_one(['True', 'False'], ",
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_TRANSACTION_METHOD' => [
          'title' => 'Transaction Method',
          'desc' => 'The processing method to use for each transaction.',
          'value' => 'Authorize',
          'set_func' => "Config::select_one(['Authorize', 'Payment'], ",
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_ORDER_STATUS_ID' => [
          'title' => 'Set Order Status',
          'desc' => 'Set the status of orders made with this payment module to this value',
          'value' => '0',
          'use_func' => 'order_status::fetch_name',
          'set_func' => 'Config::select_order_status(',
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_TRANSACTION_ORDER_STATUS_ID' => [
          'title' => 'Transaction Order Status',
          'desc' => 'Include transaction information in this order status level',
          'value' => self::ensure_order_status('MODULE_PAYMENT_BRAINTREE_CC_TRANSACTION_ORDER_STATUS_ID', 'Braintree [Transactions]'),
          'set_func' => 'Config::select_order_status(',
          'use_func' => 'order_status::fetch_name',
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_TRANSACTION_SERVER' => [
          'title' => 'Transaction Server',
          'desc' => 'Perform transactions on the production server or on the testing server.',
          'value' => 'Live',
          'set_func' => "Config::select_one(['Live', 'Sandbox'], ",
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_ZONE' => [
          'title' => 'Payment Zone',
          'desc' => 'If a zone is selected, only enable this payment method for that zone.',
          'value' => '0',
          'use_func' => 'geo_zone::fetch_name',
          'set_func' => 'Config::select_geo_zone(',
        ],
        'MODULE_PAYMENT_BRAINTREE_CC_SORT_ORDER' => [
          'title' => 'Sort order of display.',
          'desc' => 'Sort order of display. Lowest is displayed first.',
          'value' => '0',
        ],
      ];
    }

    function getTransactionCurrency() {
      return $this->isValidCurrency($_SESSION['currency']) ? $_SESSION['currency'] : DEFAULT_CURRENCY;
    }

    function getMerchantAccountId($currency) {
      foreach ( explode(';', MODULE_PAYMENT_BRAINTREE_CC_MERCHANT_ACCOUNTS) as $ma ) {
        list($a, $c) = explode(':', $ma);

        if ( $c == $currency ) {
          return $a;
        }
      }

      return '';
    }

    function isValidCurrency($currency) {
      global $currencies;

      foreach ( explode(';', MODULE_PAYMENT_BRAINTREE_CC_MERCHANT_ACCOUNTS) as $combo ) {
        list($id, $c) = explode(':', $combo);

        if ( $c == $currency ) {
          return $currencies->is_set($c);
        }
      }

      return false;
    }

    function deleteCard($token, $token_id) {
      Braintree_Configuration::environment(MODULE_PAYMENT_BRAINTREE_CC_TRANSACTION_SERVER == 'Live' ? 'production' : 'sandbox');
      Braintree_Configuration::merchantId(MODULE_PAYMENT_BRAINTREE_CC_MERCHANT_ID);
      Braintree_Configuration::publicKey(MODULE_PAYMENT_BRAINTREE_CC_PUBLIC_KEY);
      Braintree_Configuration::privateKey(MODULE_PAYMENT_BRAINTREE_CC_PRIVATE_KEY);

      try {
        Braintree_CreditCard::delete($token);
      } catch ( Exception $e ) {
      }

      $GLOBALS['db']->query("DELETE FROM customers_braintree_tokens WHERE id = '" . (int)$token_id . "' AND customers_id = '" . (int)$_SESSION['customer_id'] . "' AND braintree_token = '" . $GLOBALS['db']->escape(Text::input($token)) . "'");

      return (mysqli_affected_rows($GLOBALS['db']) === 1);
    }

    function getSubmitCardDetailsJavascript() {
      $braintree_client_key = MODULE_PAYMENT_BRAINTREE_CC_CLIENT_KEY;

      $js = <<<EOD
<script src="https://js.braintreegateway.com/v1/braintree.js"></script>
<script>
$(function() {
  $('form[name="checkout_confirmation"]').attr('id', 'braintree-payment-form');

  var braintree = Braintree.create('{$braintree_client_key}');
  braintree.onSubmitEncryptForm('braintree-payment-form');

  if ( $('#braintree_table').length > 0 ) {
    if ( typeof($('#braintree_table').parent().closest('table').attr('width')) == 'undefined' ) {
      $('#braintree_table').parent().closest('table').attr('width', '100%');
    }

    $('#braintree_table .moduleRowExtra').hide();

    $('#braintree_table_new_card').hide();

    $('form[name="checkout_confirmation"] input[name="braintree_card"]').change(function() {
      var selected = $(this).val();

      if ( selected == '0' ) {
        braintreeShowNewCardFields();
      } else {
        $('#braintree_table_new_card').hide();

        $('[id^="braintree_card_cvv_"]').hide();

        $('#braintree_card_cvv_' + selected).show();
      }

      $('tr[id^="braintree_card_"]').removeClass('moduleRowSelected');
      $('#braintree_card_' + selected).addClass('moduleRowSelected');
    });

    $('form[name="checkout_confirmation"] input[name="braintree_card"]:first').prop('checked', true).trigger('change');

    $('#braintree_table .moduleRow').hover(function() {
      $(this).addClass('moduleRowOver');
    }, function() {
      $(this).removeClass('moduleRowOver');
    }).click(function(event) {
      var target = $(event.target);

      if ( !target.is('input:radio') ) {
        $(this).find('input:radio').each(function() {
          if ( $(this).prop('checked') == false ) {
            $(this).prop('checked', true).trigger('change');
          }
        });
      }
    });
  } else {
    if ( typeof($('#braintree_table_new_card').parent().closest('table').attr('width')) == 'undefined' ) {
      $('#braintree_table_new_card').parent().closest('table').attr('width', '100%');
    }
  }
});

function braintreeShowNewCardFields() {
  $('[id^="braintree_card_cvv_"]').hide();

  $('#braintree_table_new_card').show();
}
</script>
EOD;

      return $js;
    }

    public static function set_client_key($value, $name) {
      return (new Textarea('configuration[' . $name . ']', ['cols' => '50', 'rows' => '12']))->set_text($value);
    }

    public static function show_client_key($key) {
    $string = '';

    if ( strlen($key) > 0 ) {
      $string = substr($key, 0, 20) . ' ...';
    }

    return $string;
  }

    public static function get_data($value) {
    if (empty($value)) {
      return [];
    }

    $data = [];
    foreach ( explode(';', $value) as $ma ) {
      list($a, $currency) = explode(':', $ma);

      $data[$currency] = $a;
    }

    return $data;
  }

    public static function get_currencies() {
      $currencies = array_keys(Guarantor::ensure_global('currencies')->currencies);
      sort($currencies);

      return $currencies;
    }

    public static function set_merchant_accounts($value, $key) {
      $data = static::get_data($value);

      $result = '';
      foreach ( static::get_currencies() as $c ) {
        $close = null;
        if ( $c == DEFAULT_CURRENCY ) {
          $result .= '<strong>';
          $close = '</strong>';
        }

        $result .= $c . ':';

        if ( isset($close) ) {
          $result .= $close;
        }

        $result .= '&nbsp;' . new Input('braintree_ma[' . $c . ']', ['value' => ($data[$c] ?? '')]) . '<br>';
      }

      if ( !empty($result) ) {
        $result = substr($result, 0, -strlen('<br>'));
      }

      $result .= new Input('configuration[' . $key . ']', ['value' => $value], 'hidden');

      $result .= <<<"EOD"
<script>
$(function() {
  $('form[name="modules"]').submit(function() {
    var ma_string = '';

    $('form[name="modules"] input[name^="braintree_ma["]').each(function() {
      if ( $(this).val().length > 0 ) {
        ma_string += $(this).val() + ':' + $(this).attr('name').slice(13, -1) + ';';
      }
    });

    if ( ma_string.length > 0 ) {
      ma_string = ma_string.slice(0, -1);
    }

    $('form[name="modules"] input[name="configuration[{$key}]"]').val(ma_string);
  })
});
</script>
EOD;

      return $result;
    }

    public static function show_merchant_accounts($value) {
      $data = static::get_data($value);

      $result = '';
      foreach ( static::get_currencies() as $c ) {
        if ( $c == DEFAULT_CURRENCY ) {
          $result .= '<strong>';
          $close = '</strong>';
        } else {
          $close = null;
        }

        $result .= $c . ':';

        if ( isset($close) ) {
          $result .= $close;
        }

        $result .= '&nbsp;' . ($data[$c] ?? '') . '<br>';
      }

      if ( !empty($result) ) {
        $result = substr($result, 0, -strlen('<br>'));
      }

      return $result;
    }

  }
