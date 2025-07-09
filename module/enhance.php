<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

class WB_MagicPost_Enhance extends WB_MagicPost_Base
{
  private static $option_name = 'magicpost_enhance_option';

  public function __construct()
  {

    if (is_admin()) {
      add_action('wp_ajax_magicpost', array($this, 'magicpost_ajax'));
    }

    $switch = self::opt('enhance_switch', 1);
    if (!$switch || !get_option('wb_magicpost_ver', 0)) {
      return;
    }
  }

  /**
   * 管理设置
   */
  public function magicpost_ajax()
  {

    $op = trim(self::param('op'));
    if (!$op) {
      return;
    }
    $arrow = [
      'enhance_setting',
      'enhance_update'
    ];

    if (!in_array($op, $arrow)) {
      return;
    }

    if (!current_user_can('manage_options')) {
      self::ajax_resp(['code' => 1, 'desc' => 'deny']);
      return;
    }

    if (!wp_verify_nonce(sanitize_text_field(self::param('_ajax_nonce')), 'wp_ajax_wb_magicpost')) {
      self::ajax_resp(['code' => 1, 'desc' => 'illegal']);
      return;
    }
    switch ($op) {
      case 'enhance_setting':
        $ret = ['code' => 1];

        do {
          $ret['cnf'] = WB_MagicPost::get_fields('enhance');
          $ret['opt'] = self::opt();

          $ret['code'] = 0;
          $ret['desc'] = 'success';
        } while (0);

        self::ajax_resp($ret);

        break;

      case 'enhance_update':
        $ret = ['code' => 1];
        do {
          $opt = $this->sanitize_text_field_array(self::param('opt', []));
          if (empty($opt) || !is_array($opt)) {
            $ret['desc'] = 'illegal';
            break;
          }
          update_option(self::$option_name, $opt);

          $ret['code'] = 0;
          $ret['desc'] = 'success';
        } while (0);

        self::ajax_resp($ret);
        break;
    }
  }

  public static function set_active($switch)
  {
    $opt = self::opt(null);
    $opt['enhance_switch'] = $switch;
    update_option(self::$option_name, $opt);
  }

  public static function get_active()
  {
    return self::opt('enhance_switch');
  }

  public function sanitize_text_field_array($v)
  {
    if (is_array($v)) foreach ($v as $sk => $sv) {
      if (is_array($sv)) {
        $v[$sk] = $this->sanitize_text_field_array($sv);
      } else if (is_string($sv)) {
        $v[$sk] = sanitize_text_field($sv);
      }
    }
    else if (is_string($v)) {
      $v = sanitize_text_field($v);
    }
    return $v;
  }

  //配置值
  public static function opt($name = '', $default = false)
  {
    static $options = null;

    if (null == $options) {
      $options = get_option(self::$option_name, array());
      self::extend_conf($options, self::def_opt());
    }

    $return = null;
    do {
      if (!$name) {
        $return = $options;
        break;
      }
      $ret = $options;
      $ak = explode('.', $name);

      foreach ($ak as $sk) {
        if (isset($ret[$sk])) {
          $ret = $ret[$sk];
        } else {
          $ret = $default;
          break;
        }
      }
      $return = $ret;
    } while (0);

    return apply_filters('wb_magicpost_conf', $return, $name, $default, 'enhance');
  }

  public static function def_opt()
  {

    $cnf = array(
      'enhance_switch' => '0',
      'publish_time' => '0',
      'clone_post' => '0',
      'auto_slug' => '0',
    );

    $cnf_enhance = WB_MagicPost::get_fields('enhance');
    $def_values = $cnf_enhance['default'] ?? [];
    $cnf = array_merge($cnf, $def_values);

    return $cnf;
  }

  public static function extend_conf(&$cnf, $conf)
  {
    if (is_array($conf)) foreach ($conf as  $k => $v) {
      if (!isset($cnf[$k])) {
        $cnf[$k] = $v;
      } else if (is_array($v)) {
        self::extend_conf($cnf[$k], $v);
      }
    }
  }
}
