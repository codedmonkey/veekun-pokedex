<?php

namespace Pokedex;

class Join
{
    use ParsingTrait;

    public array $data = [];
    public string $mappingField;
    public string $valueField;

    function __construct(
        public string $filename,
        public Collection $mapping,
        public Collection $values,
    ) {
        $this->read();

        $dataColumns = array_keys($this->data[0]);
        $this->mappingField = $dataColumns[0];
        $this->valueField = $dataColumns[1];
    }
}
