<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */


class WB_MagicPost_Clean extends WB_MagicPost_Base
{
  public static $cnfItems = null;

  public function __construct()
  {
    if (self::$cnfItems === null) {
      self::$cnfItems = array(
        'tags' => array(
          'normal' => array("a", "div", "span", "br", "em", "b", "i", "h1", "h2", "h3", "h4", "h5", "h6", "p", "img", "video", "strong", "section", "blockquote", "article", "figcaption", "figure"),
          'table' => array("td", "th", "tr", "col", "table", "tbody", "thead", "tfoot", "caption", "colgroup"),
          'list' => array("ul", "li", "ol", "dl", "dt", "dd"),
          'other' => array("hr", "nav", "ins", "body", "head", "html", "ruby", "title", "iframe", "script", "detail", "header", "footer")
        ),
        'attr' => array("id", "rel", "alt", "class", "style", "srcset", "sizes", "width", "height", "data-*"),
        'format' => array(
          'text-indent' => _x('段落开头空格删除', 'admin', WB_MAGICPOST_TD),
          'p2p' => _x('段落之间自动空行', 'admin', WB_MAGICPOST_TD),
          'img2img' => _x('图像之间自动空行', 'admin', WB_MAGICPOST_TD),
          'img2p' => _x('图像与段落间自动空行', 'admin', WB_MAGICPOST_TD),
          'h2p' => _x('H标题与段落间自动空行', 'admin', WB_MAGICPOST_TD)
        )
      );
    }

    if (is_admin()) {

      $switch = self::cnf('switch', 0);
      if ($switch) {
        add_action('admin_head-post.php', array(__CLASS__, 'admin_head'));
        add_action('admin_head-post-new.php', array(__CLASS__, 'admin_head'));
        add_action('media_buttons', array($this, 'add_media_button'), 20);
        add_action('media_buttons', array($this, 'add_search_replace_button'), 20);

        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'));
      }
      if ($switch && !self::cnf('gtb', 0)) {
        add_filter('use_block_editor_for_post_type', function ($is_user, $post_type) {
          return false;
        }, 10, 2);
      }
      add_action('wp_ajax_magicpost', array($this, 'magicpost_ajax'));
    }
  }

