<?php
$dir = dirname(dirname(__FILE__));
set_include_path($dir);
define('ICE_PATH','');

define('ICE_ROOT',__DIR__);
require ($dir."/vendor/autoload.php");
require ($dir."/src/AbstractModel.php");
require ($dir."/src/Database.php");
require ($dir."/src/Scaffolding/Entity.php");

require ($dir."/src/Scaffolding/EntityField.php");

require ($dir."/src/Scaffolding/SQLGenerator.php");

class TestModel extends \GKA\Noctis\Model\Database{

}

class EntityTest extends PHPUnit_Framework_TestCase
{
    // ...
    protected function setUp()
   {
     global $argv, $argc;

      \FDT2k\Noctis\Core\Env::preinit($argv);
      \FDT2k\Noctis\Core\Env::init($argv);
   }
    public function getRenderer(){
      return new TestModel();
    }

    public function testBasics(){
      /*global $argv, $argc;
      $this->assertGreaterThan(2, $argc, 'No environment name passed');
      $environment = $argv[2];
*/
        $model = $this->getRenderer();
    }



}
