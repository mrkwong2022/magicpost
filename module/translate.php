<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

class WB_MagicPost_Translate extends WB_MagicPost_Base
{
  public $debug = false;
  public $msg = null;

  public function __construct()
  {
    //$cnf = self::cnf();
    if ($this->can_use()) {
      add_action('translate_single_post', array($this, 'translate_single_post'));
      add_action('magic_post_translate_post', array($this, 'translate_post_cron'));
      if (!wp_next_scheduled('magic_post_translate_post')) {
        $time = strtotime(current_time('Y-m-d H:00:00', 1)) + 10;
        wp_schedule_event($time, 'hourly', 'magic_post_translate_post');
      }
      add_action('magic_post_baidu_translate_get_doc', array($this, 'magic_post_baidu_translate_get_doc'));
    }

    if (is_admin()) {
      add_action('wp_ajax_magicpost', array($this, 'magicpost_ajax'));
      if ($this->can_use()) {
        add_action('admin_init', array($this, 'admin_init_bulk_actions'));
        add_filter('post_row_actions', array($this, 'post_row_actions'), 99, 2);
      }
      add_action('restrict_manage_posts', array($this, 'restrict_manage_posts'), 10, 2);
      add_action('parse_query', array($this, 'admin_parse_query'));
    }
  }

  public function admin_parse_query($obj)
  {
    global $current_user;
    $is_trans = intval(self::param('is_trans', 0, 'g'));
    if ($is_trans && $current_user && $current_user->has_cap('edit_posts') && get_option('wb_magicpost_ver', 0)) {

      if ($is_trans == 1) {
        $obj->query_vars['meta_key'] = 'm-p-t-s';
        $obj->query_vars['meta_value'] = '1';
      } else if ($is_trans == 2) {
        if (!isset($obj->query_vars['meta_query'])) {
          $obj->query_vars['meta_query'] = array();
        }
        $obj->query_vars['meta_query'][] = array(
          'relation' => 'OR',
          array('key' => 'm-p-t-s', 'compare' => 'NOT EXISTS'),
          //array('key'=>'url_in_baidu','value'=>'2'),
        );
      }
    }
  }

  public function restrict_manage_posts($post_type, $which)
  {
    if (current_user_can('edit_posts') && get_option('wb_magicpost_ver', 0)) {

      if (!self::cnf('switch')) {
        return;
      }
      $is_trans = self::param('is_trans', 0, 'g');
      echo '<select name="is_trans"><option value="">' . __('翻译状态', WB_MAGICPOST_TD) . '</option>';
      foreach (array(1 => __('已翻译', WB_MAGICPOST_TD), 2 => __('未翻译', WB_MAGICPOST_TD)) as $k => $v) {
        echo '<option value="' . esc_attr($k) . '" ' . ($is_trans == $k ? 'selected' : '') . '>' . esc_html($v) . '</option>';
      }
      echo '</select>';
    }
  }

  public function admin_init_bulk_actions()
  {
    global $wp_post_types;

    if ($wp_post_types && is_array($wp_post_types)) foreach ($wp_post_types as $type) {
      if (in_array($type->name, ['attachment'])) {
        continue;
      }
      if ($type->public) {
        add_filter('bulk_actions-edit-' . $type->name, array($this, 'bulk_actions'), 90);
      }
    }
  }

