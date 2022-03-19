<?php

namespace Serversidebim\ExpressReader;

use Exception;
use JsonSerializable;

class Reader implements JsonSerializable
{
    private string $schema = "";
    protected array $types = [];
    protected array $entities = [];
    protected array $functions = [];
    protected array $rules = [];

    public function __construct()
    {
    }

    /**
     * Parse an express file
     * @param string $filepath Path to the express definition file
     * @throws Exception
     * @throws Exception
     * @throws Exception
     */
    public function parseExpress(string $filepath): Reader
    {
        $contents = file_get_contents($filepath);

        return $this->parse($contents);
    }

    /**
     * @throws Exception
     */
    public function parseStream($stream): Reader
    {
        if (!is_resource($stream)) {
            throw new Exception("Cannot parse invalid stream resource");
        }
        rewind($stream);
        $contents = "";
        while ($line = fgets($stream)) {
            $contents .= $line;
        }

        return $this->parse($contents);
    }

    /**
     * @throws Exception
     */
    public function parse(string $contents): Reader
    {
        $this->parseSchema($contents);

        $this->types = [];
        $this->parseTypes($contents);

        $this->entities = [];
        $this->parseEntities($contents);

        $this->functions = [];
        $this->parseFunctions($contents);

        $this->rules = [];
        $this->parseRules($contents);

        return $this;
    }

    /**
     * @throws Exception
     */
    private function parseSchema($contents)
    {
        $matches = array();
        if (preg_match("/^SCHEMA (.*?);/mi", $contents, $matches) == 1) {
            $this->schema = $matches[1];
        } else {
            throw new Exception("No Schema found in Express file");
        }
    }

    private function parseTypes($contents)
    {
        $matches = array();

        if (!preg_match_all("/TYPE\s+(\w+)\s=\s(.*?);.*?END_TYPE/s", $contents, $matches) === false) {
            foreach ($matches[0] as $key => $value) {
                $type = new Type($matches[1][$key], $this);

                // check if type is a single element
                if (preg_match("/^(\w+)?$/s", $matches[2][$key])) {
                    $type->type = $matches[2][$key];
                } // check for LIST, ARRAY or SET
                elseif (preg_match("/^(\w+)\s?\[([\d?]+):([\d?]+)]\sOF\s(\w+)/", $matches[2][$key], $m)) {
                    $type->type = $m[1];
                    $type->min = $m[2];
                    $type->max = $m[3];
                    $type->of = $m[4];
                } // check STRING with length / fixed
                elseif (preg_match("/STRING\((\d+)\)(\s(FIXED))?/", $matches[2][$key], $m)) {
                    $type->type = "STRING";
                    $type->length = $m[1];
                    if (count($m) == 4) {
                        $type->fixed = true;
                    }
                } // ENUMERATION or SELECT
                elseif (preg_match("/(ENUMERATION|SELECT).*?\((.*?)\)/s", $matches[2][$key], $m)) {
                    $type->type = $m[1];
                    $type->values = array_map('trim', explode(",", $m[2]));
                } //else {
                    //echo "Could not interpret ".$matches[1][$key] . "\n";
                //}

                // Now check if there is a WHERE clause
                if (preg_match("/WHERE.*?(\w.*?)END_TYPE/s", $value, $m)) {
                    $type->where = array();
                    $ar = array_map("trim", explode(";", $m[1]));
                    foreach ($ar as $v) {
                        if (!empty($v)) {
                            $parts = array_map('trim', explode(":", $v, 2));
                            $type->where[$parts[0]] = $parts[1];
                        }
                    }
                }

                // Add type to express
                $this->types[strtoupper($matches[1][$key])] = $type;
            }

            //var_dump($this->express);
        }
    }

