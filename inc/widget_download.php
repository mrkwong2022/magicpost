<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

?>

<section class="widget widget-wbdl" id="J_widgetWBDownload">
  <h3 class="widgettitle"><?php _ex('下载', 'widget', WB_MAGICPOST_TD); ?></h3>
  <div class="widget-main">
    <?php include MAGICPOST_ROOT . '/tpl/download_btn.php'; ?>

    <?php if ($display_count) :
      $count_text = '<span class="j-wbdl-count">' . esc_html($post_down) . '</span>';
    ?>
      <p class="dl-count"><?php printf(_x('已下载%s次', 'widget, %s下载次数', WB_MAGICPOST_TD), $count_text); ?></p>
    <?php endif; ?>
  </div>
</section>