  public function post_row_actions($actions, $post)
  {
    static $has_inline_js = false;
    if ($this->can_use() && current_user_can('edit_post', $post->ID)) {
      $api = self::cnf('api');
      do {
        if ($post->post_status != 'draft') {
          break;
        }
        if (empty($post->post_content)) {
          break;
        }
        if (empty($post->post_title)) {
          break;
        }
        if ($api == 'baidu') {
          $doc_id = get_post_meta($post->ID, 'wbmpbdfydocid', 1);
          if ($doc_id) {
            $actions['magicpos_translate_doc_query_action'] = '<a class="magicpos_translate_doc_query_action" data-post_id="' . $post->ID . '" href="javascript:;">' . __('查询翻译', WB_MAGICPOST_TD) . '</a>';
          }
        }

        $trans_state = get_post_meta($post->ID, 'm-p-t-s', 1);
        if ($trans_state === '1') {
          break;
        }
        $actions['magicpos_translate_action'] = '<a class="magicpos_translate_action" data-post_id="' . $post->ID . '" href="javascript:;">' . __('翻译', WB_MAGICPOST_TD) . '</a>';
      } while (0);


      if (!$has_inline_js) {
        $nonce = wp_create_nonce('wp_ajax_wb_magicpost');
        $has_inline_js = true;
        $js = array();
        $js[] = "jQuery('a.magicpos_translate_action').on('click',function(){var obj = jQuery(this);";
        $js[] = "jQuery.post(ajaxurl,{_ajax_nonce:'" . $nonce . "',action:'magicpost','op':'translate_row',post_id:obj.data('post_id')},function(ret){";
        $js[] = "if(ret){obj.parents('tr').find('.row-title').parent().append(ret); }obj.remove();";
        $js[] = "});return false;});";

        if ($api == 'baidu') {
          $js[] = "jQuery('a.magicpos_translate_doc_query_action').on('click',function(){var obj = jQuery(this);";
          $js[] = "jQuery.post(ajaxurl,{_ajax_nonce:'" . $nonce . "',action:'magicpost','op':'baidu_doc_query',post_id:obj.data('post_id')},function(ret){";
          $js[] = "if(ret){obj.parents('tr').find('.row-title').parent().append(ret); }obj.remove();";
          $js[] = "});return false;});";
        }

        wp_add_inline_script('wp-auth-check', implode('', $js));
      }
    }
    return $actions;
  }

  public function bulk_actions($actions)
  {
    static $has_bulk_inline_js = false;
    if ($this->can_use() && current_user_can('administrator')) {
      $actions['magicpost_translate'] = __('批量翻译', WB_MAGICPOST_TD);
      if (!$has_bulk_inline_js && get_option('wb_magicpost_ver', 0)) {
        $has_bulk_inline_js = true;
        $js = array();
        $fun_js = array();
        $fun_js[] = "var ckb = h('.check-column :checkbox:checked');";
        $fun_js[] = "if(ckb.length<1){return false;}";
        $fun_js[] = "var n =1;ckb.each(function(idx,el){";
        $fun_js[] = "var tr = h(el).parents('tr');if(tr.find('a.magicpos_translate_action').length<1)return;";
        $fun_js[] = " n++;tr.find('.row-title').parent().append('<span class=\"mgt-run\"> [翻译中...]</span>');setTimeout(function(){h.post(ajaxurl,{action:'magicpost','op':'translate_row',post_id:h(el).val()},function(ret){";
        $fun_js[] = "  if(ret){var rp = tr.find('.row-title').parent();rp.find('.mgt-run').remove();rp.append(ret);tr.find('a.magicpos_translate_action').remove(); }";
        $fun_js[] = " });},n * 1000);";
        $fun_js[] = "});";
        $js[] = "(function(h){";
        $js[] = "h('#doaction').on('click',function(e){";
        $js[] = "var btn = h(this);var op = btn.prev().val();";
        $js[] = "if(op=='magicpost_translate'){" . implode('', $fun_js) . "e.preventDefault();return false;}";

        $js[] = "});";
        $js[] = "})(jQuery);";

        wp_add_inline_script('wp-auth-check', implode('', $js));
      }
    }

    return $actions;
  }

  public function translate_post_cron()
  {
    // global $wpdb;
    $this->txt('translate post in cron');

    if (!$this->can_use()) {
      return;
    }
    $cnf = self::cnf();

    if (!$cnf['auto']) {
      return;
    }

    $api = $cnf['api'] ?? 'google';
    if ($api == 'baidu') {
      $this->baidu_translate_get_doc_cron();
    }
    $db = self::db();

    do {
      $num = 50;
      $subsql = " AND NOT EXISTS(SELECT m.meta_id FROM $db->postmeta m WHERE m.meta_key='m-p-t-s' AND m.meta_value='1' AND m.post_id=a.ID)";
      $sql = "SELECT a.ID FROM $db->posts a WHERE post_status='draft' AND post_type='post' $subsql AND post_title<>'' AND post_content <>''";
      $this->txt($sql);
      $post_id = $db->get_col($sql . " LIMIT " . $num);
      if (!$post_id) {
        break;
      }
      $time = current_time('U', 1) + 10;
      foreach ($post_id as $id) {
        if (!wp_next_scheduled('translate_single_post', array($id))) {
          $time += 10;
          wp_schedule_single_event($time, 'translate_single_post', array($id));
        }
      }
    } while (0);


    $this->txt('translate post finnish');
  }

