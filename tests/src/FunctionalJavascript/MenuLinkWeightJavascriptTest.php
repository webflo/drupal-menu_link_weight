<?php

namespace Drupal\Tests\menu_link_weight\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests the functionality of the Menu Link Weight module.
 *
 * @group menu_link_weight
 */
class MenuLinkWeightJavascriptTest extends JavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'menu_link_weight'];

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The content menu link storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $contentMenuLinkStorage;

  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The node type used in this test.
   *
   * @var string
   */
  protected $nodeType = 'page';

  /**
   * Set up.
   */
  protected function setUp() {
    parent::setUp();

    $this->menuLinkTree = $this->container->get('menu.link_tree');
    $this->menuLinkManager = $this->container->get('plugin.manager.menu.link');
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->contentMenuLinkStorage = $entity_type_manager->getStorage('menu_link_content');
    $this->moduleInstaller = $this->container->get('module_installer');
    $this->state = $this->container->get('state');

    $this->drupalPlaceBlock('system_menu_block:tools');

    NodeType::create([
      'type' => $this->nodeType,
      'name' => $this->nodeType,
      'third_party_settings' => [
        'menu_ui' => [
          // Enable the Navigation menu as available menu.
          'available_menus' => ['tools'],
          // Change default parent item to Navigation menu, so we can assert
          // more easily.
          'parent' => 'tools:',
        ]
      ]
    ])->save();

    $permissions = array(
      'administer menu',
      "create {$this->nodeType} content",
      "edit own {$this->nodeType} content",
    );

    // Create user.
    $user = $this->drupalCreateUser($permissions);
    // Log in user.
    $this->drupalLogin($user);
  }

  /**
   * Test creating, editing, deleting menu links via node form widget.
   */
  public function testMenuFunctionality() {
    // Create a node.
    $node1_title = $this->randomMachineName();
    $edit = array(
      'title[0][value]' => $node1_title,
    );
    $this->drupalPostForm("node/add/{$this->nodeType}", $edit, t('Save'));
    // Assert that there is no link for the node.
    $this->drupalGet('');
    $this->assertSession()->linkNotExists($node1_title);

    // Edit the node, enable the menu link setting, but skip the link title.
    $node1_edit_url = $this->drupalGetNodeByTitle($node1_title)->toUrl('edit-form');
    $edit = array(
      'menu[enabled]' => 1,
      'menu[title]' => '',
    );
    $this->drupalPostForm($node1_edit_url, $edit, 'Save');
    // Assert that there is no link for the node.
    $this->drupalGet('');
    $this->assertSession()->linkNotExists($node1_title);

    // Edit the node and create a menu link.
    $this->drupalGet($node1_edit_url);
    // Test the hidden fields validation. This should pass:
    $this->assertSession()->hiddenFieldExists('menu[db_weights][node.add_page]')->setValue(-49);
    $this->assertSession()->hiddenFieldExists('menu[db_weights][filter.tips_all]')->setValue(-48);
    $edit = array(
      'menu[enabled]' => TRUE,
      'menu[title]' => $node1_title,
      'menu[menu_link_weight][node.add_page][weight]' => '-50',
      'menu[menu_link_weight][link_current][weight]' => '-49',
      'menu[menu_link_weight][filter.tips_all][weight]' => '-48',
    );
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertSession()->pageTextNotContains('The menu link weights have been changed by another user, please try again.');
    // Assert that the link exists.
    $this->drupalGet('');
    $this->assertSession()->linkExists($node1_title);

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $links */
    $links = $this->contentMenuLinkStorage->loadByProperties(['title' => $node1_title]);
    $link1_id = reset($links)->getPluginId();

    // Assert that the reordering was successful.
    $this->assertLinkWeight('node.add_page', -50);
    $this->assertLinkWeight($link1_id, -49);
    $this->assertLinkWeight('filter.tips_all', -48);

    $this->drupalGet($node1_edit_url);
    $this->assertSession()->pageTextContains('(provided menu link)');
    $this->assertSession()->pageTextContains('Change the weight of the links within the Tools menu');
    $option = $this->assertSession()->optionExists('edit-menu-menu-link-weight-link-current-weight', -49);
    $this->assertTrue($option->hasAttribute('selected'));

    // Test Ajax functionality.
    $select_xpath = $this->cssSelectToXpath('[data-drupal-selector="edit-menu-menu-parent"]');
    $this->getSession()->getDriver()->selectOption($select_xpath, 'tools:node.add_page');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Change the weight of the links within the Add content menu');

    // Test the "add new node" form.
    // Create a node.
    $node2_title = $this->randomMachineName();
    $edit = array(
      'title[0][value]' => $node2_title,
      'menu[enabled]' => TRUE,
      'menu[title]' => $node2_title,
      'menu[menu_link_weight][' . $link1_id . '][weight]' => -50,
      'menu[menu_link_weight][filter.tips_all][weight]' => -49,
      'menu[menu_link_weight][link_current][weight]' => -48,
      'menu[menu_link_weight][node.add_page][weight]' => -47,
    );
    $this->drupalPostForm("node/add/{$this->nodeType}", $edit, t('Save'));
    // Assert that the link exists.
    $this->drupalGet('');
    $this->assertSession()->linkExists($node2_title);

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $links */
    $links = $this->contentMenuLinkStorage->loadByProperties(['title' => $node2_title]);
    $link2_id = reset($links)->getPluginId();

    // Assert that the reordering was successful.
    $this->assertLinkWeight($link1_id, -50);
    $this->assertLinkWeight('filter.tips_all', -49);
    $this->assertLinkWeight($link2_id, -48);
    $this->assertLinkWeight('node.add_page', -47);

    $node2_edit_url = $this->drupalGetNodeByTitle($node2_title)->toUrl('edit-form');

    $this->drupalGet($node2_edit_url);
    $this->assertSession()->pageTextContains('(provided menu link)');
    $option = $this->assertSession()->optionExists('edit-menu-menu-link-weight-link-current-weight', -48, 'Menu weight correct in edit form');
    $this->assertTrue($option->hasAttribute('selected'));

    // Assert that the item is placed on top of the list if no other options
    // are selected.
    $node3_title = $this->randomMachineName();
    $edit = array(
      'title[0][value]' => $node3_title,
      'menu[enabled]' => TRUE,
      'menu[title]' => $node3_title,
    );
    $this->drupalPostForm("node/add/{$this->nodeType}", $edit, t('Save'));

    // Assert that the link exists.
    $this->drupalGet('');
    $this->assertSession()->linkExists($node3_title);

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $links */
    $links = $this->contentMenuLinkStorage->loadByProperties(['title' => $node3_title]);
    $link3_id = reset($links)->getPluginId();

    // Assert that the reordering was successful.
    $this->assertLinkWeight($link3_id, -50);
    $this->assertLinkWeight($link1_id, -49);
    $this->assertLinkWeight('filter.tips_all', -48);
    $this->assertLinkWeight($link2_id, -47);
    $this->assertLinkWeight('node.add_page', -46);

    // Test the custom tree reordering functionality:
    $this->moduleInstaller->install(array('menu_link_weight_test'));
    // Insert the new link above item 2:
    $this->state->set('menu_link_weight_test_parent_value', 'navigation:0');
    $this->state->set('menu_link_weight_test_relative_position', 'above_' . $link2_id);
    $node4_title = $this->randomMachineName();
    $edit = array(
      'title[0][value]' => $node4_title,
      'menu[enabled]' => TRUE,
      'menu[title]' => $node4_title,
    );

    $this->drupalPostForm("node/add/{$this->nodeType}", $edit, t('Save'));

    // Assert that the link exists.
    $this->drupalGet('');
    $this->assertSession()->linkExists($node4_title);

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $links */
    $links = $this->contentMenuLinkStorage->loadByProperties(['title' => $node4_title]);
    $link4_id = reset($links)->getPluginId();

    $this->assertLinkWeight($link3_id, -50);
    $this->assertLinkWeight($link1_id, -49);
    $this->assertLinkWeight('filter.tips_all', -48);
    $this->assertLinkWeight($link4_id, -47);
    $this->assertLinkWeight($link2_id, -46);
    $this->assertLinkWeight('node.add_page', -45);

    $this->state->set('menu_link_weight_test_relative_position', 'below_' . $link2_id);
    $node5_title = $this->randomMachineName();
    $edit = array(
      'title[0][value]' => $node5_title,
      'menu[enabled]' => 1,
      'menu[title]' => $node5_title,
    );

    $this->drupalPostForm("node/add/{$this->nodeType}", $edit, t('Save'));

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $links */
    $links = $this->contentMenuLinkStorage->loadByProperties(['title' => $node5_title]);
    $link5_id = reset($links)->getPluginId();

    // Assert that the reordering was successful.
    $this->assertLinkWeight($link3_id, -50);
    $this->assertLinkWeight($link1_id, -49);
    $this->assertLinkWeight('filter.tips_all', -48);
    $this->assertLinkWeight($link4_id, -47);
    $this->assertLinkWeight($link2_id, -46);
    $this->assertLinkWeight($link5_id, -45);
    $this->assertLinkWeight('node.add_page', -44);
  }

  /**
   * Asserts the weight of a menu link.
   *
   * @param string $link_id
   *   The plugin ID of the link.
   * @param int $expected_weight
   *   The expected weight of the link.
   */
  protected function assertLinkWeight($link_id, $expected_weight) {
    // Retrieve the menu link.
    /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
    $link = $this->menuLinkManager->createInstance($link_id);
    $this->assertEquals($expected_weight, $link->getWeight());
  }

}
