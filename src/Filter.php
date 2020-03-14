<?php
namespace Tigrez\Tempie;

interface Filter
{
    public function filter($value, array $params);
}