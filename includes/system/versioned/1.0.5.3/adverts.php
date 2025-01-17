<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

  class adverts {

    public static function get_grouped_adverts($advert_group) {
      $group = Text::input($advert_group);

      $advert_query = $GLOBALS['db']->query("SELECT * FROM advert WHERE advert_group = '" . $GLOBALS['db']->escape($group) . "' and status = 1 order by sort_order");

      $num = 1; $adverts = [];
      while ($advert = $advert_query->fetch_assoc()) {
        $adverts[$num] =  $advert;

        $num++;
      }

      return $adverts;
    }

    public static function advert_pull_down_groups($advert_id, $key = '') {
      $name = (($key) ? 'configuration[' . $key . ']' : 'configuration_value');

      $groups_array = [['id' => '0', 'text' => TEXT_DEFAULT]];
      $groups_query = $GLOBALS['db']->query("select DISTINCT advert_group from advert order by advert_group");
      while ($groups = $groups_query->fetch_assoc()) {
        $groups_array[] = ['id' => $groups['advert_group'], 'text' => $groups['advert_group']];
      }

      return (new Select($name, $groups_array))->set_selection($advert_id);
    }

    public static function advert_get_group($advert_id) {
      return $advert_id;
    }

  }
