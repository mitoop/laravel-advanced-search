<?php

namespace Mitoop\Query;

class ModelScope
{
    protected $className;

    private $args;

    public function __construct(public string $scopeName, ...$args)
    {
        $this->args = $args;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function setClassName($className): self
    {
        $this->className = $className;

        return $this;
    }

    public function getClassName()
    {
        return $this->className;
    }
}
