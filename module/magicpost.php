<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

class WB_MagicPost extends WB_MagicPost_Base
{
  public function __construct()
  {
    add_action('plugins_loaded', function () {
      load_plugin_textdomain(WB_MAGICPOST_TD, false, plugin_basename(MAGICPOST_PATH) . '/languages/');
    });

    // 插件列表页支持本地化语言展示
    add_filter('all_plugins', function ($plugins) {
      if (isset($plugins['magicpost/index.php'])) {
        $plugins_info = [
          'Name' => __('MagicPost', WB_MAGICPOST_TD),
          'Title' => __('MagicPost', WB_MAGICPOST_TD),
          'Author' => __('闪电博', WB_MAGICPOST_TD),
          'AuthorName' => __('闪电博', WB_MAGICPOST_TD),
          'Description' => __('MagicPost（中文为魔法文章），如其名，该插件的主要目的是为WordPress的文章管理赋予更多高效，增强的功能。如定时发布管理，文章搬家，文章翻译，HTML代码清洗，下载文件管理和社交分享小组件。', WB_MAGICPOST_TD),
          'AuthorURI' => __('https://www.wbolt.com/', WB_MAGICPOST_TD)
        ];
        $plugins['magicpost/index.php'] = array_merge($plugins['magicpost/index.php'], $plugins_info);
      }
      return $plugins;
    });

    if (is_admin()) {

      add_action('admin_menu', array($this, 'admin_menu'));

      add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
      add_filter('plugin_action_links', array($this, 'actionLinks'), 10, 2);

      add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 1);
      add_action('admin_head-post.php', array($this, 'admin_head_post'), 1);
      add_action('admin_head-post-new.php', array($this, 'admin_head_post'), 1);
      add_action('admin_notices', array($this, 'admin_notices'));

      add_action('wp_ajax_magicpost', array($this, 'magicpost_ajax'));

      add_action('post_submitbox_misc_actions', array($this, 'action_post_submitbox_misc_actions'));
      add_filter('wp_insert_post_data', array($this, 'filter_wp_insert_post_data'), 10, 4);
    }

    // 移动端tabbar
    if (!is_admin() && wp_is_mobile()) {
      add_action('wp_footer', array(__CLASS__, 'tabbar_handler'), 20, 1);
    }

