<?php

namespace Serversidebim\ExpressReader;

use Serversidebim\ExpressReader\Reader;

class Entity {
  
    public $name;
    public $supertypeOf = [];
    public $abstractSupertypeOf = false;
    public $subtypeOf = null;
    public $parameters = [];
    public $inverse = [];
    public $derive = [];
    public $where = [];
    public $unique = [];
    
    function __construct(string $name) {
        $this->name = $name;
    }
    
    function merge(Entity $tomerge) {
        $this->name .= "." . $tomerge->name;
        $this->supertypeOf = null;
        $this->abstractSupertypeOf = null;
        $this->subtypeOf = null;
        $this->parameters = array_merge_recursive($this->parameters, $tomerge->parameters);
        $this->optionalParameters = array_merge_recursive($this->optionalParameters, $tomerge->optionalParameters);
        $this->inverse = array_merge_recursive($this->inverse, $tomerge->inverse);
        $this->where = array_merge_recursive($this->where, $tomerge->where);
        $this->derive = array_merge_recursive($this->derive, $tomerge->derive);
        $this->unique = array_merge_recursive($this->unique, $tomerge->unique);
        
        return $this;
        
    }
    
}
