<?php

namespace Mitoop\Query;

class ModelScope
{
    protected string $className;

    private $args;

    public function __construct(public string $scopeName, ...$args)
    {
        $this->args = $args;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function setClassName(string $className): self
    {
        $this->className = $className;

        return $this;
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}
