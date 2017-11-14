<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Serversidebim\ExpressReader;

/**
 * Description of Param
 *
 * @author Stam4
 */
class Param {

    public $type = "";
    public $optional = false;

    function __construct(string $type) {
        $this->type = $type;
    }

    public function setCollection(string $type, $min, $max, $of) {
        $this->type = $this->getCollection($type, $min, $max, $of);
    }

    public function getCollection($type, $min, $max, $of) {
        return array(
            'KIND' => $type,
            'MIN' => ($min === '?' ? null : (int)$min),
            'MAX' => ($max === '?' ? null : (int)$max),
            'OF' => $of
        );
    }
    
    public function setOptional($boolean = FALSE){
        $this->optional = $boolean;
    }
}
