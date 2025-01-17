<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

  class shipping {

    public $modules;

// class constructor
    function __construct($module = '') {
      if (defined('MODULE_SHIPPING_INSTALLED') && !Text::is_empty(MODULE_SHIPPING_INSTALLED)) {
        $this->modules = explode(';', MODULE_SHIPPING_INSTALLED);

        $include_modules = [];

        $extension = '.' . pathinfo(Request::get_page(), PATHINFO_EXTENSION);
        if ( (!Text::is_empty($module)) && (in_array(substr($module['id'], 0, strpos($module['id'], '_')) . $extension, $this->modules)) ) {
          $include_modules[] = [
            'class' => substr($module['id'], 0, strpos($module['id'], '_')),
            'file' => substr($module['id'], 0, strpos($module['id'], '_')) . $extension,
          ];
        } else {
          foreach($this->modules as $value) {
            $class = pathinfo($value, PATHINFO_FILENAME);
            $include_modules[] = ['class' => $class, 'file' => $value];
          }
        }

        foreach ($include_modules as $m) {
          $GLOBALS[$m['class']] = new $m['class']();
        }
      }
    }

    function quote($method = '', $module = '') {
      global $total_weight, $shipping_weight, $shipping_quoted, $shipping_num_boxes;

      $quotes_array = [];

      if (is_array($this->modules)) {
        $shipping_quoted = '';
        $shipping_num_boxes = 1;
        $shipping_weight = $total_weight;

        if (SHIPPING_BOX_WEIGHT >= $shipping_weight*SHIPPING_BOX_PADDING/100) {
          $shipping_weight = $shipping_weight+SHIPPING_BOX_WEIGHT;
        } else {
          $shipping_weight = $shipping_weight + ($shipping_weight*SHIPPING_BOX_PADDING/100);
        }

        if ($shipping_weight > SHIPPING_MAX_WEIGHT) { // Split into many boxes
          $shipping_num_boxes = ceil($shipping_weight/SHIPPING_MAX_WEIGHT);
          $shipping_weight = $shipping_weight/$shipping_num_boxes;
        }

        $include_quotes = [];

        foreach ($this->modules as $value) {
          $class = pathinfo($value, PATHINFO_FILENAME);
          if (!Text::is_empty($module)) {
            if ( ($module == $class) && ($GLOBALS[$class]->enabled) ) {
              $include_quotes[] = $class;
            }
          } elseif ($GLOBALS[$class]->enabled) {
            $include_quotes[] = $class;
          }
        }

        foreach ($include_quotes as $q) {
          $quotes = $GLOBALS[$q]->quote($method);
          if (is_array($quotes)) {
            $quotes_array[] = $quotes;
          }
        }
      }

      return $quotes_array;
    }

    function cheapest() {
      if (is_array($this->modules)) {
        $rates = [];

        foreach ($this->modules as $value) {
          $class = pathinfo($value, PATHINFO_FILENAME);
          if ($GLOBALS[$class]->enabled) {
            $quotes = $GLOBALS[$class]->quotes;
            foreach ($quotes['methods'] as $method) {
              if (isset($method['cost']) && !Text::is_empty($method['cost'])) {
                $rates[] = [
                  'id' => $quotes['id'] . '_' . $method['id'],
                  'title' => $quotes['module'] . ' (' . $method['title'] . ')',
                  'cost' => $method['cost'],
                ];
              }
            }
          }
        }

        $cheapest = $rates[0] ?? false;
        foreach ($rates as $rate) {
          if ($rate['cost'] < $cheapest['cost']) {
            $cheapest = $rate;
          }
        }

        return $cheapest;
      }
    }

    public function count() {
      return count(array_filter($this->modules, function ($m) {
        return $GLOBALS[pathinfo($m, PATHINFO_FILENAME)]->enabled ?? false;
      }));
    }

  }
