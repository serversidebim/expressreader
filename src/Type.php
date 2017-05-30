<?php

namespace Serversidebim\ExpressReader;

class Type {

    public $name = "";
    public $type;
    public $min;
    public $max;
    public $of;
    public $length;
    public $fixed = false;
    public $values = [];
    public $where = [];
    
    function __constructor(string $name) {
        $this->name = $name;
    }

}
