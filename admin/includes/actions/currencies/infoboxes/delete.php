<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2022 Phoenix Cart

  Released under the GNU General Public License
*/

  if (!is_object($GLOBALS['table_definition']['info'] ?? null)) {
    error_log('Nothing selected for deletion');
    return;
  }

  $cInfo =& $GLOBALS['table_definition']['info'];
  $heading = TEXT_INFO_HEADING_DELETE_CURRENCY;

  $link = $GLOBALS['Admin']->link('currencies.php', ['cID' => $cInfo->currencies_id]);
  if (isset($_GET['page'])) {
    $link->set_parameter('page', (int)$_GET['page']);
  }
  $contents[] = ['text' => TEXT_INFO_DELETE_INTRO];
  $contents[] = ['class' => 'text-center text-uppercase font-weight-bold', 'text' => $cInfo->title];
  $contents[] = [
    'class' => 'text-center',
    'text' => ($remove_currency
             ? $GLOBALS['Admin']->button(IMAGE_DELETE, 'fas fa-trash', 'btn-danger mr-2', (clone $link)->set_parameter('action', 'delete_confirm'))
             : '')
            . $GLOBALS['Admin']->button(IMAGE_CANCEL, 'fas fa-times', 'btn-light', $link)];
