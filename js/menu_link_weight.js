/**
 * @file
 * Menu Link Weight Javascript functionality.
 */

(function ($) {
  /**
   * Automatically update the current link title in the menu link weight list.
   */
  Drupal.behaviors.menuLinkSyncAutomaticTitle = {
    attach: function (context) {
      $('fieldset.menu-link-form', context).each(function () {
        var $checkbox = $('.form-item-menu-enabled input', this);
        var $link_title = $('.form-item-menu-link-title input', context);
        var $current_selection = $('.menu-link-weight-link-current', context);
        // If there is no title, take over the title of the link.
        if ($current_selection.html() == '') {
          $current_selection.html($link_title.val());
        }
        // Take over any link title change.
        $link_title.keyup(function () {
          $current_selection.html($link_title.val());
        });
      });
    }
  };

})(jQuery);
