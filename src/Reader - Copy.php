<?php

namespace Serversidebim\ExpressReader;

class Reader {

    private $schema;
    private $express;

    public function __construct($schema) {
        $this->schema = strtoupper($schema);
        $this->loadExpress();
    }

    private function loadExpress() {
        $filename = __DIR__ . "/../Express/" . $this->schema . ".exp";
        $filename2 = __DIR__ . "/../Express/" . $this->schema . ".json";
        if (file_exists($filename2)) {
            try {
                $this->express = json_decode(file_get_contents($filename2), true);
            } catch (Exception $ex) {
                // TODO: unlink file?
            }
        } else {
            $this->parseExpress($filename);
            $this->storeExpress($filename2);
        }
    }

    private function parseExpress($filename) {
        $contents = file_get_contents($filename);

        $this->express = array();
        $this->express['types'] = array();
        $this->parseTypes($contents);

        $this->express['entities'] = array();
        $this->parseEntities($contents);
    }

    private function storeExpress($filename) {
        file_put_contents($filename, json_encode($this->express));
    }

    private function parseTypes($contents) {
        $matches = array();

        if (!preg_match_all("/TYPE\s+(\w+)\s=\s(.*?);.*?END_TYPE/s", $contents, $matches) === FALSE) {

            foreach ($matches[0] as $key => $value) {
                $type = array();
                $type['_name'] = $matches[1][$key];

                // check if type is a single element
                if (preg_match("/^(\w+)?$/s", $matches[2][$key])) {
                    $type['_value'] = $matches[2][$key];
                }
                // check for LIST, ARRAY or SET
                elseif (preg_match("/^(\w+)\s?\[([\d\?]+):([\d\?]+)\]\sOF\s(\w+)/", $matches[2][$key], $m)) {
                    $type['_value'] = $m[1];
                    $type['_min'] = $m[2];
                    $type['_max'] = $m[3];
                    $type['_of'] = $m[4];
                }
                // check STRING with length / fixed
                elseif (preg_match("/STRING\((\d+)\)(\s(FIXED))?/", $matches[2][$key], $m)) {
                    $type['_value'] = "STRING";
                    $type['_length'] = $m[1];
                    if (count($m) == 4) {
                        $type['_fixed'] = true;
                    }
                }
                // ENUMERATION or SELECT
                elseif (preg_match("/(ENUMERATION|SELECT).*?\((.*?)\)/s", $matches[2][$key], $m)) {
                    $type['_value'] = $m[1];
                    $type['_values'] = array_map('trim', explode(",", $m[2]));
                } else {
                    //echo "Could not interpret ".$matches[1][$key] . "\n";
                }

                // Now check if there is a WHERE clause
                if (preg_match("/WHERE.*?(\w.*?)END_TYPE/s", $value, $m)) {

                    $type['_where'] = array();
                    $ar = array_map("trim", explode(";", $m[1]));
                    foreach ($ar as $a => $v) {
                        if (!empty($v)) {

                            $parts = array_map('trim', explode(":", $v, 2));
                            $type['_where'][$parts[0]] = $parts[1];
                        }
                    }
                }

                // Add type to express
                $this->express['types'][strtoupper($matches[1][$key])] = $type;
            }

            //var_dump($this->express);
        }
    }

