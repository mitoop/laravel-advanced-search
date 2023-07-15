<?php

namespace Mitoop\Query;

class ModelScope
{
    /**
     * Class name.
     */
    protected $className;

    /**
     * Scope method name.
     */
    private $scopeName;

    /**
     * Args.
     *
     * @var array
     */
    private $args;

    public function __construct($scopeName, ...$args)
    {
        $this->scopeName = $scopeName;
        $this->args = $args;
    }

    /**
     * @return mixed
     */
    public function getScopeName()
    {
        return $this->scopeName;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @param  mixed  $className
     */
    public function setClassName($className): self
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getClassName()
    {
        return $this->className;
    }
}
