<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

  class oscTemplate {

    private $_title = TITLE;
    private $_blocks = [];
    private $_content = [];
    public $_data = [];
    private $_template;

    public function __construct($template = 'default') {
      if (is_object($template)) {
        $this->_template = $template;
      } else {
        require DIR_FS_CATALOG . "templates/$template/includes/template.php";
        $template .= '_template';
        $this->_template = new $template();
      }
    }

    public function get_template() {
      return $this->_template;
    }

    public function setTitle($title) {
      $this->_title = $title;
    }

    public function getTitle() {
      return $this->_title;
    }

    public function addBlock($block, $group) {
      $this->_blocks[$group][] = $block;
    }

    public function hasBlocks($group) {
      return !empty($this->_blocks[$group]);
    }

    public function getBlocks($group) {
      if ($this->hasBlocks($group)) {
        return implode("\n", $this->_blocks[$group]);
      }
    }

    public function buildBlocks() {
      if ( !defined('TEMPLATE_BLOCK_GROUPS') || Text::is_empty(TEMPLATE_BLOCK_GROUPS) ) {
        return;
      }

      foreach (explode(';', TEMPLATE_BLOCK_GROUPS) as $group) {
        $module_key = 'MODULE_' . strtoupper($group) . '_INSTALLED';

        if ( !defined($module_key) || Text::is_empty(constant($module_key)) ) {
          continue;
        }

        foreach ( explode(';', constant($module_key)) as $module ) {
          $class = pathinfo($module, PATHINFO_FILENAME);

          if ( class_exists($class) ) {
            $mb = new $class();

            if ( $mb->isEnabled() ) {
              $mb->execute();
            }
          }
        }
      }
    }

    public function addContent($content, $group) {
      $this->_content[$group][] = $content;
    }

    public function hasContent($group) {
      return !empty($this->_content[$group]);
    }

    public function getContent($group) {
      $template_page_class = 'tp_' . $group;
      if ( class_exists($template_page_class) ) {
        $template_page = new $template_page_class();
        $template_page->prepare();
      }

      foreach ( $this->getContentModules($group) as $module ) {
        if ( class_exists($module) ) {
          $mb = new $module();

          if ( $mb->isEnabled() ) {
            $mb->execute();
          }
        }
      }

      if ( isset($template_page) ) {
        $template_page->build();
      }

      if ($this->hasContent($group)) {
        return implode("\n", $this->_content[$group]);
      }
    }

    public function getContentModules($group) {
      $result = [];

      foreach ( explode(';', MODULE_CONTENT_INSTALLED) as $m ) {
        $module = explode('/', $m, 2);

        if ( $module[0] == $group ) {
          $result[] = $module[1];
        }
      }

      return $result;
    }

    public function map_to_template($file, $type = 'module') {
      return $this->_template->get_template_mapping_for($file, $type)
          ?? default_template::_get_template_mapping_for($file, $type);
    }

  }
