<?php

/**
 *  Corresponding Class to test YourClass class
 *
 *  For each class in your library, there should be a corresponding Unit-Test for it
 *  Unit-Tests should be as much as possible independent from other test going on.
 *
 *  @author yourname
 */
class ReaderTest extends \PHPUnit\Framework\TestCase
{
    protected static $reader;

    public static function setupBeforeClass()
    {
        $reader = new Serversidebim\ExpressReader\Reader;
        $reader = new Serversidebim\ExpressReader\Reader;
        $reader->parseExpress(__DIR__ . '/IFC4.exp');
        self::$reader = $reader;
        //file_put_contents(__DIR__ . '/IFC4.ser', json_encode($reader));
    }

    /**
     * Just check if the YourClass has no syntax error
     *
     * This is just a simple check to make sure your library has no syntax error. This helps you troubleshoot
     * any typo before you even use this library in a real project.
     *
     */
    public function testIsThereAnySyntaxError()
    {
        $var = new Serversidebim\ExpressReader\Reader;
        $this->assertTrue(is_object($var));
        unset($var);
    }

    public function testParseExpress()
    {
        $reader = self::$reader;

        $this->assertTrue(is_object($reader));

        $this->assertEquals($reader->getSchema(), "IFC4");
    }

    public function testTypes()
    {
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

        // Check some true values
        $base = [
          'BOOLEAN',
          'REAL',
          'BINARY',
          'INTEGER',
          'NUMBER',
          'STRING',
          'ENUMERATION',
          'SELECT',
          'LOGICAL',
          'ENTITY',
        ];

        // All types should return a true value
        foreach ($reader->getTypes() as $key => $value) {
            $trueType = $value->getTrueType();
            $this->assertTrue(in_array($trueType, $base));
        }
    }

    public function testEntities()
    {
        $reader = self::$reader;

        $this->assertCount(776, $reader->getEntities());

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
        $this->assertFalse($reader->isSubTypeOf($IfcCostValue, $ifcroot));

        $this->assertEquals(2, count($ifcproduct->parameters));
        $this->assertEquals(8, count($reader->getSubtypesOf($ifcproduct)));

        $this->assertEquals('IFCROOT.IFCOBJECTDEFINITION.IFCOBJECT.IFCPRODUCT', strtoupper($ifcproductFull->name));
    }

    public function testGetFullEntity()
    {
        //IfcRectangleProfileDef
        $reader = self::$reader;

        $entity = $reader->getEntity('IfcRectangleProfileDef');
        $this->assertCount(2, $entity->parameters);
    }

    public function testParams()
    {
        $reader = self::$reader;
        $IfcMaterialLayerWithOffsets = $reader->getEntity('IfcMaterialLayerWithOffsets');
        $IfcCartesianPointList3D = $reader->getEntity('IfcCartesianPointList3D');

        $this->assertInternalType('array', $reader->getParameters($IfcMaterialLayerWithOffsets));
        $this->assertNotNull('array', $reader->getParameter($IfcMaterialLayerWithOffsets, 'OffsetValues'));

        $this->assertInternalType('array', $reader->getParameter($IfcCartesianPointList3D, 'CoordList')->type);

        $typeOf = $reader->getParameter($IfcMaterialLayerWithOffsets, 'OffsetValues')->type['OF'];
        $this->assertNotNull($reader->getTypes()[strtoupper($typeOf)]);

        $IfcApprovalRelationship = $reader->getEntity('IfcApprovalRelationship');
        $typeOfEntity = $reader->getParameter($IfcApprovalRelationship, 'RelatedApprovals')->type['OF'];
        $this->assertNotNull($reader->getEntity($typeOfEntity));
    }

    public function testLinksToEntities()
    {
        $reader = self::$reader;
        $IfcBuildingStorey = $reader->getEntity('IfcBuildingStorey');
        $result = $reader->linksToEntities($IfcBuildingStorey);
        $this->assertTrue($result);

        $IfcCartesionPoint = $reader->getEntity('IFCCARTESIANPOINT');
        $result = $reader->linksToEntities($IfcCartesionPoint);
        $this->assertFalse($result);

        $t = $reader->getType('IfcLayeredItem');

        $IFCPRESENTATIONLAYERASSIGNMENT = $reader->getEntity('IFCPRESENTATIONLAYERASSIGNMENT');
        $result = $reader->linksToEntities($IFCPRESENTATIONLAYERASSIGNMENT);
        $this->assertTrue($result);

        // Now check if this runs for each entity
        $entities = $reader->getEntities();
        foreach ($entities as $ent) {
            try {
                $check = $reader->linksToEntities($ent);
            } catch (Exception $e) {
                var_dump($ent->name);
                var_dump($ent->parameters);
                throw $e;
            }
        }
    }
}