  public function translate_single_post($post_id)
  {
    $this->txt('translate_single_post:' . $post_id);
    $state = get_post_meta($post_id, 'm-p-t-s', true);
    if ($state === '1') {
      return;
    }
    $post = get_post($post_id);
    if (!$post) {
      return;
    }
    if ('draft' !== $post->post_status) {
      return;
    }
    $ret = $this->translate($post);
    if ($ret['code']) {
      $error_list = get_option('magicpost_translate_error', []);
      if (!$error_list || !is_array($error_list)) {
        $error_list = [];
      }
      $err = sprintf(__('文章：%s 翻译失败，错误：%s', WB_MAGICPOST_TD), $post->post_title, $ret['desc']);
      array_unshift($error_list, $err);
      $error_list = array_slice($error_list, 0, 20);
      update_option('magicpost_translate_error', $error_list, false);
    } else {
      update_post_meta($post->ID, 'm-p-t-s', '1');
    }
    $this->txt('translate_single_post finnish');
  }

  public function translate($post)
  {
    // global $wpdb;
    $ret = ['code' => 0, 'desc' => 'success'];
    $cnf = self::cnf();
    if (!$this->can_use()) {
      $ret['code'] = 1;
      $ret['desc'] = __('翻译不可用', WB_MAGICPOST_TD);
      return $ret;
    }
    $txt = [];
    $fields = [];
    $trans = $this->get_service($cnf);
    $trans->post = $post;
    if (in_array('post_title', $cnf['trans']) && $post->post_title) {
      $fields[] = 'post_title';
      $txt[] = trim($post->post_title);
    }
    if (in_array('post_content', $cnf['trans']) && $post->post_content) {
      $fields[] = 'post_content';
      $txt[] = trim($post->post_content);
    }

    $result = $trans->translate($txt, $fields, $post);
    if ($result === false) {
      $ret['code'] = 1;
      $ret['desc'] = $trans->get_error();
      return $ret;
    }

    $ret = $trans->set_translate_result($fields, $result, $post);

    return $ret;
  }

  public function get_service($cnf)
  {
    //if($cnf['baidu'])
    $api = $cnf['api'] ?? 'google';
    $api = 'deepl';
    $target = ['en-zh' => ['en', 'zh-CN'], 'zh-en' => ['zh-CN', 'en']];
    if ($api === 'baidu') {
      $target = ['en-zh' => ['en', 'zh'], 'zh-en' => ['zh', 'en']];
      $obj = new WB_MagicPost_Baidu_Api();
    } else if ($api === 'deepl') {
      $target = ['en-zh' => ['EN', 'ZH-HANS'], 'zh-en' => ['ZH', 'EN-US']];
      $obj = new WB_MagicPost_Deepl_Api();
    } else {
      $obj = new WB_MagicPost_Google_Api();
      $obj->key = $cnf['google']['key'];
    }

    $obj->source = $target[$cnf['lan']][0];
    $obj->target = $target[$cnf['lan']][1];

    $obj->cnf = $cnf;

    return $obj;
  }


  public function baidu_translate_get_doc_cron()
  {
    // global $wpdb;

    $db = self::db();
    $num = 30;
    $sql = "SELECT post_id FROM $db->postmeta m WHERE m.meta_key='wbmpbdfydocid'";
    $this->txt($sql);
    $list = $db->get_col($sql . " LIMIT " . $num);
    if (!$list) return;
    $time = current_time('U', 1) + 10;
    foreach ($list as $id) {
      if (!wp_next_scheduled('magic_post_baidu_translate_get_doc', array($id))) {
        $time += 10;
        wp_schedule_single_event($time, 'magic_post_baidu_translate_get_doc', array($id));
      }
    }
  }