    add_action('wp_ajax_wb_magicpost_localize', array($this, 'localize_ajax'));
    add_action('wp_ajax_nopriv_wb_magicpost_localize', array($this, 'localize_ajax'));
  }

  public function action_post_submitbox_misc_actions($post)
  {
    /*if($post->post_type !== 'tools'){
            return;
        }*/
    echo '<div style="padding:10px;"><label><input type="checkbox" name="use_current_time" value="1">' . __('发布时间变更为当前时间', 'admin, post', WB_MAGICPOST_TD) . '</label></div>';
    //echo '<div>text2</div>';
  }

  public function filter_wp_insert_post_data($data, $postarr, $unsanitized_postarr, $update = false)
  {
    if (!$update) {
      return $data;
    }
    if (!isset($data['post_type'])) {
      return $data;
    }
    if (!isset($postarr['use_current_time']) || !$postarr['use_current_time']) {
      return $data;
    }
    $data['post_date'] = current_time('mysql');
    $data['post_date_gmt'] = current_time('mysql', 1);
    //error_log(print_r($data,true)."\n",3,__DIR__.'/log.txt');
    return $data;
  }

  public function admin_head_post()
  {
    wp_register_script('wb-magicpost-post', false, null, false);
    wp_enqueue_script('wb-magicpost-post');

    $wb_magicpost_cnf_base = array(
      'ver'       => MAGICPOST_VERSION,
      'assets_ver' => MAGICPOST_VERSION,
      'pd_name'   => 'MagicPost',
      'dir'       => MAGICPOST_URI,
      'ajax_url'  => admin_url('admin-ajax.php'),
      'pid'       => get_the_ID(),
      'uid'       => wp_get_current_user()->ID,
      'locale'    => get_locale(),
    );

    $base_cnf = ' var wb_magicpost_cnf=' . wp_json_encode($wb_magicpost_cnf_base, JSON_UNESCAPED_UNICODE) . ';' . "\n" . 'var wb_i18n_magicpost=' . wp_json_encode(self::localize_editor_handler(), JSON_UNESCAPED_UNICODE) . ';';
    wp_add_inline_script('wb-magicpost-post', $base_cnf);
  }

  public function magicpost_ajax()
  {
    $op = sanitize_text_field(self::param('op'));

    if (!$op) {
      return;
    }
    $arrow = [
      'promote',
      'options',
      'verify',
      'module',
      'active',
      'get_comparison',
      'get_localize'
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
      case 'promote':
        $ret = ['code' => 0, 'desc' => 'success', 'data' => ''];
        $data = [];
        $expired = 0;
        $update_cache = false;
        do {
          $option = get_option('wb_magicpost_promote', null);
          do {
            if (!$option || !is_array($option)) {
              break;
            }

            if (!isset($option['expired']) || empty($option['expired'])) {
              break;
            }

            $expired = intval($option['expired']);
            if ($expired < current_time('U')) {
              $expired = 0;
              break;
            }

            if (!isset($option['data']) || empty($option['data'])) {
              break;
            }

            $data = $option['data'];
          } while (0);

          if ($data) {
            $ret['data'] = $data;
            break;
          }
          if ($expired) {
            break;
          }

          $update_cache = true;
          $param = ['c' => 'magicpost', 'h' => $_SERVER['HTTP_HOST']];
          $http = wp_remote_post('https://www.wbolt.com/wb-api/v1/promote', array('sslverify' => false, 'body' => $param, 'headers' => array('referer' => home_url()),));

          if (is_wp_error($http)) {
            $ret['error'] = $http->get_error_message();
            break;
          }
          if (wp_remote_retrieve_response_code($http) !== 200) {
            $ret['error-code'] = '201';
            break;
          }
          $body = trim(wp_remote_retrieve_body($http));
          if (!$body) {
            $ret['empty'] = 1;
            break;
          }
          $data = json_decode($body, true);
          if (!$data) {
            $ret['json-error'] = 1;
            $ret['body'] = $body;
            break;
          }
          //data = [title=>'',image=>'','expired'=>'2021-05-12','url=>'']
          $ret['data'] = $data;
          if (isset($data['expired']) && $data['expired'] && preg_match('#^\d{4}-\d{2}-\d{2}$#', $data['expired'])) {
            $expired = strtotime($data['expired'] . ' 23:50:00');
          }
        } while (0);
        if ($update_cache) {
          if (!$expired) {
            $expired = current_time('U') + 21600;
          }
          update_option('wb_magicpost_promote', ['data' => $ret['data'], 'expired' => $expired], false);
        }

        self::ajax_resp($ret);

        break;

      case 'options':

        $ver = get_option('wb_magicpost_ver', 0);
        $cnf = '';
        if ($ver) {
          $cnf = get_option('wb_magicpost_cnf_' . $ver, '');
        }

        self::ajax_resp(['o' => $cnf]);

        break;

      case 'verify':
        $param = array(
          'code' => sanitize_text_field(self::param('key')),
          'host' => sanitize_text_field(self::param('host')),
          'ver' => 'magicpost',
        );
        $err = '';
        do {
          $http = wp_remote_post('https://www.wbolt.com/wb-api/v1/verify', array('sslverify' => false, 'body' => $param, 'headers' => array('referer' => home_url()),));
          if (is_wp_error($http)) {
            $err = _x('校验失败，请稍后再试（错误代码001[', 'log', WB_MAGICPOST_TD) . $http->get_error_message() . '])';
            break;
          }

          if ($http['response']['code'] != 200) {
            $err = _x('校验失败，请稍后再试（错误代码001[', 'log', WB_MAGICPOST_TD) . $http['response']['code'] . '])';
            break;
          }

          $body = $http['body'];

          if (empty($body)) {
            $err = _x('发生异常错误，联系<a href="https://www.wbolt.com/?wb=member#/contact" target="_blank">技术支持</a>（错误代码 010）', 'log', WB_MAGICPOST_TD);
            break;
          }

          $data = json_decode($body, true);

          if (empty($data)) {
            $err = _x('发生异常错误，联系<a href="https://www.wbolt.com/?wb=member#/contact" target="_blank">技术支持</a>（错误代码011）', 'log', WB_MAGICPOST_TD);
            break;
          }
          if (empty($data['data'])) {
            $err = _x('校验失败，请稍后再试（错误代码004)', 'log', WB_MAGICPOST_TD);
            break;
          }
          if ($data['code']) {
            $err_code = $data['data'];
            switch ($err_code) {
              case 100:
              case 101:
              case 102:
              case 103:
                $err = _x('插件配置参数错误，联系<a href="https://www.wbolt.com/?wb=member#/contact" target="_blank">技术支持</a>（错误代码', 'log', WB_MAGICPOST_TD) . $err_code . '）';
                break;
              case 200:
                $err = _x('输入key无效，请输入正确key（错误代码200）', 'log', WB_MAGICPOST_TD);
                break;
              case 201:
                $err = _x('key使用次数超出限制范围（错误代码201）', 'log', WB_MAGICPOST_TD);
                break;
              case 202:
              case 203:
              case 204:
                $err = _x('校验服务器异常，联系<a href="https://www.wbolt.com/?wb=member#/contact" target="_blank">技术支持</a>（错误代码', 'log', WB_MAGICPOST_TD) . $err_code . '）';
                break;
              default:
                $err = _x('发生异常错误，联系<a href="https://www.wbolt.com/?wb=member#/contact" target="_blank">技术支持</a>（错误代码', 'log', WB_MAGICPOST_TD) . $err_code . '）';
            }

            break;
          }

          update_option('wb_magicpost_ver', $data['v'], false);
          update_option('wb_magicpost_cnf_' . $data['v'], $data['data'], false);

          self::ajax_resp(['code' => 0, 'data' => 'success']);
        } while (false);
        self::ajax_resp(['code' => 1, 'data' => $err]);

        break;

      case 'module':
        $module_items = WB_MagicPost::get_fields('module');
        $ret = [
          'code' => 0,
          'desc' => 'success',
          'data' => [
            'clean' => WB_MagicPost_Clean::get_active(),
            'download' => WB_MagicPost_Download::get_active(),
            'share' => WB_MagicPost_Share::get_active(),
            'move' => WB_MagicPost_Move::get_active(),
            'schedule' => WB_MagicPost_Schedule::get_active(),
            'translate' => WB_MagicPost_Translate::get_active(),
            'toc' => WB_MagicPost_Toc::get_active(),
          ],
          'items' => $module_items['items'] ?? []
        ];

        self::ajax_resp($ret);

        break;

      case 'active':
        $ret = ['code' => 1, 'desc' => 'fail'];
        $slug = sanitize_text_field(self::param('slug'));
        $active = sanitize_text_field(self::param('active', '0'));
        switch ($slug) {
          case 'clean':
            WB_MagicPost_Clean::set_active($active);
            $ret['code'] = 0;
            $ret['desc'] = 'success';
            break;
          case 'download':
            WB_MagicPost_Download::set_active($active);
            $ret['code'] = 0;
            $ret['desc'] = 'success';
            break;
          case 'share':
            WB_MagicPost_Share::set_active($active);
            $ret['code'] = 0;
            $ret['desc'] = 'success';
            break;
          case 'schedule':
            WB_MagicPost_Schedule::set_active($active);
            $ret['code'] = 0;
            $ret['desc'] = 'success';
            break;
          case 'move':
            WB_MagicPost_Move::set_active($active);
            $ret['code'] = 0;
            $ret['desc'] = 'success';
            break;
          case 'translate':
            WB_MagicPost_Translate::set_active($active);
            $ret['code'] = 0;
            $ret['desc'] = 'success';
            break;
          case 'toc':
            WB_MagicPost_Toc::set_active($active);
            $ret['code'] = 0;
            $ret['desc'] = 'success';
            break;
        }
        self::ajax_resp($ret);

        break;

      case 'get_comparison':
        $ret = [
          'code' => 0,
          'desc' => 'success'
        ];
        $ret['data'] = WBP::wb_get_json_fields('comparison.json', __DIR__ . '/json/');
        self::ajax_resp($ret);
        break;

      case 'get_localize':
        $ret = [
          'code' => 0,
          'desc' => 'success'
        ];
        $ret['data'] = self::localize_ajax_handle();
        self::ajax_resp($ret);
    }
  }

  /**
   * 供提js用本地语言数据
   *
   * @return void
   */
  public function localize_ajax()
  {
    $op = sanitize_text_field(self::param('op'));
    if (!$op) {
      return;
    }

    $arrow = [
      'front',
      'editor'
    ];
    if (!in_array($op, $arrow)) {
      return;
    }

    $ret = [
      'code' => 0,
      'desc' => 'success'
    ];
    switch ($op) {
      case 'front':
        $ret['data'] = self::localize_front_handler();
        break;
    }
    self::ajax_resp($ret);
    exit;
  }

  public function admin_menu()
  {

    global $submenu;

    add_menu_page(
      'MagicPost',
      'MagicPost',
      'administrator',
      'magicpost',
      array($this, 'render_views'),
      MAGICPOST_URI . 'assets/img/ico.svg'
    );
    $submenu_items = [
      [_x('定时发布', 'admin, menu', WB_MAGICPOST_TD), _x('定时发布', 'admin, menu', WB_MAGICPOST_TD), 'magicpost#/schedule'],
      [_x('文章搬家', 'admin, menu', WB_MAGICPOST_TD), _x('文章搬家', 'admin, menu', WB_MAGICPOST_TD), 'magicpost#/move'],
      [_x('文章翻译', 'admin, menu', WB_MAGICPOST_TD), _x('文章翻译', 'admin, menu', WB_MAGICPOST_TD), 'magicpost#/translate'],
      [_x('HTML清理', 'admin, menu', WB_MAGICPOST_TD), _x('HTML清理', 'admin, menu', WB_MAGICPOST_TD), 'magicpost#/clean'],
      [_x('下载管理', 'admin, menu', WB_MAGICPOST_TD), _x('下载管理', 'admin, menu', WB_MAGICPOST_TD), 'magicpost#/download'],
      [_x('社交分享', 'admin, menu', WB_MAGICPOST_TD), _x('社交分享', 'admin, menu', WB_MAGICPOST_TD), 'magicpost#/share'],
      [_x('内容目录', 'admin, menu', WB_MAGICPOST_TD), _x('内容目录', 'admin, menu', WB_MAGICPOST_TD), 'magicpost#/toc'],
      [_x('编辑增强', 'admin, menu', WB_MAGICPOST_TD), _x('编辑增强', 'admin, menu', WB_MAGICPOST_TD), 'magicpost#/enhance'],
      [_x('插件设置', 'admin, menu', WB_MAGICPOST_TD), _x('插件设置', 'admin, menu', WB_MAGICPOST_TD), 'magicpost#/home']
    ];

    foreach ($submenu_items as $item) {
      add_submenu_page('magicpost', $item[0], $item[1], 'administrator', $item[2], array($this, 'render_views'));
    }
    if (!get_option('wb_magicpost_ver', 0)) {
      add_submenu_page('magicpost', _x('升至Pro版', 'admin, menu', WB_MAGICPOST_TD), '<span style="color: #FCB214;">' . _x('升至Pro版', 'admin, menu', WB_MAGICPOST_TD) . '</span>', 'administrator', "https://www.wbolt.com/plugins/magicpost' target='_blank'");
    }

    unset($submenu['magicpost'][0]);
  }

  public static function render_views()
  {
    if (!current_user_can('manage_options')) {
      wp_die(esc_html(__('You do not have sufficient permissions to access this page.')));
    }

    echo '<div id="app"></div>';
  }

  public function admin_enqueue_scripts($hook)
  {
    if (!str_contains($hook ?? '', 'magicpost')) {
      return;
    }

    wp_register_script('magicpost-inline-js', false, null, false);
    wp_enqueue_script('magicpost-inline-js');

    $prompt_items = WBP::wb_get_json_fields('prompt.json', __DIR__ . '/json/');

    $wb_cnf = array(
      'wbp_security' => wp_create_nonce('wp_ajax_wb_magicpost'),
      'base_url' => admin_url(),
      'home_url' => home_url(),
      'ajax_url' => admin_url('admin-ajax.php'),
      'dir_url' => MAGICPOST_URI,
      'pd_code' => 'magicpost',
      'doc_url' => "https://www.wbolt.com/magicpost-plugin-documentation.html",
      'pd_title' => _x('MagicPost', '产品名称', WB_MAGICPOST_TD),
      'pd_version' => MAGICPOST_VERSION,
      'is_pro' => intval(get_option('wb_magicpost_ver', 0)),
      'action' => array(
        'act' => 'magicpost',
        'fetch' => 'get_setting',
        'push' => 'set_setting'
      ),
      'locale' => get_locale(),
      'actpanel_visible' => in_array(get_locale(), ['zh_CN', 'zh_TW'], true),
      'prompt' => $prompt_items,
    );

    wp_add_inline_script(
      'magicpost-inline-js',
      ' var wbp_js_cnf=' . wp_json_encode($wb_cnf, JSON_UNESCAPED_UNICODE) . ';',
      'before'
    );

    echo WB_Vite::vite('src/main.js', MAGICPOST_PATH . '/assets/wbp/', MAGICPOST_URI . '/assets/wbp/');

    wp_enqueue_media();
  }

  public function plugin_row_meta($links, $file)
  {

    $base = plugin_basename(MAGICPOST_BASE);
    if ($file == $base) {
      $links[] = '<a href="https://www.wbolt.com/plugins/magicpost?utm_source=magicpost_setting&utm_medium=link&utm_campaign=plugins_list" target="_blank">' . _x('插件主页', 'admin, link', WB_MAGICPOST_TD) . '</a>';
      $links[] = '<a href="https://www.wbolt.com/magicpost-plugin-documentation.html?utm_source=magicpost_setting&utm_medium=link&utm_campaign=plugins_list" target="_blank">' . _x('说明文档', 'admin, link', WB_MAGICPOST_TD) . '</a>';
      $links[] = '<a href="https://www.wbolt.com/plugins/magicpost#J_commentsSection" target="_blank">' . _x('反馈', 'admin, link', WB_MAGICPOST_TD) . '</a>';
    }
    return $links;
  }

  public function admin_notices()
  {
    global $current_screen;
    if (!current_user_can('update_plugins')) {
      return;
    }
    if (!str_contains($current_screen->parent_base ?? '', 'magicpost')) {
      return;
    }

    $current         = get_site_transient('update_plugins');
    if (!$current) {
      return;
    }
    $plugin_file = plugin_basename(MAGICPOST_BASE);
    if (!isset($current->response[$plugin_file])) {
      return;
    }
    $all_plugins     = get_plugins();
    if (!$all_plugins || !isset($all_plugins[$plugin_file])) {
      return;
    }
    $plugin_data = $all_plugins[$plugin_file];
    $update = $current->response[$plugin_file];

    $update_url = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=') . $plugin_file, 'upgrade-plugin_' . $plugin_file);

    $pd_name = $plugin_data['Name'];
    echo  '<div class="update-message notice inline notice-warning notice-alt"><p>' . esc_html($pd_name) . __('有新版本可用。', WB_MAGICPOST_TD);
    echo  '<a href="' . esc_url($update->url) . '" target="_blank" aria-label="' . sprintf(_x('查看 %s 版本', '%s产品名', WB_MAGICPOST_TD), $pd_name) . esc_attr($update->new_version) . '">' . sprintf(__('查看版本 %s 详情', WB_MAGICPOST_TD), esc_html($update->new_version)) . '</a>';
    echo  _x('或', 'or', WB_MAGICPOST_TD) . '<a href="' . esc_url($update_url) . '" class="update-link" aria-label="' . sprintf(_x('现在更新%s', '%s产品名', WB_MAGICPOST_TD), $pd_name) . '">' . _x('现在更新', 'link', WB_MAGICPOST_TD) . '</a>。</p></div>';
  }


  public static function actionLinks($links, $file)
  {

    if (!str_contains($file ?? '', 'magicpost/')) {
      return $links;
    }
    if (!get_option('wb_magicpost_ver', 0)) {
      $a_link = '<a href="https://www.wbolt.com/plugins/magicpost" target="_blank"><span style="color: #FCB214;">' . _x('升至Pro版', 'admin, link', WB_MAGICPOST_TD) . '</span></a>';
      array_unshift($links, $a_link);
    }
    $a_link = '<a href="' . menu_page_url('magicpost', false) . '#/home">' . _x('设置', 'admin, link', WB_MAGICPOST_TD) . '</a>';
    array_unshift($links, $a_link);

    return $links;
  }


  /**
   * 文章编辑页通用插入css or js
   */
  public static function assets_for_post_edit()
  {
    $download_module_switch = WB_MagicPost_Download::get_active();
    $clean_module_switch = WB_MagicPost_Clean::get_active();

    if (!$download_module_switch && !$clean_module_switch) {
      return;
    }

    wp_enqueue_style('wbp_magicpost_post', MAGICPOST_URI . 'assets/wbp_magicpost_post.css', array(), MAGICPOST_VERSION);
    wp_enqueue_script('wbp_magicpost_post', MAGICPOST_URI . 'assets/wbp_magicpost_post.js', array(), MAGICPOST_VERSION);

    // 加载搜索替换脚本
    wp_enqueue_script('wbp-search-replace', MAGICPOST_URI . 'assets/search-replace.js', array('jquery'), MAGICPOST_VERSION, true);

    wp_register_style('wbmgp-inline', false, null, false);
    wp_enqueue_style('wbmgp-inline');
    $inline_css = ':root{--wbmgp-icon: url(' . MAGICPOST_URI . 'assets/img/icon_for_metabox.svg);}';
    wp_add_inline_style('wbmgp-inline', $inline_css);
  }

  public static function tabbar_handler($html)
  {
    if (is_single() && WB_MagicPost_Share::is_tabbar_active()) {
      include MAGICPOST_ROOT . '/inc/tabbar-post.php';
    }

    return $html;
  }

  public static function localize_ajax_handle()
  {
    $locale = get_locale();
    $cache_key = 'wb_localize_' . $locale . '_' . WB_MAGICPOST_TD . '_' . MAGICPOST_VERSION;
    $cache_data = get_transient($cache_key);
    if ($cache_data) {
      return $cache_data;
    }

    $lang_data = [];
    if (file_exists(__DIR__ . '/localize/_localize_be.php')) {
      include __DIR__ . '/localize/_localize_be.php';
    }

    apply_filters('wb_magicpost_locales_data', $lang_data);

    $format_data = [];
    foreach ($lang_data as $k => $v) {
      $format_data[WBP::set_localize_key($k)] = $v;
    }
    set_transient($cache_key, $format_data, DAY_IN_SECONDS);

    return $format_data;
  }

  public static function localize_editor_handler()
  {
    $locale = get_locale();
    $cache_key = 'wb_cache_localize_' . $locale . '_' . WB_MAGICPOST_TD . '_editor' . MAGICPOST_VERSION;
    $lang_data = [];
    $lang_data = get_transient($cache_key);

    if (empty($lang_data)) {
      $lang_data = [];
      if (file_exists(__DIR__ . '/localize/_localize_admin_js.php')) {
        include __DIR__ . '/localize/_localize_admin_js.php';
      }
      $format_data = [];
      foreach ($lang_data as $k => $v) {
        $format_data[WBP::set_localize_key($k)] = $v;
      }
      set_transient($cache_key, $format_data, DAY_IN_SECONDS);
      $lang_data = $format_data;
    }

    return $lang_data;
  }

  public static function localize_front_handler()
  {
    $locale = get_locale();
    $cache_key = 'wb_cache_localize_' . $locale . '_' . WB_MAGICPOST_TD . '_front' . MAGICPOST_VERSION;
    $lang_data = [];
    $lang_data = get_transient($cache_key);

    if (empty($lang_data)) {
      $lang_data = [];
      if (file_exists(__DIR__ . '/localize/_localize_front_js.php')) {
        include __DIR__ . '/localize/_localize_front_js.php';
      }
      $format_data = [];
      foreach ($lang_data as $k => $v) {
        $format_data[WBP::set_localize_key($k)] = $v;
      }
      set_transient($cache_key, $format_data, DAY_IN_SECONDS);
      $lang_data = $format_data;
    }

    return $lang_data;
  }

  /**
   * 获取配置
   * @param string $key
   *
   * @return mixed|string
   */
  public static function get_fields($key = '')
  {
    try {
      $fields = WBP::wb_get_json_fields('fields.json', MAGICPOST_PATH . '/module/json/');
      if (empty($fields) || !is_array($fields)) {
        throw new Exception('Invalid JSON format or empty content.');
      }
      $fields = apply_filters('wb_magicpost_fields', $fields);
    } catch (Exception $e) {
      return ['error' => 'Failed to read JSON: ' . $e->getMessage()];
    }

    if ($key) {
      return $fields[$key] ?? '';
    }

    return $fields;
  }
}
