<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

class WB_MagicPost_Share extends WB_MagicPost_Base
{
	public static $is_set_script_var = false;

	public function __construct()
	{
		if (is_admin()) {
			add_action('wp_ajax_magicpost', array($this, 'magicpost_ajax'));
		}

		$switch = self::opt('dwqr_switch', 1);
		if (!$switch) {
			return;
		}

		if (!is_admin()) {
			add_filter('the_content', array($this, 'the_content'), 100);
		}

		add_action('wp_ajax_dwqr_ajax', array($this, 'dwqr_ajax_handler'));
		add_action('wp_ajax_nopriv_dwqr_ajax', array($this, 'dwqr_ajax_handler'));
		add_shortcode('wb_share_social', array($this, 'wb_share_social_handler'));

		add_action('plugins_loaded', array($this, 'migrate_options'));
	}

	public static function migrate_options()
	{
		// 更新配置字段 @remrak 2025-07-06
		if (version_compare(MAGICPOST_VERSION, '1.3.1', '>')) {
			$options = self::opt();
			if (isset($options['items']) && !empty($options['items'] && empty($options['donate']))) {
				$options['donate'] = array(
					'wechat_qrcode' => $options['items']['weixin']['img'] ?? '',
					'alipay_qrcode' => $options['items']['alipay']['img'] ?? ''
				);

				unset($options['items']);
				update_option('dwqr_option', $options);
			}
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
			'dwqr_setting',
			'dwqr_update'
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
			case 'dwqr_setting':
				$ret = [
					'code' => 0,
					'desc' => 'success',
					'cnf' => WB_MagicPost::get_fields('share'),
					'opt' => self::opt()
				];
				self::ajax_resp($ret);
				break;

			case 'dwqr_update':
				$ret = ['code' => 1];
				do {
					$opt = $this->sanitize_text_field_array(self::param('opt', []));
					if (empty($opt) || !is_array($opt)) {
						$ret['desc'] = 'illegal';
						break;
					}
					update_option('dwqr_option', $opt);

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
		$opt['dwqr_switch'] = $switch;
		update_option('dwqr_option', $opt);
	}

	public static function get_active()
	{
		return self::opt('dwqr_switch');
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
			$options = get_option('dwqr_option', array());
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

		return apply_filters('wb_magicpost_conf', $return, $name, $default, 'dwqr');
	}

	public static function def_opt()
	{

		$opt_default = array(
			/*基本设置*/
			'dwqr_switch' => '1',
			'theme_color' => '#0066CC',
			'dwqr_module' => array(
				'donate' => '1',
				'like' => '1',
				'poster' => '1',
				'share' => '1',
			),
			'dwqr_module_position' => '0',
			/*打赏*/
			// 'items' => array(
			// 	'weixin' => array(
			// 		'name' => '微信',
			// 		'img' => ''
			// 	),
			// 	'alipay' => array(
			// 		'name' => '支付宝',
			// 		'img' => ''
			// 	)
			// ),
			/*微海报*/
			'logo_url' => '',
			'cover_url' => '',
			'poster_theme' => '0',
			'cover_ratio' => '3:2',
			'tabbar_switch' => '0',
		);

		$cnf_share = WB_MagicPost::get_fields('share');
		$def_values = $cnf_share['default'] ?? [];
		$opt_default = array_merge($opt_default, $def_values);

		return $opt_default;
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
	 * 分享api配置
	 *
	 * @param string $key
	 * @return array
	 */
	public static function get_share_api_cnf($key = '')
	{
		$share_cnf_data = WB_MagicPost::get_fields('share');
		$share_cnf = $share_cnf_data['share_items_api'] ?? [];

		$ret = [];
		foreach ($share_cnf as $k => $v) {
			$ret[$v['value']] = $v;
		}

		if ($key) {
			return $ret[$key] ?? [];
		}

		return $ret;
	}

	/**
	 * 前台展示交互
	 */
	public function dwqr_ajax_handler()
	{
		$post_id = self::param('pid', 0);
		if (!$post_id) {
			$post_id = get_the_ID();
		}

		if (has_excerpt($post_id)) {
			$excerpt = rtrim(trim(wp_strip_all_tags(get_the_excerpt($post_id))), __('[原文链接]', WB_MAGICPOST_TD));
		} else {
			$excerpt =  mb_strimwidth(wp_strip_all_tags(get_the_content(null, null, $post_id)), 0, 120, "...", 'utf-8');
		}

		$excerpt = preg_replace('/\\s+/', ' ', $excerpt);
		$share_cover = self::wbolt_post_thumbnail_url($post_id);

		$op = sanitize_text_field(self::param('do'));

		switch ($op) {
			case 'get_cnf':
				$res = array('code' => 0, 'desc' => 'success');
				$res['data'] = array(
					'dir'           => MAGICPOST_URI,
					'uid'           => wp_get_current_user()->ID,
					'poster_theme'  => self::opt('poster_theme'),
					'cover_ratio'   => self::opt('cover_ratio')
				);
				header('content-type:text/json;charset=utf-8');
				echo wp_json_encode($res);
				break;

			case 'like':
				self::do_like($post_id);
				break;

			case 'wb_share_poster':
				$res = array('code' => 0, 'desc' => 'success');
				$share_logo = self::opt('logo_url');
				$title = get_the_title($post_id);

				$res['data'] = array(
					'head'      => self::wb_image_to_base64($share_cover),
					'logo'      => $share_logo ? self::wb_image_to_base64($share_logo) : 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==',
					'title'     => wp_specialchars_decode($title),
					'excerpt'   => wp_specialchars_decode($excerpt),
					'timestamp' => get_post_time('U', true, $post_id),
					'url'       => wp_get_shortlink($post_id)
				);

				header('content-type:text/json;charset=utf-8');
				echo wp_json_encode($res);
				break;

			case 'get_donate_items':
				echo self::get_donate_html();
				break;

			case 'get_social_items':
				$res = array('code' => 0, 'desc' => 'success');
				$res['html'] = self::get_share_html($post_id);
				$res['data'] = array(
					'title' => wp_specialchars_decode(get_the_title($post_id)),
					'desc'  => wp_specialchars_decode($excerpt),
					'cover' => $share_cover,
					'url'   => wp_get_shortlink($post_id)
				);
				header('content-type:text/json;charset=utf-8');
				echo wp_json_encode($res);

				break;
		}
		exit();
	}

	public function the_content($content)
	{

		if (!self::is_tabbar_active() && is_single()) {
			$content .= $this->dwqr_html();
		}
		return $content;
	}

	/**
	 * 点赞处理
	 * @param $post_id
	 */
	function do_like($post_id)
	{
		$like = get_post_meta($post_id, 'dwqr_like', true);
		if ($like) {
			$like = intval($like);
		} else {
			$like = 0;
		}
		$like++;

		update_post_meta($post_id, 'dwqr_like', $like);
		echo esc_html($like);
	}

	/**
	 * @param array $attr
	 *
	 * @return string
	 */
	function dwqr_html($attr = array())
	{
		$post_id = get_the_ID();

		$selected_module = !empty($attr['selected_module']) ? $attr['selected_module'] : self::opt('dwqr_module');
		$def_wpclass = wp_is_mobile() || !$selected_module['position'] ? 'wbp-cbm-magicpost' : 'wbp-dwqr-sticky left';
		$wp_classname = !empty($attr['wpclass']) ? $attr['wpclass'] : $def_wpclass;

		$theme_color = '';
		$inline_style = '';
		if (self::opt('theme_color') && self::opt('theme_color') != '#0066CC') {
			$custom_theme_color = esc_html(self::opt('theme_color'));
			$theme_color .= ' style="--dwqr-theme: ' . $custom_theme_color . ';" ';

			global $is_IE;
			if ($is_IE) {
				$inline_style .= '.wbp-cbm-magicpost .wb-btn-dwqr{border: 1px solid ' . $custom_theme_color . ';} ';
				$inline_style .= '.wbui-dwqr-donate .tab-cont .hl, .wbp-cbm-magicpost .wb-btn-dwqr span{color: ' . $custom_theme_color . ';} ';
				$inline_style .= '.wbp-cbm-magicpost .wb-btn-dwqr svg, .widget-social-dwqr .wb-btn-dwqr:hover svg{fill: ' . $custom_theme_color . ';} ';
				$inline_style .= '.wbp-cbm-magicpost .wb-btn-dwqr.active,.wbp-cbm-magicpost .wb-btn-dwqr:active,.wbp-cbm-magicpost .wb-btn-dwqr:hover{background-color: ' . $custom_theme_color . ';} ';
			}
		}

		$tpl = '';
		$inline_script = '';

		$like_count = get_post_meta($post_id, 'dwqr_like', true);
		$like_count = $like_count ? intval($like_count) : 0;
		$like_count = $like_count > 999 ? intval($like_count / 1000) . 'k+' : $like_count;


		if (!self::$is_set_script_var) {
			wp_register_script('wbs-front-dwqr-i', false, null, false);
			wp_enqueue_script('wbs-front-dwqr-i');
			//wp_enqueue_script('wbs-front-dwqr-i',null,[],MAGICPOST_VERSION,true);
			//print_r(['content']);
			self::$is_set_script_var = wp_add_inline_script('wbs-front-dwqr-i', $inline_script, 'before');

			//按后台加载自定义样式
			if ($inline_style) {
				wp_add_inline_style('wbs-dwqr-css', $inline_style);
			}
		}

		$tpl .= '
			<div class="' . esc_attr($wp_classname) . '" '
			. $theme_color
			. (!empty($share_url) ? 'wb-share-url="' . $share_url . '"' : '') . '><div class="dwqr-inner">';

		if (isset($selected_module['donate']) && $selected_module['donate']) {
			$tpl .= '<a class="wb-btn-dwqr wb-btn-donate j-dwqr-donate-btn" rel="nofollow"><svg class="wb-icon wbsico-donate"><use xlink:href="#wbsico-magicpost-donate"></use></svg><span>打赏</span></a>';
		}

		if (isset($selected_module['like']) && $selected_module['like']) {
			$tpl .= '<a class="wb-btn-dwqr wb-btn-like j-dwqr-like-btn" data-count="' . $like_count . '" rel="nofollow"><svg class="wb-icon wbsico-like"><use xlink:href="#wbsico-magicpost-like"></use></svg><span class="like-count">赞' . ($like_count ? '(' . $like_count . ')' : '') . '</span></a>';
		}

		if (isset($selected_module['poster']) && $selected_module['poster']) {
			$tpl .= '<a class="wb-btn-dwqr wb-share-poster j-dwqr-poster-btn" rel="nofollow"><svg class="wb-icon wbsico-poster"><use xlink:href="#wbsico-magicpost-poster"></use></svg><span>微海报</span></a>';
		}

		if (isset($selected_module['share']) && $selected_module['share']) {
			$tpl .= '<a class="wb-btn-dwqr wb-btn-share j-dwqr-social-btn" rel="nofollow"><svg class="wb-icon wbsico-share"><use xlink:href="#wbsico-magicpost-share"></use></svg><span>分享</span></a>';
		}

		$tpl .= '</div></div>';

		return $tpl;
	}

	/**
	 * 分享选项html
	 * @return string
	 */
	function get_share_html($post_id)
	{
		$share_items = self::opt('share_ways');

		if (has_excerpt($post_id)) {
			$excerpt = rtrim(trim(strip_tags(get_the_excerpt($post_id))), __('[原文链接]', WB_MAGICPOST_TD));
		} else {
			$excerpt = mb_strimwidth(strip_tags(get_the_content(null, null, $post_id)), 0, 120, "...", 'utf-8');
		}


		$excerpt = preg_replace('/\\s+/', ' ', $excerpt);
		$thumbnail_url = self::wbolt_post_thumbnail_url($post_id);
		$title = get_the_title($post_id);

		$wb_dwqr_share_html = '<div class="wb-share-list">';
		foreach ($share_items as $key) {
			$item = self::get_share_api_cnf($key);
			$url_format = $item['format'];
			$post_url = rawurlencode(wp_get_shortlink($post_id));
			$share_url = sprintf($url_format, $post_url, rawurlencode($title), rawurlencode($thumbnail_url), rawurlencode($excerpt));
			$wb_dwqr_share_html .= '<a class="share-logo-magicpost icon-' . $key . '" data-cmd="' . $key . '" data-url="' . $share_url . '" title="分享到' . esc_attr($item['label']) . '" rel="nofollow">
			<svg class="share-magicpost-' . $key . '"><use xlink:href="#share-magicpost-' . $key . '"></use></svg></a>';
		}

		$wb_dwqr_share_html .= '</div>';

		return $wb_dwqr_share_html;
	}

	/**
	 * 打赏选项html
	 * @return array|int
	 */
	function get_donate_html()
	{
		$donate_items = self::opt('items');
		$index = 0;
		$tab_html = '';
		$cont_html = '';
		foreach ($donate_items as $k => $v) {
			if (empty($v['img'])) continue;
			$tab_html .= '<div class="tab-nav-item item-' . $k . ($index == 0 ? ' current' : '') . '" data-index="' . $index . '"><span>' . esc_html($v['name']) . '</span></div>';
			$cont_html .= '<div class="tab-cont' . ($index == 0 ? ' current' : '') . '"><div class="pic"><img src="' . esc_attr($v['img']) . '" alt="' . esc_attr($v['name']) . '二维码图片"></div><p>用<span class="hl">' . esc_html($v['name']) . '</span>扫描二维码打赏</p></div>';
			$index++;
		}
		if ($index < 2) $tab_html = '';
		if ($index == 0) $selected_module['donate'] = '';

		return '<div class="tab-navs">' . $tab_html . '</div><div class="tab-conts">' . $cont_html . '</div>';
	}

	/**
	 * 获取缩略图url
	 */
	function wbolt_post_thumbnail_url($post_id = null, $size = 'large')
	{
		$url = esc_attr(self::opt('cover_url'));
		$def_cover   = MAGICPOST_URI . 'assets/img/def_cover.png';
		$url = $url ? $url : $def_cover;

		if (has_post_thumbnail($post_id) && get_the_post_thumbnail($post_id) !== '') {
			$url = get_the_post_thumbnail_url($post_id, $size);
		} elseif ($img = self::wbolt_catch_first_image($post_id, $size)) {
			$url = $img;
		}

		$url = apply_filters('wb_post_thumbnail_url', $url, $post_id, $size);
		return $url;
	}

	/**
	 * 抽取文章第一张图
	 * @param null $post_id
	 * @param string $size
	 *
	 * @return array
	 */
	function wbolt_catch_first_image($post_id = null, $size = 'large')
	{
		$post = get_post($post_id);
		$first_img_src = '';

		if (preg_match_all('#<img[^>]+>#is', $post->post_content, $match)) {

			$match_frist = $match[0][0];


			if ($match_frist) :
				preg_match('#src=[\'"]([^\'"]+)[\'"]#', $match_frist, $src);
				preg_match('#wp-image-(\d+)#', $match_frist, $img_wp);

				$first_img_thumbnail = $img_wp ? wp_get_attachment_image_src($img_wp[1], $size) : '';

				if ($first_img_thumbnail) {
					$first_img_src = $first_img_thumbnail[0];
				} else {
					$first_img_src = $src ? $src[1] : '';
				}

			endif;
		}

		return $first_img_src;
	}


	public function wb_image_to_base64($image)
	{
		$site_domain = wp_parse_url(get_bloginfo('url'), PHP_URL_HOST);
		$img_domain = wp_parse_url($image, PHP_URL_HOST);
		if ($img_domain != $site_domain) {
			$http_options = array(
				'httpversion' => '1.0',
				'timeout' => 20,
				'redirection' => 20,
				'sslverify' => FALSE,
				'user-agent' => 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0; MALC)'
			);
			if (preg_match('/^\/\//i', $image)) $image = 'http:' . $image;
			$get = wp_remote_get($image, $http_options);
			if (!is_wp_error($get) && 200 === $get['response']['code']) {
				$img_base64 = 'data:' . $get['headers']['content-type'] . ';base64,' . base64_encode($get['body']);
				return $img_base64;
			}
		}
		$image = preg_replace('/^(http:|https:)/i', '', $image);
		return $image;
	}

	/**
	 * e.g. [wb_share_social items="donate,like,poster,share" wpclass="widget-social"]
	 * items 添加显示组件名称
	 * wpclass 外层DOM的类名（免于与文章详情底下的冲突）
	 */
	public function wb_share_social_handler($attr = array())
	{
		$set_attr = array();

		if (!empty($attr)) {
			if (isset($attr['items'])) {
				$get_items = explode(',', $attr['items']);
				$set_attr['selected_module'] = [];
				foreach ($get_items as $item) {
					$set_attr['selected_module'][$item] = 1;
				}
			}

			$set_attr['wpclass'] = isset($attr['wpclass']) ? $attr['wpclass'] : 'widget-social-dwqr';
		}
		return $this->dwqr_html($set_attr);
	}

	public static function set_like_btn()
	{

		$post_id = get_the_ID();
		$like_count = get_post_meta($post_id, 'dwqr_like', true);

		$like_count = get_post_meta($post_id, 'dwqr_like', true);
		$like_count = $like_count ? intval($like_count) : 0;
		$like = $like_count > 999 ? intval($like_count / 1000) . 'k+' : $like_count;

		$liked_string = sprintf(_nx('点赞(%s)', '点赞(%s)', $like, '点赞按钮label，已有点赞显示数量', WB_MAGICPOST_TD), $like);
		$like_btn_label = $like > 0 ? $liked_string : _x('点赞', '点赞按钮label，未有点赞不显示数量', WB_MAGICPOST_TD);
		return '<div class="wb-btn-like j-dwqr-like-btn" data-post_id="' . $post_id . '" data-count="' . $like . '" >
		<svg class="wb-icon-magicpost wbsico-magicpost-like">
      <use xlink:href="#wbsico-magicpost-like"></use>
    </svg><span class="like-count">' . $like_btn_label . '</span>
				</div>';
	}

	public static function is_tabbar_active()
	{
		return get_option('wb_magicpost_ver', 0) && wp_is_mobile() && self::opt('tabbar_switch') == 1 ? true : false;
	}
}
