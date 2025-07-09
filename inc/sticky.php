<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

?>
<?php if ($sticky_mode > 0) : ?>
  <div class="wb-sticky-bar<?php if ($sticky_mode == 2) echo ' at-bottom'; ?>" id="J_downloadBar">
    <div class="wbsb-inner pw">
      <div class="sb-title">
        <?php echo esc_html(get_the_title($post_id)); ?>
      </div>

      <div class="ctrl-box">
        <a class="wb-btn wb-btn-outlined wb-btn-download" href="#J_MPDLCont">
          <svg class="wb-icon-magicpost wbsico-magicpost-download">
            <use xlink:href="#wbsico-magicpost-download"></use>
          </svg>
          <span><?php _ex('去下载', 'front', WB_MAGICPOST_TD); ?></span></a>
      </div>
    </div>
  </div>
<?php endif; ?>