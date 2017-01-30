<?php
namespace GKA\Noctis\Model\Scaffolding;
use \FDT2k\Helpers\String as String;
use \FDT2k\Noctis\Core\Env as Env;

class EntityRelation extends \IObject {

  function initFromDef($array){

    $this->setReferencedField($array['ref_field']);
    $this->setReferencedTable($array['ref_table'])
  }
}
