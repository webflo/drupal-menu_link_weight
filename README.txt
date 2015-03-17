MENU LINK WEIGHT
================

This module replaces the standard numeric weight dropdown widget for menu links
in the node form provided by Drupal Core with a tabledrag widget.

Upon selection of a parent, a tabledrag widget will be loaded via AJAX that
will allow us to reorder the weight of the menu link for the node we are editing
relative to its sibling links. The sibling links themselves can also be
reordered.

Upon node submission, the weights will be internally reordered from -50 to -49,
to -48 etc. With "row weights" hidden, content editors can now ignore the
numerical weight values and instead see the position of a menu link relative to
other links in the tabledrag widget itself.

This module includes support for the Hierarchical Select module.
