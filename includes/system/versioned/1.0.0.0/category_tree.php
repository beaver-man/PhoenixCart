<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

  class category_tree {
    protected $_data = [];

    public
        $root_category_id = 0,
        $max_level = 0,
        $root_start_string = '',
        $root_end_string = '',
        $parent_start_string = '',
        $parent_end_string = '',
        $parent_group_start_string = '<ul>',
        $parent_group_end_string = '</ul>',
        $parent_group_apply_to_root = false,
        $child_start_string = '<li>',
        $child_end_string = '</li>',
        $breadcrumb_separator = '_',
        $breadcrumb_usage = true,
        $spacer_string = '',
        $spacer_multiplier = 1,
        $follow_cpath = false,
        $cpath_array = [],
        $cpath_start_string = '',
        $cpath_end_string = '';

    public function __construct() {
      static $_category_tree_data;

      if ( isset($_category_tree_data) ) {
        $this->_data = $_category_tree_data;
      } else {

        $categories_query = $GLOBALS['db']->query("select c.categories_id, c.parent_id, c.categories_image, cd.categories_name, cd.categories_description, cd.categories_seo_description, cd.categories_seo_title from categories c, categories_description cd where c.categories_id = cd.categories_id and cd.language_id = '" . (int)$_SESSION['languages_id']. "' order by c.parent_id, c.sort_order, cd.categories_name");

        while ( $categories = $categories_query->fetch_assoc() ) {
          $this->_data[$categories['parent_id']][$categories['categories_id']] = [
            'name'            => $categories['categories_name'],
            'image'           => $categories['categories_image'],
            'description'     => $categories['categories_description'],
            'seo_description' => $categories['categories_seo_description'],
            'seo_title'       => $categories['categories_seo_title'],
          ];
        }

        $_category_tree_data = $this->_data;
      }
    }

    protected function _buildBranch($parent_id, $level = 0) {
      $result = ((($level === 0) && ($this->parent_group_apply_to_root === true)) || ($level > 0)) ? $this->parent_group_start_string : null;

      if ( isset($this->_data[$parent_id]) ) {
        $link = $GLOBALS['Linker']->build('index.php');
        foreach ( $this->_data[$parent_id] as $category_id => $category ) {
          if ( $this->breadcrumb_usage === true ) {
            $category_link = $this->buildBreadcrumb($category_id);
          } else {
            $category_link = $category_id;
          }

          $result .= $this->child_start_string;

          if ( isset($this->_data[$category_id]) ) {
            $result .= $this->parent_start_string;
          }

          if ( $level === 0 ) {
            $result .= $this->root_start_string;
          }

          if ( ($this->follow_cpath === true) && in_array($category_id, $this->cpath_array) ) {
            $link_title = $this->cpath_start_string . $category['name'] . $this->cpath_end_string;
          } else {
            $link_title = $category['name'];
          }

          $result .= '<a class="list-group-item list-group-item-action" href="' . $link->set_parameter('cPath', $category_link) . '">';
          $result .= str_repeat($this->spacer_string, $this->spacer_multiplier * $level);
          $result .= $link_title . '</a>';

          if ( $level === 0 ) {
            $result .= $this->root_end_string;
          }

          if ( isset($this->_data[$category_id]) ) {
            $result .= $this->parent_end_string;
          }



          if ( isset($this->_data[$category_id]) && (($this->max_level == '0') || ($this->max_level > $level+1)) ) {
            if ( $this->follow_cpath === true ) {
              if ( in_array($category_id, $this->cpath_array) ) {
                $result .= $this->_buildBranch($category_id, $level+1);
              }
            } else {
              $result .= $this->_buildBranch($category_id, $level+1);
            }
          }

          $result .= $this->child_end_string;
        }
      }

      $result .= ((($level === 0) && ($this->parent_group_apply_to_root === true)) || ($level > 0)) ? $this->parent_group_end_string : null;

      return $result;
    }

    function buildBranchArray($parent_id, $level = 0, $result = '') {
      if (empty($result)) {
        $result = [];
      }

      if (isset($this->_data[$parent_id])) {
        foreach ($this->_data[$parent_id] as $category_id => $category) {
          if ($this->breadcrumb_usage == true) {
            $category_link = $this->buildBreadcrumb($category_id);
          } else {
            $category_link = $category_id;
          }

          $result[] = [
            'id' => $category_link,
            'image' => $category['image'],
            'title' => str_repeat($this->spacer_string, $this->spacer_multiplier * $level) . $category['name'],
          ];

          if (isset($this->_data[$category_id]) && (($this->max_level == '0') || ($this->max_level > $level+1))) {
            if ($this->follow_cpath === true) {
              if (in_array($category_id, $this->cpath_array)) {
                $result = $this->buildBranchArray($category_id, $level+1, $result);
              }
            } else {
              $result = $this->buildBranchArray($category_id, $level+1, $result);
            }
          }
        }
      }

      return $result;
    }

    function buildBreadcrumb($category_id, $level = 0) {
      $breadcrumb = '';

      foreach ($this->_data as $parent => $categories) {
        foreach ($categories as $id => $info) {
          if ($id == $category_id) {
            if ($level < 1) {
              $breadcrumb = $id;
            } else {
              $breadcrumb = $id . $this->breadcrumb_separator . $breadcrumb;
            }

            if ($parent != $this->root_category_id) {
              $breadcrumb = $this->buildBreadcrumb($parent, $level+1) . $breadcrumb;
            }
          }
        }
      }

      return $breadcrumb;
    }

/**
 * Return a formated string representation of the category structure relationship data
 *
 * @access public
 * @return string
 */

    public function getTree() {
      return $this->_buildBranch($this->root_category_id);
    }

/**
 * Magic function; return a formated string representation of the category structure relationship data
 *
 * This is used when echoing the class object, eg:
 *
 * echo $osC_CategoryTree;
 *
 * @access public
 * @return string
 */

    public function __toString() {
      return $this->getTree();
    }

    function getArray($parent_id = '') {
      return $this->buildBranchArray((empty($parent_id) ? $this->root_category_id : $parent_id));
    }

    function exists($id) {
      foreach ($this->_data as $parent => $categories) {
        foreach ($categories as $category_id => $info) {
          if ($id == $category_id) {
            return true;
          }
        }
      }

      return false;
    }

    function getChildren($category_id, &$array = []) {
      foreach ($this->_data as $parent => $categories) {
        if ($parent == $category_id) {
          foreach ($categories as $id => $info) {
            $array[] = $id;
            $this->getChildren($id, $array);
          }
        }
      }

      return $array;
    }

/**
 * Return category information
 *
 * @param int $id The category ID to return information of
 * @param string $key The key information to return (since v3.0.2)
 * @return mixed
 * @since v3.0.0
 */

    public function getData($id, $key = null) {
      foreach ( $this->_data as $parent => $categories ) {
        foreach ( $categories as $category_id => $info ) {
          if ( $id == $category_id ) {
            $data = [
              'id'                => $id,
              'name'              => $info['name'],
              'parent_id'         => $parent,
              'image'             => $info['image'],
            ];

            $data['description']     = $info['description'];
            $data['seo_description'] = $info['seo_description'];
            $data['seo_title']       = $info['seo_title'];

            return ( isset($key) ? $data[$key] : $data );
          }
        }
      }

      return false;
    }

/**
 * Return the parent ID of a category
 *
 * @param int $id The category ID to return the parent ID of
 * @return int
 * @since v3.0.2
 */

    public function getParentID($id) {
      return $this->getData($id, 'parent_id');
    }

    function setRootCategoryID($root_category_id) {
      $this->root_category_id = $root_category_id;
    }

    function setMaximumLevel($max_level) {
      $this->max_level = $max_level;
    }

    function setRootString($root_start_string, $root_end_string) {
      $this->root_start_string = $root_start_string;
      $this->root_end_string = $root_end_string;
    }

    function setParentString($parent_start_string, $parent_end_string) {
      $this->parent_start_string = $parent_start_string;
      $this->parent_end_string = $parent_end_string;
    }

    function setParentGroupString($parent_group_start_string, $parent_group_end_string, $apply_to_root = false) {
      $this->parent_group_start_string = $parent_group_start_string;
      $this->parent_group_end_string = $parent_group_end_string;
      $this->parent_group_apply_to_root = $apply_to_root;
    }

    function setChildString($child_start_string, $child_end_string) {
      $this->child_start_string = $child_start_string;
      $this->child_end_string = $child_end_string;
    }

    function setBreadcrumbSeparator($breadcrumb_separator) {
      $this->breadcrumb_separator = $breadcrumb_separator;
    }

    function setBreadcrumbUsage($breadcrumb_usage) {
      if ($breadcrumb_usage === true) {
        $this->breadcrumb_usage = true;
      } else {
        $this->breadcrumb_usage = false;
      }
    }

    function setSpacerString($spacer_string, $spacer_multiplier = 2) {
      $this->spacer_string = $spacer_string;
      $this->spacer_multiplier = $spacer_multiplier;
    }

    function setCategoryPath($cpath, $cpath_start_string = '', $cpath_end_string = '') {
      $this->follow_cpath = true;
      $this->cpath_array = explode($this->breadcrumb_separator, $cpath);
      $this->cpath_start_string = $cpath_start_string;
      $this->cpath_end_string = $cpath_end_string;
    }

    function setFollowCategoryPath($follow_cpath) {
      if ($follow_cpath === true) {
        $this->follow_cpath = true;
      } else {
        $this->follow_cpath = false;
      }
    }

    function setCategoryPathString($cpath_start_string, $cpath_end_string) {
      $this->cpath_start_string = $cpath_start_string;
      $this->cpath_end_string = $cpath_end_string;
    }
  }

