<?php

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Psr\Log\LogLevel;

/**
 * Implements of hook_drush_command().
 */
function multiversion_drush_command() {
  $items = [];

  $items['multiversion-uninstall'] = [
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Uninstall Multiversion.',
    'aliases' => ['mun'],
  ];

  $items['multiversion-enable-entity-types'] = [
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Enable entity types (make them multiversionable).',
    'arguments' => [
      'entity_types' => dt('The list of entity types, comma or space separated.'),
    ],
    'examples' => [
      'drush multiversion-enable-entity-types my_et' => 'Makes my_et entity type multiversionable.',
      'drush multiversion-enable-entity-types my_et my_et2' => 'Makes my_et and my_et2 entity types multiversionable.',
      'drush met my_et' => 'Makes my_et entity type multiversionable.',
      'drush met my_et my_et2' => 'Makes my_et and my_et2 entity types multiversionable.',
    ],
    'aliases' => ['met'],
  ];

  $items['multiversion-disable-entity-types'] = [
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Disable entity types (make them non-multiversionable).',
    'arguments' => [
      'entity_types' => dt('The list of entity types, comma or space separated.'),
    ],
    'examples' => [
      'drush multiversion-disable-entity-types my_et' => 'Makes my_et entity type non-multiversionable.',
      'drush multiversion-disable-entity-types my_et1 my_et2' => 'Makes my_et and my_et2 entity types non-multiversionable.',
      'drush mdt my_et' => 'Makes my_et entity type non-multiversionable.',
      'drush mdt my_et1 my_et2' => 'Makes my_et and my_et2 entity types non-multiversionable.',
    ],
    'aliases' => ['mdt'],
  ];

  return $items;
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_multiversion_uninstall() {
  $extension = 'multiversion';
  $uninstall = TRUE;
  $extension_info = drush_get_extensions();
  $required = drush_drupal_required_modules($extension_info);
  if (in_array($extension, $required)) {
    $info = $extension_info[$extension]->info;
    $explanation = !empty($info['explanation']) ? ' ' . dt('Reason: !explanation.', ['!explanation' => strip_tags($info['explanation'])])  : '';
    drush_log(dt('!extension is a required extension and can\'t be uninstalled.', ['!extension' => $extension]) . $explanation, LogLevel::INFO);
    $uninstall = FALSE;
  }
  elseif (!$extension_info[$extension]->status) {
    drush_log(dt('!extension is already uninstalled.', ['!extension' => $extension]), LogLevel::INFO);
    $uninstall = FALSE;
  }
  elseif (drush_extension_get_type($extension_info[$extension]) == 'module') {
    $dependents = [];
    foreach (drush_module_dependents([$extension], $extension_info) as $dependent) {
      if (!in_array($dependent, $required) && ($extension_info[$dependent]->status)) {
        $dependents[] = $dependent;
      }
    }
    if (count($dependents)) {
      drush_log(dt('To uninstall !extension, the following extensions must be uninstalled first: !required', ['!extension' => $extension, '!required' => implode(', ', $dependents)]), LogLevel::ERROR);
      $uninstall = FALSE;
    }
  }

  if ($uninstall) {
    drush_print(dt('Multiversion will be uninstalled.'));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }

    drush_log('*** ' . dt('The uninstall process can take a few minutes, it depends by the number of entities on the site. Please be patient.'), LogLevel::WARNING);

    try {
      \Drupal::service('multiversion.manager')->disableEntityTypes();
      // Delete workspace entities before uninstall.
      $storage = \Drupal::entityTypeManager()->getStorage('workspace');
      $entities = $storage->loadMultiple();
      $storage->delete($entities);
      drush_module_uninstall(['multiversion']);
    }
    catch (Exception $e) {
      drush_log($e->getMessage(), LogLevel::ERROR);
    }

    // Inform the user of final status.
    drush_log(dt('!extension was successfully uninstalled.', ['!extension' => $extension]), LogLevel::INFO);
  }

}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_multiversion_enable_entity_types() {
  $args = pm_parse_arguments(func_get_args());
  if (empty($args)) {
    drush_log(dt('Entity types list argument is missing.'), LogLevel::ERROR);
  }
  elseif ($entity_types = _drush_multiversion_get_entity_types($args)) {
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
    try {
      $multiversion_settings = \Drupal::configFactory()->getEditable('multiversion.settings');
      $supported_entity_types = $multiversion_settings->get('supported_entity_types') ?: [];
      foreach (array_keys($entity_types) as $id) {
        if (!in_array($id, $supported_entity_types)) {
          $supported_entity_types[] = $id;
        }
      }
      // Add new entity types to the supported entity types list.
      $multiversion_settings
        ->set('supported_entity_types', $supported_entity_types)
        ->save();
      \Drupal::service('multiversion.manager')->enableEntityTypes($entity_types);
    }
    catch (Exception $e) {
      drush_log($e->getMessage(), LogLevel::ERROR);
    }
  }
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_multiversion_disable_entity_types() {
  $args = pm_parse_arguments(func_get_args());
  if (empty($args)) {
    $args = \Drupal::config('multiversion.settings')->get('enabled_entity_types') ?: [];
    if (!drush_confirm(dt('Do you really want to disable all enabled entity types (!entity_types)?', [
      '!entity_types' => implode(', ', array_keys(_drush_multiversion_get_entity_types($args))),
    ]))) {
      return drush_user_abort();
    }
  }
  else {
    if (!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
  }
  if (!empty($args) && $entity_types = _drush_multiversion_get_entity_types($args)) {
    try {
      \Drupal::service('multiversion.manager')->disableEntityTypes($entity_types);
    }
    catch (Exception $e) {
      drush_log($e->getMessage(), LogLevel::ERROR);
    }
  }
}

function _drush_multiversion_get_entity_types($entity_type_ids) {
  $entity_types = [];
  $manager = \Drupal::entityTypeManager();
  foreach ($entity_type_ids as $id) {
    try {
      $entity_type = $manager->getStorage($id)->getEntityType();
      if ($entity_type instanceof ContentEntityTypeInterface) {
        $entity_types[$id] = $entity_type;
      }
    }
    catch (Exception $e) {
      drush_log($e->getMessage(), LogLevel::INFO);
    }
  }

  return $entity_types;
}
