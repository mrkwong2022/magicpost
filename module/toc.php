<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

class WB_MagicPost_Toc extends WB_MagicPost_Base
{
  private static $option_name = 'magicpost_toc_option';

  public static $is_set_script_var = false;

  public function __construct()
  {

    if (is_admin()) {
      add_action('wp_ajax_magicpost', array($this, 'magicpost_ajax'));
    }

    $switch = self::opt('toc_switch', 1);
    if (!$switch || !get_option('wb_magicpost_ver', 0)) {
      return;
    }

    if (!is_admin()) {
      add_filter('the_content', array($this, 'the_content_handler'), 100);
      add_action('wp_enqueue_scripts', array($this, 'front_assets_handler'), 20);
    } else {
      add_action('admin_head-post.php', array(__CLASS__, 'admin_post_handle'));
      add_action('admin_head-post-new.php', array(__CLASS__, 'admin_post_handle'));
    }

    add_shortcode('magicpost_toc_items', array($this, 'toc_shortcode_handler'));
    add_action('widgets_init', array($this, 'widgets_init'));
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
      'toc_setting',
      'toc_update'
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
      case 'toc_setting':
        $ret = ['code' => 1];

        do {
          $ret['cnf'] = WB_MagicPost::get_fields('toc');
          $ret['opt'] = self::opt();

          $ret['code'] = 0;
          $ret['desc'] = 'success';
        } while (0);

        self::ajax_resp($ret);

        break;

      case 'toc_update':
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
    $opt['toc_switch'] = $switch;
    update_option(self::$option_name, $opt);
  }

  public static function get_active()
  {
    return self::opt('toc_switch');
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

    return apply_filters('wb_magicpost_conf', $return, $name, $default, 'toc');
  }

