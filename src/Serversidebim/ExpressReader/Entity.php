<?php

namespace Serversidebim\ExpressReader;

class Entity
{

    public $name;
    public $supertypeOf = [];
    public $abstractSupertypeOf = false;
    public $subtypeOf = null;
    public $parameters = [];
    public $inverse = [];
    public $derive = [];
    public $where = [];
    public $unique = [];
    public array $optionalParameters = [];

    function __construct(string $name)
    {
        $this->name = $name;
    }

    function merge(Entity $toMerge): Entity
    {
        $this->name .= "." . $toMerge->name;
        $this->supertypeOf = null;
        $this->abstractSupertypeOf = null;
        $this->subtypeOf = null;
        $this->parameters = array_merge_recursive($this->parameters, $toMerge->parameters);
        $this->optionalParameters = array_merge_recursive($this->optionalParameters, $toMerge->optionalParameters);
        $this->inverse = array_merge_recursive($this->inverse, $toMerge->inverse);
        $this->where = array_merge_recursive($this->where, $toMerge->where);
        $this->derive = array_merge_recursive($this->derive, $toMerge->derive);
        $this->unique = array_merge_recursive($this->unique, $toMerge->unique);

        return $this;

    }

}