    private function parseEntities($contents) {
        $matches = array();

        if (!preg_match_all("/ENTITY (\w+)(.*?);(.*?END_ENTITY);/s", $contents, $matches) === FALSE) {

            foreach ($matches[0] as $key => $value) {
                $entity = array();
                $entity['_name'] = $matches[1][$key];

                // Check the supertype
                //(ABSTRACT)?\s?SUPERTYPE OF \(ONEOF.*?\((.*?)\)
                if (preg_match("/(ABSTRACT)?\s?SUPERTYPE OF \(ONEOF.*?\((.*?)\)/s", $matches[2][$key], $m)) {
                    $entity['_supertypeOf'] = array_map("trim", explode(",", $m[2]));
                    if (!empty($m[1])) {
                        $entity['_abstractSupertypeOf'] = true;
                    }
                }

                // Check the subtype
                // SUBTYPE OF \((.*?)\)
                if (preg_match("/SUBTYPE OF \((.*?)\)/s", $matches[2][$key], $m)) {
                    $entity['_subtypeOf'] = $m[1];
                }

                // Now check the parameters
                if (preg_match("/(.*?)\n\s?([A-Z_]+)\W/s", $matches[3][$key], $m)) {
                    $params1 = explode(";", $m[1]);
                    $params2 = array();
                    $optional = array();
                    foreach ($params1 as $k => $v) {
                        if (!empty($v)) {
                            $split = array_map("trim", explode(":", $v, 2));
                            if (isset($split[1])) {
                                if (preg_match("/^(OPTIONAL\s+)?(.*?)$/", $split[1], $n)) {
                                    $params2[$split[0]] = $n[2];
                                    if (!empty($n[1])) {
                                        array_push($optional, $split[0]);
                                    }
                                }
                            }
                        }
                    }
                    $entity['_parameters'] = $params2;
                    $entity['_optionalParameters'] = $optional;
                }

                // Now check INVERSE
                // INVERSE\W+(.*?)\n\s?([A-Z_]+)\W
                if (preg_match("/INVERSE\W+(.*?)\n\s?[A-Z_]+(?:\r\n|$)/s", $matches[3][$key], $m)) {
                    $ar = array_map('trim', explode(";\r\n", $m[1]));
                    $entity['_inverse'] = array();
                    foreach ($ar as $k => $v) {
                        if (!empty($v)) {
                            $parts = array_map('trim', explode(":", $v, 2));
                            $entity['_inverse'][$parts[0]] = $parts[1];
                        }
                    }
                }

                // Now check WHERE
                if (preg_match("/WHERE\W+(.*?)\n\s?([A-Z_]+)(?:\r\n|$)/s", $matches[3][$key], $m)) { // TODO WRONG!!
                    $ar = array_map('trim', explode(";\r\n", $m[1]));
                    $entity['_where'] = array();
                    foreach ($ar as $k => $v) {
                        if (!empty($v)) {
                            $parts = array_map('trim', explode(":", $v, 2));
                            $entity['_where'][$parts[0]] = $parts[1];
                        }
                    }
                }

                // Now check UNIQUE
                if (preg_match("/UNIQUE\r\n\W+(.*?)\n\s?([A-Z_]+)(?:\r\n|$)/s", $matches[3][$key], $m)) { // TODO WRONG!!
                    $ar = array_map('trim', explode(";", $m[1]));
                    $entity['_unique'] = array();
                    foreach ($ar as $k => $v) {
                        if (!empty($v)) {
                            $parts = array_map('trim', explode(":", $v, 2));
                            $entity['_unique'][$parts[0]] = $parts[1];
                        }
                    }
                }

                // TODO: parse functions?
                // Add entity to express
                $this->express['entities'][strtoupper($matches[1][$key])] = $entity;
                //var_dump($entity);
            }
        }
    }

    public function isSubtypeOf($class, $supertype) {
        // Check if class is subtype of $supertype
        if (strtoupper($class) == strtoupper($supertype)) {
            return true;
        }

        if (isset($this->express['entities'][strtoupper($class)])) {
            $scheme = $this->express['entities'][strtoupper($class)];
            if (array_key_exists('_subtypeOf', $scheme)) {
                $next = strtoupper($scheme['_subtypeOf']);
                while ($next) {

                    if (strtoupper($supertype) == strtoupper($next)) {
                        return true;
                    }
                    $scheme = $this->express['entities'][$next];
                    if (array_key_exists('_subtypeOf', $scheme)) {
                        $next = strtoupper($scheme['_subtypeOf']);
                    } else {
                        return false;
                    }
                }
            } else {
                return false;
            }
        }

        return false;
    }

    public function getAttributeAr($class) {
        $attr = array();
        $class = strtoupper($class);

        if (array_key_exists($class, $this->express['entities'])) {
            $ent = $this->express['entities'][$class];
            while ($ent) {
                //array_unshift($attr, $ent['_parameters']);
                if (array_key_exists("_parameters", $ent)) {
                $attr = $ent['_parameters'] + $attr;
                }
                if (array_key_exists("_subtypeOf", $ent)) {
                    $ent = $this->express['entities'][strtoupper($ent['_subtypeOf'])];
                }
                else {
                    break;
                }
            }
        }
        
        return $attr;
    }

}
