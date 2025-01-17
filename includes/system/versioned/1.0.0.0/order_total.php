<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

  class order_total {
    var $modules;

// class constructor
    function __construct() {
      if (defined('MODULE_ORDER_TOTAL_INSTALLED') && !Text::is_empty(MODULE_ORDER_TOTAL_INSTALLED)) {
        $this->modules = explode(';', MODULE_ORDER_TOTAL_INSTALLED);

        foreach ($this->modules as $value) {
          include('includes/languages/' . $_SESSION['language'] . '/modules/order_total/' . $value);
          include('includes/modules/order_total/' . $value);

          $class = substr($value, 0, strrpos($value, '.'));
          $GLOBALS[$class] = new $class;
        }
      }
    }

    function process() {
      $order_total_array = [];
      if (is_array($this->modules)) {
        foreach($this->modules as $value) {
          $class = substr($value, 0, strrpos($value, '.'));
          if ($GLOBALS[$class]->enabled) {
            $GLOBALS[$class]->output = [];
            $GLOBALS[$class]->process();

            for ($i=0, $n=count($GLOBALS[$class]->output); $i<$n; $i++) {
              if (!Text::is_empty($GLOBALS[$class]->output[$i]['title']) && !Text::is_empty($GLOBALS[$class]->output[$i]['text'])) {
                $order_total_array[] = [
                  'code' => $GLOBALS[$class]->code,
                  'title' => $GLOBALS[$class]->output[$i]['title'],
                  'text' => $GLOBALS[$class]->output[$i]['text'],
                  'value' => $GLOBALS[$class]->output[$i]['value'],
                  'sort_order' => $GLOBALS[$class]->sort_order,
                ];
              }
            }
          }
        }
      }

      return $order_total_array;
    }

    function output() {
      $output_string = '';
      if (is_array($this->modules)) {
        foreach($this->modules as $value) {
          $class = substr($value, 0, strrpos($value, '.'));
          if ($GLOBALS[$class]->enabled) {
            $size = count($GLOBALS[$class]->output);
            for ($i=0; $i<$size; $i++) {
              $output_string .= '<tr>';
                $output_string .= '<td>' . $GLOBALS[$class]->output[$i]['title'] . '</td>';
                $output_string .= '<td class="text-right">' . $GLOBALS[$class]->output[$i]['text'] . '</td>';
              $output_string .= '</tr>';
            }
          }
        }
      }

      return $output_string;
    }

  }
