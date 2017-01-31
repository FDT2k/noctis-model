<?php
namespace GKA\Noctis\Model\Scaffolding;
use \FDT2k\Helpers\String as String;
use \FDT2k\Noctis\Core\Env as Env;

class EntityRelation extends \IObject {

  function initFromDef($array){

    $this->setField($array['field']);

    $this->setName($array['name']);
    $this->setRefField($array['ref_field']);
    $this->setRefTable($array['ref_table']);

    return $this;
  }
}
