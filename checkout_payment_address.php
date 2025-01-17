<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

  require 'includes/application_top.php';

  require 'includes/system/segments/checkout/pipeline.php';

  if (!$customer_data->has('address')) {
    Href::redirect($Linker->build('checkout_payment.php'));
  }

  // needs to be included earlier to set the success message in the messageStack
  require language::map_to_translation('checkout_payment_address.php');

  $message_stack_area = 'checkout_address';

  $error = false;
  $process = false;
  if (Form::validate_action_is('submit')) {
    $customer_details = $customer_data->process($customer_data->get_fields_for_page('address_book'));
    $hooks->cat('injectFormVerify');
    if (Form::is_valid()) {
      $customer_details['id'] = $customer->get_id();
      $customer_data->add_address($customer_details);

      $_SESSION['billto'] = $customer_data->get('address_book_id', $customer_details);

      unset($_SESSION['payment']);

      Href::redirect($Linker->build('checkout_payment.php'));
    } elseif (isset($_POST['address'])) {
      // process the selected billing destination
      $reset_payment = isset($_SESSION['billto']) && ($_SESSION['billto'] != $_POST['address']) && isset($_SESSION['payment']);
      $_SESSION['billto'] = $_POST['address'];

      if ($customer->fetch_to_address($_SESSION['billto'])) {
        if ($reset_payment) {
          unset($_SESSION['payment']);
        }
        Href::redirect($Linker->build('checkout_payment.php'));
      } else {
        unset($_SESSION['billto']);
      }
    }
  }

// if no billing destination address was selected, use their own address as default
  if (!isset($_SESSION['billto'])) {
    $_SESSION['billto'] = $customer->get('default_billto');
    $billto =& $_SESSION['billto'];
  }

  $addresses_count = $customer->count_addresses();

  require $Template->map(__FILE__, 'page');

  require 'includes/application_bottom.php';