    private function parseEntities($contents)
    {
        $matches = array();

        if (!preg_match_all("/ENTITY (\w+)(.*?);(.*?END_ENTITY);/s", $contents, $matches) === false) {
            foreach ($matches[0] as $key => $value) {
                $entity = new Entity($matches[1][$key]);

                // Check the supertype
                //(ABSTRACT)?\s?SUPERTYPE OF \(ONEOF.*?\((.*?)\)
                if (preg_match("/(ABSTRACT)?\s?SUPERTYPE OF \(ONEOF.*?\((.*?)\)/s", $matches[2][$key], $m)) {
                    $entity->supertypeOf = array_map("trim", explode(",", $m[2]));
                    if (!empty($m[1])) {
                        $entity->abstractSupertypeOf = true;
                    }
                }

                // Check the subtype
                // SUBTYPE OF \((.*?)\)
                if (preg_match("/SUBTYPE OF \((.*?)\)/s", $matches[2][$key], $m)) {
                    $entity->subtypeOf = $m[1];
                }

                // Now check the parameters
                if (preg_match("/(.*?)\n\s?[A-Z_]{2,}[\r\n;Y]/s", $matches[3][$key], $m)) {
                    $params1 = explode(";", $m[1]);
                    $params2 = array();
                    $optional = array();
                    foreach ($params1 as $v) {
                        if (!empty($v)) {
                            $split = array_map("trim", explode(":", $v, 2));
                            if (isset($split[1])) {
                                if (preg_match("/^([A-Z]*\s+)?(?:([A-Z]*)\s\[(.):(.)])?(?:\sOF\s([A-Z]*)\s\[(.):(.)])?(?:\sOF\s)?(?:UNIQUE\s)?(\w*)$/", $split[1], $n)) {
                                    $param = new Param($n[8]);
                                    if (!empty($n[2])) {
                                        $of = $n[8];
                                        if (!empty($n[5])) {
                                            $of = $param->getCollection($n[5], $n[6], $n[7], $n[8]);
                                        }
                                        $param->setCollection($n[2], $n[3], $n[4], $of);
                                    }
                                    if (!empty($n[1])) {
                                        $param->setOptional(true);
                                    }
                                    $params2[$split[0]] = $param;
                                }
                            }
                        }
                    }
                    $entity->parameters = $params2;
                    $entity->optionalParameters = $optional;
                }

                // Now check INVERSE
                // INVERSE\W+(.*?)\n\s?([A-Z_]+)\W
                if (preg_match("/INVERSE\W+(.*?)\n\s?[A-Z_]+(?:\r\n|$)/s", $matches[3][$key], $m)) {
                    $ar = array_map('trim', explode(";\r\n", $m[1]));
                    foreach ($ar as $v) {
                        if (!empty($v)) {
                            $parts = array_map('trim', explode(":", $v, 2));
                            $entity->inverse[$parts[0]] = $parts[1];
                        }
                    }
                }

                // Now check DERIVE
                if (preg_match("/DERIVE\W+(.*?)\n\s?[A-Z_]+(?:\r\n|$)/s", $matches[3][$key], $m)) {
                    $ar = array_map('trim', explode(";\r\n", $m[1]));
                    foreach ($ar as $v) {
                        if (!empty($v)) {
                            $parts = array_map('trim', explode(":", $v, 2));
                            $entity->derive[$parts[0]] = $parts[1];
                        }
                    }
                }

                // Now check WHERE
                if (preg_match("/WHERE\W+(.*?)\n\s?([A-Z_]+)(?:\r\n|$)/s", $matches[3][$key], $m)) { // TODO WRONG!!
                    $ar = array_map('trim', explode(";\r\n", $m[1]));
                    foreach ($ar as $v) {
                        if (!empty($v)) {
                            $parts = array_map('trim', explode(":", $v, 2));
                            $entity->where[$parts[0]] = $parts[1];
                        }
                    }
                }

                // Now check UNIQUE
                if (preg_match("/UNIQUE\r\n\W+(.*?)\n\s?([A-Z_]+)(?:\r\n|$)/s", $matches[3][$key], $m)) { // TODO WRONG!!
                    $ar = array_map('trim', explode(";", $m[1]));
                    foreach ($ar as $v) {
                        if (!empty($v)) {
                            $parts = array_map('trim', explode(":", $v, 2));
                            $entity->unique[$parts[0]] = $parts[1];
                        }
                    }
                }

                // TODO: parse functions?
                // Add entity to express
                $this->entities[strtoupper($matches[1][$key])] = $entity;
                //var_dump($entity);
            }
        }
    }