  public function magic_post_baidu_translate_get_doc($post_id)
  {
    // global $wpdb;
    $this->txt('magic_post_baidu_translate_get_doc:' . $post_id);
    $doc_meta_key = 'wbmpbdfydocid';
    $job = get_post_meta($post_id, $doc_meta_key, true);
    if (!$job) {
      return false;
    }
    if (!is_array($job) || !isset($job['id'])) {
      delete_post_meta($post_id, 'wbmpbdfydocid');
      return false;
    }

    $post = get_post($post_id);
    if (!$post) {
      delete_post_meta($post_id, 'wbmpbdfydocid');
      return false;
    }
    if ('draft' !== $post->post_status) {
      delete_post_meta($post_id, 'wbmpbdfydocid');
      return false;
    }


    $obj = new WB_MagicPost_Baidu_Api();

    $ret = $obj->doc_translate_result($job['id']);
    $error = null;
    $message = null;
    do {
      if ($ret === false) {
        $error = sprintf(__('文章：%s 百度获取文档翻译失败，错误：%s', WB_MAGICPOST_TD), $post->post_title, $obj->get_error());
        break;
      }
      $result = $ret['result'];
      if (!isset($result['data']) || empty($result['data'])) {
        $error = sprintf(__('文章：%s 百度获取文档翻译失败，data 为空', WB_MAGICPOST_TD), $post->post_title);
        break;
      }
      $data = $result['data'];
      if ($data['status'] == 'Failed') {
        $error = sprintf(__('文章：%s 百度获取文档翻译失败，%s', WB_MAGICPOST_TD), $post->post_title, $data['reason']);
        break;
      }
      if ($data['status'] == 'Succeeded') {
        $doc_list = $data['output']['files'];
        $content = '';
        foreach ($doc_list as $doc) {
          $this->txt($doc['url']);
          $http = wp_remote_get($doc['url'], ['timeout' => 5, 'sslverify' => false,]);
          if (is_wp_error($http)) {
            $this->txt($http->get_error_message());
            $error = sprintf(__('获取翻译结果出错，%s', WB_MAGICPOST_TD), $http->get_error_message());
          } else {
            $content .= wp_remote_retrieve_body($http);
          }
        }
        if ($error) {
          break;
        }
        $this->txt(print_r($content, true));
        if ($content) {
          preg_match('#<body>(.+)</body>#is', $content, $match);
          $content = $match[1];
          $up = [];
          if (preg_match('#<div id="magicpost-title">([^<]+)</div>#is', $content, $match)) {
            $up['post_title'] = trim($match[1]);
            $content = str_replace($match[0], '', $content);
          }
          $up['post_content'] = $content;

          $up['ID'] = $post->ID;
          wp_update_post($up);
        }
        delete_post_meta($post_id, 'wbmpbdfydocid');
        $message = __('翻译成功', WB_MAGICPOST_TD);
        break;
      }
      $message = ['NotStarted' => __('待翻译', WB_MAGICPOST_TD), 'Running' => __('翻译中', WB_MAGICPOST_TD)][$data['status']]  ?? __('翻译失败', WB_MAGICPOST_TD);
    } while (0);


    if ($error) {
      $error_list = get_option('magicpost_translate_error', []);
      if (!$error_list || !is_array($error_list)) {
        $error_list = [];
      }
      array_unshift($error_list, $error);
      $error_list = array_slice($error_list, 0, 20);
      update_option('magicpost_translate_error', $error_list, false);
      return $error;
    }


    return $message;
  }

  public function txt($msg)
  {
    if (!$this->debug) {
      return;
    }
    $msg = is_array($msg) ? wp_json_encode($msg, JSON_UNESCAPED_UNICODE) : $msg;
    error_log(current_time('mysql') . " $msg \n", 3, MAGICPOST_ROOT . '/translate.log');
  }

