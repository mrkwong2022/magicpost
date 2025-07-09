<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

class WB_MagicPost_Download extends WB_MagicPost_Base
{
  public $post_id = 0;

  public static $dl_type_items = null;

  public static $meta_fields = array(
    'wb_dl_type', // 下载开关
    'wb_dl_mode', // 下载方式
    'wb_down_price' // 下载价格
  );


  public function __construct()
  {
    if (is_admin()) {
      add_action('wp_ajax_magicpost', array($this, 'magicpost_ajax'));
    }
    $switch = self::cnf('switch', 0);
    if (!$switch) {
      return;
    }

    if (self::$dl_type_items === null) {
      $fields = WB_MagicPost::get_fields('download');
      self::$dl_type_items = $fields['dl_type_items'] ?? [];
    }

    if (is_admin()) {
      add_action('add_meta_boxes', array($this, 'add_metabox'));
      add_action('save_post', array($this, 'save_meta_data'));
    } else {
      add_filter('the_content', array($this, 'the_content'), 40);
      add_action('wp_enqueue_scripts', array($this, 'wp_head'), 50);
      add_action('wp_footer', array($this, 'sticky_html'), 50);

      add_action('widgets_init', array($this, 'widgets_init'));
      add_filter('wb_dlip_html', array($this, 'down_html'));
    }

    add_action('wp_ajax_wb_mpdl_front', array($this, 'wb_ajax'));
    add_action('wp_ajax_nopriv_wb_mpdl_front', array($this, 'wb_ajax'));
  }

  /**
   * 管理设置
   */
  public function magicpost_ajax()
  {
    $op = sanitize_text_field(self::param('op'));
    if (!$op) {
      return;
    }
    $arrow = [
      'dip_setting',
      'dip_update'
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
      case 'dip_setting':
        $ret = ['code' => 1];
        do {
          $fields = WB_MagicPost::get_fields('download');
          // $fields['dl_type_items'] = self::$dl_type_items;
          global $wp_post_types;
          $post_types = get_post_types(['public' => true]);
          foreach ($post_types as $k => $v) {
            $post_types[$k] = $wp_post_types[$k]->label;
          }
          $fields['post_types'] = $post_types;

          $ret['opt'] = self::cnf();
          $ret['cnf'] = $fields;
          $ret['code'] = 0;
          $ret['desc'] = 'success';
        } while (0);
        self::ajax_resp($ret);


        break;

      case 'dip_update':
        $ret = ['code' => 1];
        do {

          $opt = $this->sanitize_text_field_array(self::param('opt', []));
          if (empty($opt) || !is_array($opt)) {
            $ret['desc'] = 'illegal';
            break;
          }
          update_option('dlip_option', $opt);

          $ret['code'] = 0;
          $ret['desc'] = 'success';
        } while (0);
        self::ajax_resp($ret);
        break;
    }
  }

  public static function set_active($switch)
  {
    $opt = self::cnf();
    $opt['switch'] = $switch;
    update_option('dlip_option', $opt);
  }

