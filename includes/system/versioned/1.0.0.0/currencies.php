<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

////
// Class to handle currencies
// TABLES: currencies
  class currencies {

    public $currencies;

// class constructor
    function __construct() {
      $this->currencies = [];
      $currencies_query = $GLOBALS['db']->query("SELECT code, title, symbol_left, symbol_right, decimal_point, thousands_point, decimal_places, value FROM currencies");
      while ($currencies = $currencies_query->fetch_assoc()) {
        $this->currencies[$currencies['code']] = [
          'title' => $currencies['title'],
          'symbol_left' => $currencies['symbol_left'],
          'symbol_right' => $currencies['symbol_right'],
          'decimal_point' => $currencies['decimal_point'],
          'thousands_point' => $currencies['thousands_point'],
          'decimal_places' => (int)$currencies['decimal_places'],
          'value' => $currencies['value'],
        ];
      }
    }

// class methods
    function format($number, $calculate_currency_value = true, $currency_type = '', $currency_value = '') {
      if (empty($currency_type)) $currency_type = $_SESSION['currency'];

      if ($calculate_currency_value == true) {
        $rate = (!Text::is_empty($currency_value)) ? $currency_value : $this->currencies[$currency_type]['value'];
        $format_string = $this->currencies[$currency_type]['symbol_left'] . number_format(static::round($number * $rate, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . $this->currencies[$currency_type]['symbol_right'];
      } else {
        $format_string = $this->currencies[$currency_type]['symbol_left'] . number_format(static::round($number, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . $this->currencies[$currency_type]['symbol_right'];
      }

      return $format_string;
    }

    function calculate_price($products_price, $products_tax, $quantity = 1) {
      return static::round(Tax::price($products_price, $products_tax), $this->currencies[$_SESSION['currency']]['decimal_places']) * $quantity;
    }

    function is_set($code) {
      return isset($this->currencies[$code]) && !Text::is_empty($this->currencies[$code]);
    }

    function get_value($code) {
      return $this->currencies[$code]['value'];
    }

    function get_decimal_places($code) {
      return $this->currencies[$code]['decimal_places'];
    }

    function display_price($products_price, $products_tax, $quantity = 1) {
      return $this->format($this->calculate_price($products_price, $products_tax, $quantity));
    }

    function format_raw($number, $calculate_currency_value = true, $currency_type = '', $currency_value = '') {
      if (empty($currency_type)) $currency_type = $_SESSION['currency'];

      if ($calculate_currency_value == true) {
        $rate = (!Text::is_empty($currency_value)) ? $currency_value : $this->currencies[$currency_type]['value'];
        $format_string = number_format(static::round($number * $rate, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], '.', '');
      } else {
        $format_string = number_format(static::round($number, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], '.', '');
      }

      return $format_string;
    }

    function display_raw($products_price, $products_tax, $quantity = 1) {
      return $this->format_raw($this->calculate_price($products_price, $products_tax, $quantity));
    }

    public static function round($number, $precision) {
      $location = strpos($number, '.');
// if there's a decimal point, increment the location to point after it
      if ((false === $location) || (strlen(substr($number, ++$location)) <= $precision)) {
// the number is already rounded sufficiently
        return $number;
      }

      $location += $precision;
      $next_digit = substr($number, $location, 1);
      $number = substr($number, 0, $location);

      if ($next_digit < 5) {
// we already truncated (which rounds down)
        return $number;
      }

// otherwise we need to round up
      return ($precision < 1)
           ? $number + 1
           : $number + ('0.' . str_repeat(0, $precision - 1) . '1');
    }

  }
