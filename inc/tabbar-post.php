<?php

/**
 * The template part for displaying tabbar of blog
 *
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */
$post_id = get_the_ID();
$is_toc_active = WB_MagicPost_Toc::opt('toc_switch');
$tabbar_items = [
  [
    'label' => _x('微海报', 'tabbar label', WB_MAGICPOST_TD),
    'slug' => 'poster',
    'icon' => 'tsi-magicpost-poster',
    'class' => 'wb-act-poster j-dwqr-poster-btn',
    'attrs' => ['data-id' => $post_id, 'data-theme' => '0']
  ],
  [
    'label' => _x('分享', 'tabbar label', WB_MAGICPOST_TD),
    'slug' => 'share',
    'icon' => 'tsi-magicpost-share',
    'class' => 'wb-act-share wb-act-share-nvt j-dwqr-social-btn',
    'attrs' => []
  ],
];
if ($is_toc_active) {
  array_unshift($tabbar_items, [
    'label' => _x('目录', 'tabbar label', WB_MAGICPOST_TD),
    'slug' => 'toc',
    'icon' => 'tsi-magicpost-toc',
    'class' => 'wb-act-toc',
    'attrs' => ['data-wbats-target' => '.wb-ats-toc']
  ]);
}

$style_ouput = '';

$style_ouput .= count($tabbar_items) != 4 ? '--wb-tabbar-item-count:' . count($tabbar_items) . '; ' : '';
if ($style_ouput) {
  $style_ouput = ' style="' . $style_ouput . '"';
}
?>
<nav class="wb-tabbar with-primary" <?php echo $style_ouput; ?>>
  <div class="wb-tabbar-primary">
    <?php echo WB_MagicPost_Share::set_like_btn(); ?>
  </div>
  <div class="wb-tabbar-items">

    <?php foreach ($tabbar_items as $item) :
      $html_tag = isset($item['attrs']['href']) && $item['attrs']['href'] ? 'a' : 'div';
      $attrs = ' ';

      if (isset($item['attrs']) && is_array($item['attrs'])) {
        foreach ($item['attrs'] as $key => $val) {
          $attrs .= $key . '="' . $val . '" ';
        }
      }

      $css_class = $item['class'] ?? '';
    ?>
      <<?php echo $html_tag . ' class="wb-tabbar-item ' . $css_class . '"' . $attrs; ?>>
        <svg class="wb-tabbar-icon">
          <use xlink:href="#<?php echo $item['icon']; ?>"></use>
        </svg>
        <strong class="wb-tabbar-label">
          <?php echo $item['label']; ?>
        </strong>
      </<?php echo $html_tag; ?>>
    <?php endforeach; ?>
  </div>
</nav>

<?php
/**
 * toc
 */
if ($is_toc_active) :
  $sc_items = WB_MagicPost_Toc::get_toc_items($post_id, ['location' => 'content']);
  $toc_label = WB_MagicPost_Toc::opt('toc_label') ?: _x('内容目录', '标题', WB_MAGICPOST_TD);
  $tc_mode = WB_MagicPost_Toc::opt('ct_mode');
?>
  <div class="wb-action-sheet wb-ats-toc magicpost-toc-wp" data-wb-ct="<?php echo $tc_mode; ?>">
    <div class="was-inner">
      <div class="was-hd">
        <strong class="was-title"><?php echo $toc_label; ?></strong>
      </div>
      <div class="was-bd">
        <div class="wb-cells cells-toc">
          <?php foreach ($sc_items as $item) : ?>
            <div class="wb-cell-item">
              <?php echo $item; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="was-close"><i class="i"></i></div>
    </div>
    <div class="was-mask"></div>
  </div>
<?php endif; ?>