  public static function get_active()
  {
    return self::cnf('switch');
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

  public function wp_head()
  {

    if (!is_single()) {
      return;
    }

    $post_id = get_the_ID();

    // 不属于支持下载功能的类型
    if (!self::is_supply($post_id)) {
      return '';
    }

    $meta_value = self::get_meta_values($post_id);
    $with_dl_info = isset($meta_value['wb_dl_type']) && $meta_value['wb_dl_type'] ? 1 : 0;

    if ($with_dl_info) {

      //若开启评论下载
      $cur_post_need_comment = isset($meta_value['wb_dl_mode']) && $meta_value['wb_dl_mode'] == 1 ? 1 : 0;
      $need_comment = self::cnf('need_comment', 0);
      if ($need_comment && $cur_post_need_comment) {
        add_filter('comment_form_field_cookies', '__return_false');
        add_action('set_comment_cookies', array(__CLASS__, 'coffin_set_cookies'), 10, 3);
      }

      $sticky_mode = self::cnf('sticky_mode', 0);
      if ($sticky_mode == 2) {
        add_filter('body_class', array(__CLASS__, 'wb_body_classes'));
      }

      if (self::get_custom_code()) {
        wp_add_inline_style('wbp-magicpost', self::get_custom_code());
      }
    }
  }

  public static function get_custom_code()
  {
    $custom_css = '';

    // 暗黑模式兼容
    $dm_class_name = self::cnf('dark_mode_class');
    if ($dm_class_name) {
      $custom_css .= $dm_class_name . '{--wb-mgp-bfc: #c3c3c3; --wb-mgp-fcs: #fff; --wb-mgp-wk: #999; --wb-mgp-wke: #686868; --wb-mgp-bgc: #2b2b2b; --wb-mgp-bbc: #4d4d4d; --wb-mgp-bcs: #686868; --wb-mgp-bgcl: #353535;}';
    }

    return $custom_css;
  }


  public function down_html($with_title = true)
  {

    $post_id = get_the_ID();
    // 不属于支持下载功能的类型
    if (!self::is_supply($post_id)) {
      return '';
    }

    $html = '';

    do {
      if (!$post_id) {
        break;
      }

      $this->post_id = $post_id;

      $meta_value = self::get_meta_values($post_id);

      //关闭资源
      if (!$meta_value['wb_dl_type']) {
        break;
      }

      $dlt_items_actived = self::get_dlt_items_actived();
      if (empty($dlt_items_actived)) {
        break;
      }

      $dl_info = array();
      foreach ($dlt_items_actived as $slug) {
        if ($slug == 'local') {
          $local_url = $meta_value['wb_down_local_url'] ?? '';
          if ($local_url) {
            $dl_info['local'] = [
              'name' => _x('直接下载', 'dl_type', WB_MAGICPOST_TD),
            ];
          }
        } elseif ($slug == 'baidu') {
          $bdurl = $meta_value['wb_down_url'] ?? '';

          if ($bdurl) {
            $dl_info['baidu'] = [
              'name' => _x('百度网盘', 'dl_type', WB_MAGICPOST_TD),
            ];
          }
        } else {
          $c_url = 'wb_down_url_' . $slug;
          $c_url_value = $meta_value[$c_url] ?? '';
          $dlt_cnf = self::get_dl_type_items_cnf();
          $c_item = $dlt_cnf[$slug] ?? [];

          if ($c_url_value) {
            $dl_info[$slug] = [
              'name' => $c_item['label'] ?? $slug,
              'icon' => $c_item['icon'] ?? 'download'
            ];
          }
        }
      }

      if (empty($dl_info)) {
        break;
      }

      $display_count = self::cnf('display_count', 0);
      $btn_align = self::cnf('btn_align', 0);
      $remark_info = self::cnf('remark', '');

      $need_login = self::cnf('need_member', 0);
      $is_login = is_user_logged_in();
      $need_comment = isset($meta_value['wb_dl_mode']) && $meta_value['wb_dl_mode'] == 1 ? 1 : 0;
      $is_comment = $this->wb_is_comment($post_id);

      $need_pay = isset($meta_value['wb_dl_mode']) && $meta_value['wb_dl_mode'] == 2 ? 1 : 0;
      $need_pay = current_user_can('edit_post', $post_id) ? 0 : $need_pay;
      $pay_tips_content = _x('该资源需支付后下载，当前出了点小问题，请稍后再试或联系站长。', 'front', WB_MAGICPOST_TD);
      $is_buy = false;

      if (class_exists('WP_VK') && class_exists('WP_VK_Order') && WP_VK_Order::post_price($post_id)) {
        $attr = array('tpl' => _x('此资源需支付%price%后下载', 'front', WB_MAGICPOST_TD));
        $pay_tips_content = WP_VK_Front::sc_vk_content($attr);
        $is_buy = WP_VK_Order::is_buy($post_id);
      }
      if ($display_count) {
        $post_down = get_post_meta($post_id, 'post_downs', true);
        if (!$post_down) $post_down = 0;
      }

      ob_start();
      if ($with_title) {
        include MAGICPOST_ROOT . '/inc/download.php';
      } else {
        include MAGICPOST_ROOT . '/inc/widget_download.php';
      }
      $html = ob_get_clean();
    } while (false);

    return $html;
  }

  public function sticky_html()
  {

    if (!is_single()) {
      return;
    }
    $post_id = $this->post_id;

    // 不属于支持下载功能的类型
    if (!self::is_supply($post_id)) {
      return '';
    }

    do {
      if (!$post_id) {
        break;
      }
      $meta_value = self::get_meta_values($post_id);

      //关闭资源
      if (!$meta_value['wb_dl_type']) {
        break;
      }

      $sticky_mode = self::cnf('sticky_mode', 0);
      include MAGICPOST_ROOT . '/inc/sticky.php';
    } while (false);
  }

  public static function wb_is_comment($post_id)
  {
    $email = null;
    $user_ID = wp_get_current_user()->ID;
    $user_name = wp_get_current_user()->display_name;

    if ($user_ID > 0) {
      $email = get_userdata($user_ID)->user_email;
    } else if (isset($_COOKIE['comment_author_email_' . COOKIEHASH])) {
      $email = str_replace('%40', '@', $_COOKIE['comment_author_email_' . COOKIEHASH]);
    } else {
      return false;
    }
    if (empty($email) && empty($user_name)) {
      return false;
    }

    // global $wpdb;
    $db = self::db();
    $pid = $post_id;
    $query = "SELECT `comment_ID` FROM {$db->comments} WHERE `comment_post_ID` = %d and `comment_approved`='1' and (`comment_author_email` = %s or `comment_author` = %s) LIMIT 1";
    if ($db->get_var($db->prepare($query, $pid, $email, $user_name))) {
      return true;
    }
  }

  public function the_content($content)
  {
    $post_id = get_the_ID();
    if (!self::is_supply($post_id)) {
      return $content;
    }

    if (self::is_supply($post_id)) {
      $content .= $this->down_html();
    }

    return $content;
  }

  public static function wb_ajax()
  {
    $post_id = intval(self::param('pid', 0));
    $dl_type = sanitize_text_field(self::param('rid'));

    $meta_value = self::get_meta_values($post_id);
    $need_login = self::cnf('need_member', 0);
    $is_login = is_user_logged_in();
    $need_comment = isset($meta_value['wb_dl_mode']) && $meta_value['wb_dl_mode'] == 1 ? 1 : 0;


    $ret = array('code' => 0, 'is_login' => is_user_logged_in(), 'data' => array());

    do {
      if (!$post_id) {
        $ret['code'] = 1;
        break;
      }
      if ($need_login && !$is_login) {
        $ret['code'] = 2;
        break;
      }
      $is_comment = 0;
      if ($need_comment) {
        $is_comment = self::wb_is_comment($post_id);
      }
      if ($need_comment && !$is_comment) {
        $ret['code'] = 3;
        break;
      }

      $dl_info = self::get_dl_info($post_id);
      if (empty($dl_info) || empty($dl_info[$dl_type]) || empty($dl_info[$dl_type]['url'])) {
        $ret['code'] = 4;
        $ret['desc'] = _x('下载方式已过期，请与站长联系', 'front', WB_MAGICPOST_TD);
      }

      $ret['data']['url'] = $dl_info[$dl_type]['url'] ?? '';
      $ret['data']['pwd'] = $dl_info[$dl_type]['pwd'] ?? '';

      $val = (int)get_post_meta($post_id, 'post_downs', true);
      $val = $val ? $val + 1 : 1;
      update_post_meta($post_id, 'post_downs', $val);
      $ret['data']['post_downs'] = $val;
    } while (false);


    header('content-type:text/json;charset=utf-8');
    echo wp_json_encode($ret);
    exit();
  }

  public function widgets_init()
  {
    wp_register_sidebar_widget('wbolt-download-info', _x('#下载信息#', 'front', WB_MAGICPOST_TD), array($this, 'wb_download_info'), array('description' => _x('侧栏展示下载信息，可选', 'front', WB_MAGICPOST_TD)));
  }

  public function wb_download_info()
  {
    echo $this->down_html(false);
  }

  public static function getPostMataVal($key, $default = 0)
  {
    $postId = get_the_ID();
    if (!$postId) return $default;
    $val = get_post_meta($postId, $key, true);
    return $val ? $val : $default;
  }

  public static function coffin_set_cookies($comment, $user, $cookies_consent)
  {
    $cookies_consent = true;
    wp_set_comment_cookies($comment, $user, $cookies_consent);
  }

  public static  function wb_body_classes($classes)
  {
    $classes[] = 'wb-with-sticky-btm';
    return $classes;
  }




  public static function cnf($key = null, $default = null)
  {
    //['switch'=>1,'need_member'=>0,'display_count'=>0,'sticky_mode'=>0,'btn_align'=>0,'remark'=>''];
    static $_option = array();
    if (!$_option) {
      $_option = get_option('dlip_option');
      if (!$_option || !is_array($_option)) {
        $_option = [];
      }
      $default_conf = array(
        'switch' => '1',
        'need_member' => '0',
        'display_count' => '0',
        'sticky_mode' => '0',
        'btn_align' => '0',
        'remark' => '',
        'dl_type_items' => ['local'],
        'dlt_custom' => [], //自定义下载方式
        'dark_mode_class' => '',
        'supply_post_types' => ['post']
      );

      $cnf_download = WB_MagicPost::get_fields('download');
      $def_values = $cnf_download['default'] ?? [];
      $default_conf = array_merge($default_conf, $def_values);

      foreach ($default_conf as $k => $v) {
        if (!isset($_option[$k])) $_option[$k] = $v;
      }
    }

    if (null === $key) {
      return $_option;
    }

    if (isset($_option[$key])) {
      return $_option[$key];
    }

    return $default;
  }


  public function add_metabox()
  {
    global $current_screen;

    $screen_now = $current_screen->id ?? '';
    if (!$screen_now) {
      return;
    }

    $supply_post_types = self::supply_post_types();
    if (!in_array($screen_now, $supply_post_types)) {
      return;
    }

    add_meta_box(
      'wbolt_meta_box_download_info_megicpost',
      _x('下载设置', 'admin, metabox', WB_MAGICPOST_TD),
      array($this, 'render_metabox'),
      $screen_now
    );
  }

  public static function get_meta_values($post_id)
  {
    $meta_values = array();
    // 常规字段
    foreach (self::$meta_fields as $field) {
      if (!$field) continue;
      $meta_values[$field] = get_post_meta($post_id, $field, true);
    }

    // 激活的上传文件方式
    $dlt_items_actived = self::get_dlt_items_actived();
    if (!empty($dlt_items_actived)) {
      foreach ($dlt_items_actived as $slug) {
        $c_url = null;
        $c_pwd = null;

        if ($slug == 'local') {
          $c_url = 'wb_down_local_url';
        } elseif ($slug == 'baidu') {
          $c_url = 'wb_down_url';
          $c_pwd = 'wb_down_pwd';
        } else {
          $c_url = 'wb_down_url_' . $slug;
          $c_pwd = 'wb_down_pwd_' . $slug;
        }

        if ($c_url) {
          $meta_values[$c_url] = get_post_meta($post_id, $c_url, true);
        }

        if ($c_pwd) {
          $meta_values[$c_pwd] = get_post_meta($post_id, $c_pwd, true);
        }
      }
    }

    if ('' === $meta_values['wb_dl_type']) {
      $meta_values['wb_dl_type'] = '0';
    }
    if ('' === $meta_values['wb_dl_mode']) {
      $meta_values['wb_dl_mode'] = '0';
    }

    return $meta_values;
  }

  public function render_metabox($post)
  {

    WB_MagicPost::assets_for_post_edit();
    $meta_value = self::get_meta_values($post->ID);

    //原有的下载方式字段wb_dl_type 改为下载开关
    $wb_dipp_switch = $meta_value['wb_dl_type'];

    // 下载方式配置
    $dl_type_items_cnf = self::get_dl_type_items_cnf();

    // 激活的下载方式
    $dlt_items_actived = self::get_dlt_items_actived();
    // 下载方式
    $dl_mode = $meta_value['wb_dl_mode'];

    // 付费下载信息
    $wpvk_active = class_exists('WP_VK');
    if ($wpvk_active) {
      $wpvk_install = 1;
    } else {
      $wpvk_install = file_exists(WP_CONTENT_DIR . '/plugins/wp-vk/index.php');
    }
    $meta_value_vk_price = get_post_meta($post->ID, 'vk_price', true);

    include MAGICPOST_ROOT . '/inc/meta_box.php';
  }

  public function save_meta_data($post_id)
  {

    if (!current_user_can('edit_post', $post_id)) return;

    $wb_dl_mode = self::param('wb_dl_mode', null);
    $wb_dl_type = self::param('wb_dl_type', null);

    // for vk plugin
    $wb_down_vk_price = self::param('wb_down_vk_price', null);

    if ($wb_dl_mode !== null && null === $wb_dl_type) {
      $wb_dl_type = 0;
    }

    if (null !== $wb_dl_type) {
      $wb_dl_type = absint($wb_dl_type);
    }
    if (null !== $wb_dl_mode) {
      $wb_dl_mode = absint($wb_dl_mode);
    }

    // 下载方式为“付费下载”才影响vk_price
    if ($wb_dl_type === 1 && $wb_dl_mode === 2 && $wb_down_vk_price !== null) {
      update_post_meta($post_id, 'vk_price', abs(floatval($wb_down_vk_price)));
    }

    foreach (self::$meta_fields as $field) {
      $value = self::param($field, null);
      if (null === $value) continue;
      $value = sanitize_text_field($value);
      update_post_meta($post_id, $field, $value);
    }

    update_post_meta($post_id, 'wb_dl_type', $wb_dl_type);
    update_post_meta($post_id, 'wb_dl_mode', $wb_dl_mode);

    // 文件上传方式
    $dlt_items_actived = self::get_dlt_items_actived();
    if (!empty($dlt_items_actived)) {
      foreach ($dlt_items_actived as $slug) {
        // 特殊处理本地下载
        if ($slug == 'local') {
          $c_url = 'wb_down_local_url';
          $c_url_value = self::param($c_url, null);
          if (null !== $c_url_value) {
            $c_url_value = sanitize_text_field($c_url_value);
            update_post_meta($post_id, $c_url, $c_url_value);
          }
          continue;
        }

        // 特殊处理百度网盘
        elseif ($slug == 'baidu') {
          $c_url = 'wb_down_url';
          $c_pwd = 'wb_down_pwd';
          $c_url_value = self::param($c_url, null);
          $c_pwd_value = self::param($c_pwd, null);
          if (null !== $c_url_value) {
            $c_url_value = sanitize_text_field($c_url_value);
            update_post_meta($post_id, $c_url, $c_url_value);
          }
          continue;
        } else {
          // 其他下载方式
          $c_url = 'wb_down_url_' . $slug;
          $c_pwd = 'wb_down_pwd_' . $slug;
          $c_url_value = self::param($c_url, null);
          $c_pwd_value = self::param($c_pwd, null);

          if (null !== $c_url_value) {
            $c_url_value = sanitize_text_field($c_url_value);
            update_post_meta($post_id, $c_url, $c_url_value);
          }
          if (null !== $c_pwd_value) {
            $c_pwd_value = sanitize_text_field($c_pwd_value);
            update_post_meta($post_id, $c_pwd, $c_pwd_value);
          }
        }
      }
    }
  }

  /**
   * 上传文件方式配置
   *
   * @return array
   */
  public static function get_dl_type_items_cnf()
  {
    static $items = null;
    if ($items !== null) {
      return $items;
    }

    $dl_type_items = self::$dl_type_items;
    $dlt_custom_items = self::cnf('dlt_custom');
    $dl_type_items = array_merge($dl_type_items, $dlt_custom_items);
    $dl_type_items_cnf = [];
    foreach ($dl_type_items as $item) {
      $dl_type_items_cnf[$item['slug']] = $item;
    }
    $items = apply_filters('wb_magicpost_dlt_type_items_cnf', $dl_type_items_cnf);

    return $items;
  }

  /**
   * 获取激活的下载方式
   * @return array
   */
  public static function get_dlt_items_actived()
  {
    static $items = null;
    if ($items !== null) {
      return $items;
    }
    // 默认激活的下载方式
    $items_default_actived = self::cnf('dl_type_items');

    // 自定义并已激活的下载方式
    $items_custom_cnf = self::cnf('dlt_custom');
    $custom_items = [];
    if (!empty($items_custom_cnf)) {
      $custom_items = array_column(array_filter($items_custom_cnf, function ($item) {
        return $item['status'] == 1;
      }), 'slug');
    }

    $items = array_merge($items_default_actived, $custom_items);
    return $items;
  }

  public static function get_dl_info($post_id)
  {
    $meta_value = self::get_meta_values($post_id);
    $dlt_items_actived = self::get_dlt_items_actived();
    if (empty($dlt_items_actived)) {
      return [];
    }

    $dl_info = array();
    foreach ($dlt_items_actived as $slug) {

      if ($slug == 'local') {
        $local_url = $meta_value['wb_down_local_url'] ?? '';
        if ($local_url) {
          $dl_info['local'] = [
            'name' => _x('直接下载', 'front', WB_MAGICPOST_TD),
            'url' => $local_url
          ];
        }
      } elseif ($slug == 'baidu') {
        $bdpsw = $meta_value['wb_down_pwd'] ?? '';
        $bdurl = $meta_value['wb_down_url'] ?? '';

        if ($bdurl) {
          $dl_info['baidu'] = [
            'name' => _x('百度网盘', 'front', WB_MAGICPOST_TD),
            'url' => $bdurl,
            'pwd' => $bdpsw
          ];
        }
      } else {
        $c_url = 'wb_down_url_' . $slug;
        $c_pwd = 'wb_down_pwd_' . $slug;
        $c_url_value = $meta_value[$c_url] ?? '';
        $c_pwd_value = $meta_value[$c_pwd] ?? '';
        $dlt_cnf = self::get_dl_type_items_cnf();
        $c_item = $dlt_cnf[$slug] ?? [];

        if ($c_url_value) {
          $dl_info[$slug] = [
            'name' => $c_item['label'] ?? $slug,
            'url' => $c_url_value,
            'pwd' => $c_pwd_value,
            'icon' => $c_item['icon'] ?? 'download'
          ];
        }
      }
    }

    return $dl_info;
  }

  static public function is_supply($post)
  {
    $cur_post_type = get_post_type($post);
    return in_array($cur_post_type, self::supply_post_types());
  }

  public static function supply_post_types()
  {
    return self::cnf('supply_post_types', ['post']);
  }
}
