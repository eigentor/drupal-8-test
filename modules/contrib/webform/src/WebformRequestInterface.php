<?php

namespace Drupal\webform;

use Drupal\Core\Entity\EntityInterface;

/**
 * Helper class webform entity methods.
 */
/**
 * Provides an interface defining a webform request handler.
 */
interface WebformRequestInterface {

  /**
   * Get the current request's source entity.
   *
   * @param string|array $ignored_types
   *   (optional) Array of ignore entity types.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The current request's source entity.
   */
  public function getCurrentSourceEntity($ignored_types = NULL);

  /**
   * Get webform associated with the current request.
   *
   * @return \Drupal\webform\WebformInterface|null
   *   The current request's webform.
   */
  public function getCurrentWebform();

  /**
   * Get the webform and source entity for the current request.
   *
   * @return array
   *   An array containing the webform and source entity for the current
   *   request.
   */
  public function getWebformEntities();

  /**
   * Get the webform submission and source entity for the current request.
   *
   * @return array
   *   An array containing the webform and source entity for the current
   *   request.
   */
  public function getWebformSubmissionEntities();

  /**
   * Get the route name for a form/submission and source entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $webform_entity
   *   A webform or webform submission.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   A webform submission's source entity.
   * @param string $route_name
   *   The route name.
   *
   * @return string
   *   A route name prefixed with 'entity.{entity_type_id}'
   *   or just 'entity'.
   */
  public function getRouteName(EntityInterface $webform_entity, EntityInterface $source_entity = NULL, $route_name);

  /**
   * Get the route parameters for a form/submission and source entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $webform_entity
   *   A webform or webform submission.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   A webform submission's source entity.
   *
   * @return array
   *   An array of route parameters.
   */
  public function getRouteParameters(EntityInterface $webform_entity, EntityInterface $source_entity = NULL);

  /**
   * Get the base route name for a form/submission and source entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $webform_entity
   *   A webform or webform submission.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   A webform submission's source entity.
   *
   * @return string
   *   If the source entity has a webform attached, 'entity.{entity_type_id}'
   *   or just 'entity'.
   */
  public function getBaseRouteName(EntityInterface $webform_entity, EntityInterface $source_entity = NULL);

  /**
   * Check if a source entity is attached to a webform.
   *
   * @param \Drupal\Core\Entity\EntityInterface $webform_entity
   *   A webform or webform submission.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   A webform submission's source entity.
   *
   * @return bool
   *   TRUE if a webform is attached to a webform submission source entity.
   */
  public function isValidSourceEntity(EntityInterface $webform_entity, EntityInterface $source_entity = NULL);

  /**
   * Get the source entity's webform field name.
   *
   * @param EntityInterface $source_entity
   *   A webform submission's source entity.
   *
   * @return string
   *   The name of the webform field, or an empty string.
   */
  public function getSourceEntityWebformFieldName(EntityInterface $source_entity);

}
