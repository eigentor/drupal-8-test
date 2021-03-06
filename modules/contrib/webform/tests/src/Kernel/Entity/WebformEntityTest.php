<?php

namespace Drupal\Tests\user\Kernel\Entity;

use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\webform\Entity\Webform;

/**
 * Tests the webform entity class.
 *
 * @group webform
 * @see \Drupal\webform\Entity\Webform
 */
class WebformEntityTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['system', 'path', 'webform', 'user', 'field'];

  /**
   * Tests some of the methods.
   */
  public function testWebformMethods() {
    $this->installConfig('webform');

    // Create webform.
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = Webform::create(['id' => 'webform_test']);
    $webform->save();
    $this->assertEquals('webform_test', $webform->id());
    $this->assertFalse($webform->isTemplate());
    $this->assertTrue($webform->isOpen());

    // Check that templates are always closed.
    $webform->set('template', TRUE)->save();
    $this->assertTrue($webform->isTemplate());
    $this->assertFalse($webform->isOpen());

    // Set elements.
    $elements = [
      'root' => [
        '#type' => 'textfield',
        '#title' => 'root'
      ],
      'container' => [
        '#type' => 'container',
        '#title' => 'container',
        'child' => [
          '#type' => 'textfield',
          '#title' => 'child',
        ]
      ],
    ];
    $webform->setElements($elements);

    // Check that elements are serialized to YAML.
    $this->assertTrue($webform->getElementsRaw(), Yaml::encode($elements));

    // Check elements decoded and flattened.
    $flattened_elements = [
      'root' => [
        '#type' => 'textfield',
        '#title' => 'root'
      ],
      'container' => [
        '#type' => 'container',
        '#title' => 'container',
      ],
      'child' => [
        '#type' => 'textfield',
        '#title' => 'child',
      ],
    ];
    $this->assertEquals($webform->getElementsDecodedAndFlattened(), $flattened_elements);

    // Check elements initialized  and flattened.
    $elements_initialized_and_flattened = array(
      'root' => array(
        '#type' => 'textfield',
        '#title' => 'root',
        '#webform_id' => 'webform_test--root',
        '#webform_key' => 'root',
        '#webform_parent_key' => '',
        '#webform_parent_flexbox' => FALSE,
        '#webform_depth' => 0,
        '#webform_children' => array(),
        '#webform_multiple' => FALSE,
        '#webform_composite' => FALSE,
        '#admin_title' => NULL,
      ),
      'container' => array(
        '#type' => 'container',
        '#title' => 'container',
        '#webform_id' => 'webform_test--container',
        '#webform_key' => 'container',
        '#webform_parent_key' => '',
        '#webform_parent_flexbox' => FALSE,
        '#webform_depth' => 0,
        '#webform_children' => array(),
        '#webform_multiple' => FALSE,
        '#webform_composite' => FALSE,
        '#admin_title' => NULL,
      ),
      'child' => array(
        '#type' => 'textfield',
        '#title' => 'child',
        '#webform_id' => 'webform_test--child',
        '#webform_key' => 'child',
        '#webform_parent_key' => 'container',
        '#webform_parent_flexbox' => FALSE,
        '#webform_depth' => 1,
        '#webform_children' => array(),
        '#webform_multiple' => FALSE,
        '#webform_composite' => FALSE,
        '#admin_title' => NULL,
      ),
    );
    $this->assertEquals($webform->getElementsInitializedAndFlattened(), $elements_initialized_and_flattened);

    // Check elements flattened has value.
    $elements_initialized_flattened_and_has_value = $elements_initialized_and_flattened;
    unset($elements_initialized_flattened_and_has_value['container']);
    $this->assertEquals($webform->getElementsInitializedFlattenedAndHasValue(), $elements_initialized_flattened_and_has_value);

    // Check invalid elements.
    $webform->set('elements', 'invalid')->save();
    $this->assertFalse($webform->getElementsInitialized());

    // Check get wizard pages.
    $wizard_elements = [
      'page_1' => ['#type' => 'wizard_page', '#title' => 'Page 1'],
      'page_2' => ['#type' => 'wizard_page', '#title' => 'Page 2'],
      'page_3' => ['#type' => 'wizard_page', '#title' => 'Page 3'],
    ];
    $webform->set('elements', $wizard_elements)->save();
    $wizard_pages = [
      'page_1' => ['#title' => 'Page 1'],
      'page_2' => ['#title' => 'Page 2'],
      'page_3' => ['#title' => 'Page 3'],
      'complete' => ['#title' => 'Complete']
    ];
    $this->assertEquals($webform->getPages(), $wizard_pages);

    // @todo Add the below assertions.
    // Check access rules.
    // Check get submission form.
    // Check handlers CRUD operations.
  }

  /**
   * Test paths.
   */
  function testPaths() {
    $this->installConfig('webform');

    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = Webform::create(['id' => 'webform_test']);
    $webform->save();

    $aliases = db_query('SELECT source, alias FROM {url_alias}')->fetchAllKeyed();
    $this->assertEquals($aliases['/webform/webform_test'], '/form/webform-test');
    $this->assertEquals($aliases['/webform/webform_test/confirmation'], '/form/webform-test/confirmation');
    $this->assertEquals($aliases['/webform/webform_test/submissions'], '/form/webform-test/submissions');
  }

  /**
   * Test elements CRUD operations.
   */
  function testElementsCrud() {
    $this->installEntitySchema('webform_submission');

    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = Webform::create(['id' => 'webform_test']);
    $webform->save();

    // Check set new root element.
    $elements = [
      'root' => [
        '#type' => 'container',
        '#title' => 'root',
      ]
    ];
    $webform->setElementProperties('root', $elements['root']);
    $this->assertEquals($webform->getElementsRaw(), Yaml::encode($elements));

    // Check add new container to root.
    $elements['root']['container'] = [
      '#type' => 'container',
      '#title' => 'container',
    ];
    $webform->setElementProperties('container', $elements['root']['container'], 'root');
    $this->assertEquals($webform->getElementsRaw(), Yaml::encode($elements));

    // Check add new element to container.
    $elements['root']['container']['element'] = [
      '#type' => 'textfield',
      '#title' => 'element',
    ];
    $webform->setElementProperties('element', $elements['root']['container']['element'], 'container');
    $this->assertEquals($webform->getElementsRaw(), Yaml::encode($elements));

    // Check delete container with al recursively delete all children.
    $elements = [
      'root' => [
        '#type' => 'container',
        '#title' => 'root',
      ]
    ];
    $webform->deleteElement('container');
    $this->assertEquals($webform->getElementsRaw(), Yaml::encode($elements));
  }
}
