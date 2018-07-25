<?php

namespace Drupal\multiversion\Entity\Storage\Sql;

interface MultiversionStorageSchemaConverterFactoryInterface {

  /**
   * Returns an MultiversionStorageSchemaConverter object.
   *
   * @param $entity_type_id
   *
   * @return \Drupal\multiversion\Entity\Storage\Sql\MultiversionStorageSchemaConverter
   */
  public function getStorageSchemaConverter($entity_type_id);

}
