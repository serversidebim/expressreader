<?php

/**
 *  Corresponding Class to test YourClass class
 *
 *  For each class in your library, there should be a corresponding Unit-Test for it
 *  Unit-Tests should be as much as possible independent from other test going on.
 *
 *  @author yourname
 */
class ReaderTest extends PHPUnit_Framework_TestCase {

    protected static $reader;

    public static function setupBeforeClass() {
        $reader = new Serversidebim\ExpressReader\Reader;
        $reader = new Serversidebim\ExpressReader\Reader;
        $reader->parseExpress(__DIR__ . '/IFC4.exp');
        self::$reader = $reader;
    }

    /**
     * Just check if the YourClass has no syntax error 
     *
     * This is just a simple check to make sure your library has no syntax error. This helps you troubleshoot
     * any typo before you even use this library in a real project.
     *
     */
    public function testIsThereAnySyntaxError() {
        $var = new Serversidebim\ExpressReader\Reader;
        $this->assertTrue(is_object($var));
        unset($var);
    }

    public function testParseExpress() {

        $reader = self::$reader;

        $this->assertTrue(is_object($reader));

        $this->assertEquals($reader->getSchema(), "IFC4");
    }

    public function testTypes() {

        $reader = self::$reader;


        // Check the types
        $this->assertCount(398, $reader->getTypes());

        // Check some types
        $type = $reader->getTypes()[strtoupper('IfcDuctSegmentTypeEnum')];
        $this->assertEquals($type->type, 'ENUMERATION');
        $this->assertContains('USERDEFINED', $type->values);

        //TYPE IfcCompoundPlaneAngleMeasure = LIST [3:4] OF INTEGER;
        $type = $reader->getTypes()[strtoupper('IfcCompoundPlaneAngleMeasure')];
        $this->assertEquals($type->type, 'LIST');
        $this->assertEquals($type->of, 'INTEGER');
        $this->assertEquals($type->min, 3);
        $this->assertEquals($type->max, 4);
        $this->assertGreaterThan(0, count($type->where));
    }

    public function testEntities() {
        $reader = self::$reader;
        
        $this->assertGreaterThan(0,count($reader->getEntities()));
        
        $ifcwall = $reader->getEntity('IfcWall');
        $ifcbuildingelement = $reader->getEntity('IfcBuildingElement');
        $ifcroot = $reader->getEntity('IfcRoot');
        $IfcCostValue = $reader->getEntity('IfcCostValue');
        $ifcproduct = $reader->getEntity('IfcProduct');
        $ifcproductFull = $reader->getFullEntity('ifcproduct');
                
        $this->assertNotNull($ifcwall);
        $this->assertNotNull($ifcroot);
        
        $this->assertTrue($reader->isDirectSupertype($ifcwall, $ifcbuildingelement));
        $this->assertTrue($reader->isSubTypeOf($ifcwall, $ifcroot));
        $this->assertFalse($reader->isSubTypeOf($IfcCostValue,$ifcroot));
        
        $this->assertEquals(2, count($ifcproduct->parameters));
        $this->assertEquals(8, count($reader->getSubtypesOf($ifcproduct)));
        
        $this->assertEquals('IFCROOT.IFCOBJECTDEFINITION.IFCOBJECT.IFCPRODUCT', strtoupper($ifcproductFull->name));
       
    }

}
