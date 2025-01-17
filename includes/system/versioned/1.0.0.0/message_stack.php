<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License

  Example usage:

  $messageStack = new messageStack();
  $messageStack->add('general', 'Error: Error 1', 'error');
  $messageStack->add('general', 'Error: Error 2', 'warning');
  if ($messageStack->size('general') > 0) echo $messageStack->output('general');
*/
  class messageStack extends alertBlock {

// class constructor
    function __construct() {
      $this->messages = [];

      if (isset($_SESSION['messageToStack'])) {
        for ($i=0, $n=count($_SESSION['messageToStack']); $i<$n; $i++) {
          $this->add($_SESSION['messageToStack'][$i]['class'], $_SESSION['messageToStack'][$i]['text'], $_SESSION['messageToStack'][$i]['type']);
        }
        unset($_SESSION['messageToStack']);
      }
    }

// class methods
    function add($class, $message, $type = 'error') {
      if ($type == 'error') {
        $this->messages[] = ['params' => 'class="alert alert-danger alert-dismissible fade show" role="alert"', 'class' => $class, 'text' => $message];
      } elseif ($type == 'warning') {
        $this->messages[] = ['params' => 'class="alert alert-warning alert-dismissible fade show" role="alert"', 'class' => $class, 'text' => $message];
      } elseif ($type == 'success') {
        $this->messages[] = ['params' => 'class="alert alert-success alert-dismissible fade show" role="alert"', 'class' => $class, 'text' => $message];
      } else {
        $this->messages[] = ['params' => 'class="alert alert-info alert-dismissible fade show" role="alert"', 'class' => $class, 'text' => $message];
      }
    }

    function add_session($class, $message, $type = 'error') {
      if (!isset($_SESSION['messageToStack'])) {
        $_SESSION['messageToStack'] = [];
      }

      $_SESSION['messageToStack'][] = ['class' => $class, 'text' => $message, 'type' => $type];
    }

    function reset() {
      $this->messages = [];
    }

    function output($class) {
      $output = [];
      for ($i=0, $n=count($this->messages); $i<$n; $i++) {
        if ($this->messages[$i]['class'] == $class) {
          $output[] = $this->messages[$i];
        }
      }

      return $this->alertBlock($output);
    }

    function size($class) {
      $count = 0;

      for ($i=0, $n=count($this->messages); $i<$n; $i++) {
        if ($this->messages[$i]['class'] == $class) {
          $count++;
        }
      }

      return $count;
    }
  }
?>
