<?php

namespace Tigrez\Tempie;

class FilterCache implements \ArrayAccess {

    private $filters = [];

    // @todo Filter interface!
    public function offsetSet($filterName, $filter) {
        $this->filters[$filterName] = $filter;
    }

    public function offsetExists($filterName) {
        return isset($this->filters[$filterName]);
    }

    public function offsetUnset($filterName) {
        unset($this->filters[$filterName]);
    }

    public function offsetGet($filterName) {
        return $this->filters[$filterName] ?? null;
    }
}
