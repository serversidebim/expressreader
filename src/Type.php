<?php

namespace Serversidebim\ExpressReader;

class Type
{
    public string $name = "";
    public string $type;
    public int $min;
    public int $max;
    public string $of;
    public int $length;
    public bool $fixed = false;
    public array $values = [];
    public array $where = [];

    protected $reader;

    public function __construct(string $name, $reader = null)
    {
        $this->name = $name;
        $this->reader = $reader;
    }

    /**
     * Get the true type of the Type
     * @return string BOOLEAN|REAL|BINARY|INTEGER|NUMBER|STRING|ENUMERATION|LOGICAL|SELECT|ENTITY
     */
    public function getTrueType(): ?string
    {
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
      ];

        if (!in_array($this->type, $base)) {
            $type = $this->type;

            // Is it a list or array?
            if (in_array($this->type, ['LIST', 'ARRAY', 'SET'])) {
                $ofType = $this->of;
                if (in_array($ofType, $base)) {
                    return $ofType;
                }

                if ($this->reader->getType($ofType)) {
                    $type = $ofType;
                } elseif ($this->reader->getEntity($ofType)) {
                    return 'ENTITY';
                } else {
                    return null;
                }
            }

            $parentType = $this->reader->getType($type);
            //var_dump($parentType);
            if ($parentType) {
                return $parentType->getTrueType();
            } else {
                return null;
            }
        }

        return $this->type;
    }
}
