<?php

namespace Serversidebim\ExpressReader;

use Serversidebim\ExpressReader\Reader;

class Entity {
  
    public $name;
    public $supertypeOf = [];
    public $abstractSupertypeOf = false;
    public $subtypeOf = null;
    public $parameters = [];
    public $optionalParameters = [];
    public $inverse = [];
    public $where = [];
    public $unique = [];
    
    function __construct(string $name) {
        $this->name = $name;
    }
    
}