  public function magicpost_ajax()
  {
    // global $wpdb, $wp_taxonomies, $wp_post_types;

    $op = sanitize_text_field(self::param('op'));

    if (!$op) {
      return;
    }
    $arrow = [
      'baidu_doc_query',
      'translate_row',
      'translate_setting',
      'translate_update'
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
      case 'baidu_doc_query':
        $msg = __('翻译失败', WB_MAGICPOST_TD);
        do {
          $id = intval(self::param('post_id', 0));
          if (!$id) {
            break;
          }
          $msg = $this->magic_post_baidu_translate_get_doc($id);
          if (!$msg) {
            $msg = __('翻译失败', WB_MAGICPOST_TD);
          }
        } while (0);
        echo esc_html($msg);
        exit();
        break;
      case 'translate_row':
        $ret = array('code' => 0, 'desc' => 'success');
        do {
          $id = intval(self::param('post_id', 0));
          if (!$id) {
            break;
          }
          $trans_state = get_post_meta($id, 'm-p-t-s', 1);
          if ($trans_state === '1') {
            break;
          }
          $post = get_post($id);
          $ret = $this->translate($post);
          if ($ret['code']) {
            $error_list = get_option('magicpost_translate_error', []);
            if (!$error_list || !is_array($error_list)) {
              $error_list = [];
            }
            $err = sprintf(__('文章：%s 翻译失败，错误：%s', WB_MAGICPOST_TD), $post->post_title, $ret['desc']);
            array_unshift($error_list, $err);
            $error_list = array_slice($error_list, 0, 20);
            update_option('magicpost_translate_error', $error_list, false);
          } else {
            update_post_meta($id, 'm-p-t-s', '1');
          }
        } while (0);

        if ($ret['code']) {
          echo ' [' . __('翻译失败', WB_MAGICPOST_TD) . ':' . esc_html($ret['desc']) . ']';
        } else {
          $api = self::cnf('api');
          if ($api == 'baidu') {
            echo ' [' . __('文档翻译提交成功', WB_MAGICPOST_TD) . ']';
          } else {
            echo ' [' . __('翻译成功', WB_MAGICPOST_TD) . ']';
          }
        }
        exit();
        break;

      case 'translate_setting':
        $ret = [];
        $fields = [];
        if (class_exists('WB_MagicPost')) {
          $fields = WB_MagicPost::get_fields('translate');
        }
        $ret['opt'] = self::cnf();
        $ret['cnf'] = $fields;
        $ret['data'] = get_option('magicpost_translate_error', []);
        $ret['code'] = 0;
        $ret['desc'] = 'success';
        self::ajax_resp($ret);

        break;

      case 'translate_update':
        $ret = ['code' => 1];
        do {
          $old = self::cnf();
          $opt = $this->sanitize_text(self::param('opt', []));
          if (empty($opt) || !is_array($opt)) {
            $ret['desc'] = 'illegal';
            break;
          }
          if ($old['auto'] && !$opt['auto']) {
            wp_clear_scheduled_hook('magic_post_translate_post');
          }
          update_option('magicpost_translate', $opt);

          $ret['code'] = 0;
          $ret['desc'] = 'success';
        } while (0);
        self::ajax_resp($ret);

        break;
    }
  }

  public static function cnf($key = null, $default = null)
  {
    //['switch'=>1,'need_member'=>0,'display_count'=>0,'sticky_mode'=>0,'btn_align'=>0,'remark'=>''];
    static $_option = array();
    if (!$_option) {
      $_option = [];
      if (get_option('wb_magicpost_ver', 0)) {
        $_option = get_option('magicpost_translate');
      }
      if (!$_option || !is_array($_option)) {
        $_option = [];
      }
      $default_conf = [
        'switch' => '0',
        'api' => 'google',
        'google' => [
          'key' => '',
        ],
        'google2' => [
          'key' => 'web-client',
          'proxy' => 'none'
        ],
        'baidu' => [
          'key' => '',
          'secret' => '',
        ],
        'trans' => [
          'post_title',
          'post_content'
        ],
        'auto' => '0',
        'lan' => 'en-zh'
      ];
      foreach ($default_conf as $k => $v) {
        if (!isset($_option[$k])) $_option[$k] = $v;
      }
      foreach (['google', 'google2', 'baidu', 'trans'] as $f) {
        if (!is_array($_option[$f])) {
          $_option[$f] = $default_conf[$f];
          continue;
        }
        foreach ($default_conf[$f] as $sk => $sv) {
          if (!isset($_option[$f][$sk])) {
            $_option[$f][$sk] = $sv;
          }
        }
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

  public function can_use()
  {
    if (!get_option('wb_magicpost_ver', 0)) return false;
    $cnf = self::cnf();
    if (!$cnf['switch']) return false;
    if (!$cnf[$cnf['api']]['key']) return false;
    return true;
  }

  public static function set_active($switch)
  {
    $opt = self::cnf();
    $opt['switch'] = $switch;
    update_option('magicpost_translate', $opt);
  }

  public static function get_active()
  {
    return self::cnf('switch');
  }

  public function sanitize_text($v, $skip_key = [])
  {

    if (is_array($v)) foreach ($v as $sk => $sv) {
      if ($skip_key && in_array($sk, $skip_key)) {
        continue;
      }
      if (is_array($sv)) {
        $v[$sk] = $this->sanitize_text($sv, $skip_key);
      } else if (is_string($sv)) {
        $v[$sk] = sanitize_text_field($sv);
      }
    }
    else if (is_string($v)) {
      $v = sanitize_text_field($v);
    }
    return $v;
  }


  public function deepl_translate($text)
  {
    $api = 'https://api.deepl.com/v2/translate';
  }
}
