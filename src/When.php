<?php

namespace Mitoop\Query;

class When
{
    private $when;

    private $success;

    private $fail;

    public function __construct($value)
    {
        $this->when = boolval(is_callable($value) ? $value() : $value);
    }

    public static function make($value)
    {
        return new static($value);
    }

    public function success($value)
    {
        $this->success = $value;

        return $this;
    }

    public function fail($value)
    {
        $this->fail = $value;

        return $this;
    }

    public function result()
    {
        $result = $this->when === true ? $this->success : $this->fail;

        return is_callable($result) ? $result() : $result;
    }
}