  public static function admin_enqueue_scripts()
  {
    add_editor_style(MAGICPOST_URI . 'assets/wbp_tinymce_search_replace.css');
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
      'cht_setting',
      'cht_update'
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
      case 'cht_setting':
        $ret = [];
        $ret['cnf'] = self::$cnfItems;
        $ret['opt'] = self::opt();
        $ret['code'] = 0;
        $ret['desc'] = 'success';
        self::ajax_resp($ret);
        break;

      case 'cht_update':
        $ret = ['code' => 1];
        do {
          $opt = $this->sanitize_text(self::param('opt', []), ['re_txt']);
          if (empty($opt) || !is_array($opt)) {
            $ret['desc'] = 'illegal';
            break;
          }
          $txt = '';
          if (isset($opt['re_txt'])) {
            $txt = stripslashes($opt['re_txt']);
            $opt['txt_replace'] = json_decode($txt, true);
            /*foreach($opt['txt_replace'] as $k=>$v){
                            $v['s'] = trim($v['s']);
                            $v['r'] = trim($v['r']);
                            $opt['txt_replace'][$k] = $v;
                        }*/
            unset($opt['re_txt']);
          }

          update_option('cht_option', $opt, false);

          $ret['code'] = 0;
          $ret['desc'] = 'success';
        } while (0);
        self::ajax_resp($ret);

        break;
    }
  }

  public static function set_active($switch)
  {
    $opt = self::opt();
    $opt['switch'] = $switch;
    update_option('cht_option', $opt, false);
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

  public static function opt()
  {

    static $opt = null;
    if ($opt) {
      return $opt;
    }
    $default = array(
      'switch' => '0',
      'gtb' => '0',
      'tags' => array("a", "div", "span", "article", "figcaption", "figure", "blockquote", "section", "canvas", "br", "detail", "article", "header", "footer", "hr", "iframe", "ins", "body", "head", "html", "ruby", "title", "script", "nav"),
      'attr' => array("id", "class", "style", "srcset", "sizes", "width", "height", "rel", "data-*"),
      'format' => array(
        'text-indent' => '1',
        'p2p' => '1',
        'img2img' => '1',
        'img2p' => '1',
        'h2p' => '1'
      ),
      'custom' => array(
        'tags' => array(),
        'attr' => array()
      ),
      'stag' => array(),
      'txt_replace' => array()
    );

    $opt = get_option('cht_option', null);
    if (!$opt || !is_array($opt)) {
      $opt = $default;
    }
    foreach ($default as $k => $v) {
      $opt[$k] = $opt[$k] ?? $default[$k];
    }
    foreach ($default['custom'] as $k => $v) {
      $opt['custom'][$k] = $opt['custom'][$k] ?? $default['custom'][$k];
    }

    return $opt;
  }

  public static function cnf($key, $default = null)
  {
    static $option = array();
    if (!$option) {
      $option = self::opt();
    }
    $keys = explode('.', $key);
    $find = false;
    $cnf = $option;
    foreach ($keys as $_k) {
      if (isset($cnf[$_k])) {
        $cnf = $cnf[$_k];
        $find = true;
        continue;
      }
      $find = false;
    }
    if ($find) {
      return $cnf;
    }

    return $default;
  }

  public static function admin_head()
  {

    WB_MagicPost::assets_for_post_edit();

    $cht_opt = self::opt();
    $cht_cnf = self::$cnfItems;

    $setting_url = menu_page_url('magicpost', false) . '#/clean';

    ob_start();
    include MAGICPOST_ROOT . '/inc/action_dialog.php';
    $html = ob_get_clean();
    $html = str_replace(array("\r", "\n", "\t"), '', $html);
    $_cnf = array('data' => $html);

    wp_add_inline_script('wbp_magicpost_post', 'var wbcht_cnf=' . wp_json_encode($_cnf) . ';', 'before');
  }

  public function add_media_button()
  {
    echo '<button id="wb-cls-tag-btn" type="button" class="button wb-wbsm-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="15"><path d="m12.7 10.8-1.4-1.4L13.6 7l-2.3-2.3 1.4-1.4 3 3c.4.4.4 1 0 1.4l-3 3zm-9.4 0-3-3a1 1 0 0 1 0-1.4l3-3 1.4 1.4L2.4 7l2.3 2.3-1.4 1.4zM6 14l-.3-.1a1 1 0 0 1-.6-1.3l4-12a1 1 0 0 1 1.3-.6c.5.2.8.7.6 1.3l-4 12c-.2.4-.6.7-1 .7" fill="#999" fill-rule="evenodd"/></svg><span>' . _x('清除代码', 'admin, btn', WB_MAGICPOST_TD) . '</span></button>';
  }
  public function add_search_replace_button()
  {
    echo '<button id="wb-search-replace-btn" type="button" class="button wb-wbsm-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="15"><path d="m12.7 10.8-1.4-1.4L13.6 7l-2.3-2.3 1.4-1.4 3 3c.4.4.4 1 0 1.4l-3 3zm-9.4 0-3-3a1 1 0 0 1 0-1.4l3-3 1.4 1.4L2.4 7l2.3 2.3-1.4 1.4zM6 14l-.3-.1a1 1 0 0 1-.6-1.3l4-12a1 1 0 0 1 1.3-.6c.5.2.8.7.6 1.3l-4 12c-.2.4-.6.7-1 .7" fill="#999" fill-rule="evenodd"/></svg><span>' . _x('搜索替换', 'admin, btn', WB_MAGICPOST_TD) . '</span></button>';
  }
}