  public static function def_opt()
  {

    $cnf = array(
      'toc_switch' => '0',
      'auto_insert_content' => '0',
      'ct_mode' => '2',
      'toc_label' => __('内容目录', WB_MAGICPOST_TD),
      'style_content_unfold' => '0',
      'style_content_max' => 5,
      'style_widget_unfold' => '1',
      'style_widget_max' => 5,
      'style' => [
        'content' => [],
        'widget' => []
      ],
      'custom_style' => ''
    );

    $cnf_toc = WB_MagicPost::get_fields('toc');
    $def_values = $cnf_toc['default'] ?? [];
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

  /**
   * 注册小工具
   */
  public function widgets_init()
  {
    wp_register_sidebar_widget('magicpost-toc-widget', _x('#Magicpost 内容目录#', 'widget', WB_MAGICPOST_TD), array($this, 'widget_handler'), array('description' => _x('侧栏展示内容目录', 'widget', WB_MAGICPOST_TD)));
  }

  public function widget_handler()
  {
    if (!is_single()) return;

    $args = [
      'location' => 'widget',
      'class' => 'magicpost-toc-widget magicpost-toc-interaction widget'
    ];
    echo self::toc_html($args);
  }

  function toc_shortcode_handler()
  {
    return self::toc_html(['location' => 'content', 'class' => 'toc-on-content']);
  }

  // 内容处理
  public function the_content_handler($content)
  {
    // 启用tabbar则不需要插入
    $toc_marker = '<span id="magicpostMarker"></span>';
    if (!WB_MagicPost_Share::is_tabbar_active() && is_single() && self::opt('auto_insert_content') == 1) {
      $content = self::toc_html() . $content;
    }
    return $content . $toc_marker;
  }

  /**
   * 输出目录 结构
   *
   * @param array $args
   * location 所在位置，content 正文 | widget 小工具
   */
  public static function get_toc_items($post, $args = ['location' => 'sidebar'])
  {
    $cur_post = get_post($post);
    $location = $args['location'] ?? 'sidebar';

    /**
     * 文章目录处理
     */
    $tc_mode = self::opt('ct_mode');
    $sc_items = array();

    if ($tc_mode) {
      preg_match_all('#<h([' . $tc_mode . '])[^>]*>([\w\W]*?)<\/h\1>#is', $cur_post->post_content, $matches);

      $cnf_tags = str_split($tc_mode);

      $idx = 0;
      $idxs = 0;
      foreach ($matches[1] as $index => $tag) {
        $item = strip_tags($matches[2][$index]);
        $item = trim($item);
        if (!$item) continue;

        /**
         * 置于文章内容顶部，以链接形式
         */
        if ($location == 'content') {
          preg_match('#id=["\']?([^"\'>]+)["\']#is', $matches[0][$index], $matches_id);

          if ($cnf_tags[0] == $tag) {
            $attr_id = $matches_id[1] ?? 'toc-' . $idx;

            $sc_items[$index] = '<a class="mgp-toc-title" href="#' . $attr_id . '">' . $item . '</a>';
            $idx++;
          } elseif (isset($cnf_tags[1]) && $cnf_tags[1] == $tag) {
            $attr_id = $matches_id[1] ?? 'toc-s-' . $idxs;

            $sc_items[$index] = '<a class="mgp-toc-subtitle" href="#' . $attr_id . '">' . $item . '</a>';
            $idxs++;
          }
        } else {
          if ($cnf_tags[0] == $tag) {
            $sc_items[$index] = '<strong class="mgp-toc-title" data-index="' . $idx . '">' . $item . '</strong>';
            $idx++;
          } elseif (isset($cnf_tags[1]) && $cnf_tags[1] == $tag) {
            $sc_items[$index] = '<span class="mgp-toc-subtitle" data-index="' . $idxs . '">' . $item . '</span>';
            $idxs++;
          }
        }
      }

      ksort($sc_items);
    }

    return $sc_items;
  }

  /**
   * 输出目录HTML结构
   *
   * @param array $args
   * location 所在位置，content 正文 | widget 小工具
   * class 外层css类名
   * @return string
   */
  public static function toc_html($args = ['location' => 'content', 'class' => 'toc-on-content', 'post_id' => null])
  {
    $post_id = $args['post_id'] ?? get_the_ID();
    // $cur_post = get_post($post_id);
    $location = $args['location'] ?? 'content';

    /**
     * 文章目录处理
     */
    $tc_mode = self::opt('ct_mode');
    $tc_tags = 'data-wb-ct="' . $tc_mode . '"';

    $sc_items = self::get_toc_items($post_id, $args);

    /**
     * 整个模块内容处理
     */
    $html = '';
    $items_html = '';

    if (count($sc_items) > 1) {

      foreach ($sc_items as $index => $item) {
        $items_html .= '<div class="mgp-toc-item">' . $item . '</div>';
      }

      $css_class = $args['class'] ?? '';
      $toc_label = self::opt('toc_label') ? self::opt('toc_label') : __('内容目录', WB_MAGICPOST_TD);
      $title_html =  '';
      $style =  '';

      if ($location == 'widget') {
        $style .= self::opt('style_widget_max') ? '--mgp-toc-mxh: ' . (int)self::opt('style_widget_max') * 2 . 'em;' : '';
        $style .= self::style_output('widget');
        $title_html .= '<h3 class="magicpost-toc-title widget-title">' . $toc_label . '</h3>';
      } else {
        $style .= self::opt('style_content_max') ? '--mgp-toc-mxh: ' . (int)self::opt('style_content_max') * 2 . 'em;' : '';
        $style .= self::style_output('content');
        $title_html .= '<div class="magicpost-toc-title"><span>' . $toc_label . '</span><svg class="wb-icon wbsico-magicpost-fold"><use xlink:href="#wbsico-magicpost-fold"></use></svg></div>';
        $css_class .= self::opt('style_content_unfold') == 1 ? '' : ' toc-fold';
      }

      $html .= '<div class="magicpost-toc-wp ' . $css_class . '" style="' . $style . '" ' . $tc_tags . '>
      ' . $title_html . '

        <div class="mgp-toc-inner">
          <div class="mgp-toc-items">' . $items_html . ' </div>
           </div>
         </div>';
    }

    return $html;
  }

  /**
   * 样式参数转换为css变量
   *
   * @param [type] $location 对应输出位置，以组合成合适的css变量名
   * @return string
   */
  public static function style_output($location)
  {
    if (!$location) return;

    $style_data = self::opt('style');
    $style_items = $style_data[$location] ?? [];
    $result = '';
    if (!empty($style_items)) {
      foreach ($style_items as $key => $item) {
        if (!$item) continue;

        $k = ' --mgp-toc-' . str_replace('_', '-', $key);
        $v = is_numeric($item) ? $item . 'px' : $item;
        $result .= $k . ': ' . $v . '; ';
      }
    }

    return $result;
  }

  /**
   * 传统编辑器插入短代码
   */

  /**
   * 是否启用古腾堡
   * @return bool
   */
  public static function is_active_gutenberg_editor()
  {
    if (function_exists('is_gutenberg_page') && is_gutenberg_page()) {
      return true;
    }

    global $current_screen;
    $current_screen = get_current_screen();
    if (method_exists($current_screen, 'is_block_editor') && $current_screen->is_block_editor()) {
      return true;
    }
    return false;
  }

  /**
   * 文章编辑页判断是否启用古腾堡选择功能形式
   */
  public static function admin_post_handle()
  {

    if (!self::is_active_gutenberg_editor()) {

      add_filter('mce_external_plugins', array(__CLASS__, 'add_plugin'));
      add_filter('mce_buttons', array(__CLASS__, 'register_button'));
    }
  }

  public static function add_plugin($plugin_array)
  {
    $plugin_array['magicpost_insert_shortcode'] = MAGICPOST_URI . 'assets/magicpost_insert_shortcode.js';
    return $plugin_array;
  }

  public static function register_button($buttons)
  {
    array_push($buttons, "", "magicpost_insert_shortcode");
    return $buttons;
  }

  /**
   * 前端资源输出处理
   */
  function front_assets_handler()
  {
    $custom_style = self::opt('custom_style');

    if ($custom_style) {
      add_filter('magicpost_front_inline_css', function ($css) {
        $custom_style = self::opt('custom_style');
        return $css . $custom_style;
      });
    }
  }
}