    public function parseFunctions($contents)
    { //TODO: this needs more intelligent parsing
        $matches = array();

        if (!preg_match_all("/FUNCTION ((\w+).*?)END_FUNCTION;/s", $contents, $matches) === false) {
            foreach ($matches[0] as $key => $value) {

                // add functions to this
                $this->functions[strtoupper($matches[2][$key])] = $matches[1][$key];
                //var_dump($entity);
            }
        }
    }

    public function parseRules($contents)
    { //TODO: this needs more intelligent parsing
        $matches = array();

        if (!preg_match_all("/RULE ((\w+).*?)END_RULE;/s", $contents, $matches) === false) {
            foreach ($matches[0] as $key => $value) {

                // add functions to this
                $this->rules[strtoupper($matches[2][$key])] = $matches[1][$key];
                //var_dump($entity);
            }
        }
    }

    public function getSchema(): string
    {
        return $this->schema;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Get the IFC Type by name
     * @param string $name Name of the Type
     * @return Type|null
     */
    public function getType(string $name): ?Type
    {
        $name = strtoupper($name);
        if (key_exists($name, $this->types)) {
            return $this->types[$name];
        }
        return null;
    }

    public function getEntities(): array
    {
        return $this->entities;
    }

    public function getEntity(string $name)
    {
        $name = strtoupper($name);
        if (key_exists($name, $this->entities)) {
            return $this->entities[$name];
        }
        return null;
    }

    public function getFullEntity(string $name)
    {
        $name = strtoupper($name);
        if ($ent = $this->getEntity($name)) {
            $clone = clone $ent;

            if ($parent = $this->getSupertype($clone)) {
                $parentName = $parent->name;
                $parent = $this->getFullEntity($parentName);
                if ($parent) {
                    return $parent->merge($clone);
                }
            }
            return $clone;
        }

        return null;
    }

    /**
     * Get the subtypes of Entity
     * @param Entity $ent The entity to check
     */
    public function getSubtypesOf(Entity $ent): array
    {
        $subtypes = [];
        if (is_array($ent->supertypeOf)) {
            foreach ($ent->supertypeOf as $sup) {
                $subtypes[] = $this->getEntity($sup);
            }
        }
        return $subtypes;
    }

    /**
     * Get the supertype of the Entity
     * @param Entity $ent The Entity to check
     * @return Entity | null
     */
    public function getSupertype(Entity $ent): ?Entity
    {
        if ($ent->subtypeOf) {
            return $this->getEntity($ent->subtypeOf);
        }
        return null;
    }

    public function isDirectSupertype(Entity $entity, Entity $direct): bool
    {
        return strtoupper($this->getSupertype($entity)->name) == strtoupper($direct->name);
    }

    public function isSubTypeOf(Entity $entity, Entity $super): bool
    {
        $parent = $this->getSupertype($entity);
        if ($parent) {
            if ($this->isDirectSupertype($entity, $super)) {
                return true;
            } else {
                return $this->isSubTypeOf($parent, $super);
            }
        } else {
            return false;
        }
    }

    public function getParameters(Entity $entity): array
    {
        return $entity->parameters;
    }

    public function getParameter(Entity $entity, string $param)
    {
        $parameters = $this->getParameters($entity);
        if (key_exists($param, $parameters)) {
            return $parameters[$param];
        }
        return null;
    }

    /**
     * Return true when the entity links to other entities
     * @param Entity $entity
     * @return boolean
     */
    public function linksToEntities(Entity $entity): bool
    {
        $params = $entity->parameters;
        foreach ($params as $param) {
            $type = $param->type;
            while (is_array($type)) {
                $type = $type['OF'];
            }

            if (key_exists(strtoupper($type), $this->entities)) {
                return true;
            }

            // else check if it's a select type
            $t = $this->getType($type);
            if ($t) {
                if (strtoupper($t->type) == 'SELECT') {
                    foreach ($t->values as $v) {
                        if (key_exists(strtoupper($v), $this->entities)) {
                            return true;
                        }
                    }
                }
            }
        }

        if ($super = $this->getSupertype($entity)) {
            return $this->linksToEntities($super);
        }

        return false;
    }

    public function jsonSerialize(): array
    {
        return [
            "types" => $this->types,
            "entities" => $this->entities,
            "functions" => $this->functions,
            "rules" => $this->rules,
        ];
    }
}
