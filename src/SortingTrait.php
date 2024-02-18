<?php

namespace Pokedex;

trait SortingTrait
{
    function sortEntries(): static
    {
        ksort($this->data);

        return $this;
    }

    function sortEntryFields(): static
    {
        foreach ($this->data as &$row) {
            ksort($row);
        }

        return $this;
    }

    function sortByOrderField(): static
    {
        uasort($this->data, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $this;
    }
}
