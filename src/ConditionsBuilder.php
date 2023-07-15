<?php

namespace Mitoop\Query;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

class ConditionsBuilder
{
    private Builder $builder;

    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    public function attach(array $conditions): Builder
    {
        $this->handleWhere($conditions)
            ->handleWith($conditions)
            ->handleGroupBy($conditions)
            ->handleHaving($conditions)
            ->handleOrderBy($conditions);

        return $this->builder;
    }

    private function handleWhere($conditions): static
    {
        $wheres = $this->sortOutWhereConditions($conditions);

        foreach ($wheres as $where) {
            if (is_array($where)) {
                foreach ($where as $field => $operatorAndValue) {
                    $mixType = Arr::get($operatorAndValue, 'mix', 'and');
                    unset($operatorAndValue['mix']);

                    // Query relation.
                    if (str_contains($field, '.')) {
                        [$relation, $field] = explode('.', $field);
                        $this->builder->whereHas(Str::camel($relation), function ($builder) use (
                            $operatorAndValue,
                            $mixType,
                            $field
                        ) {
                            $this->makeComboQuery($builder, $field, $mixType, $operatorAndValue);
                        });
                    } else {
                        // Normal where.
                        $this->builder->where(function ($builder) use ($field, $mixType, $operatorAndValue) {
                            $this->makeComboQuery($builder, $field, $mixType, $operatorAndValue);
                        });
                    }
                }
            } elseif ($where instanceof Closure) {
                $this->builder->where($where);
            } elseif ($where instanceof Expression) {
                $this->builder->whereRaw($where);
            } elseif ($where instanceof ModelScope) {
                $method = $where->scopeName;
                $className = $where->getClassName() ?: get_class($this->builder->getModel());
                $args = $where->getArgs();
                $scopeMethod = 'scope'.Str::title($method);

                if ($className !== get_class($this->builder->getModel()) || ! method_exists($this->builder->getModel(), $scopeMethod)) {
                    throw new LogicException('[laravel advanced search] '.get_class($this->builder->getModel()).' cont find '.$scopeMethod.' method.');
                }

                $this->builder->{$method}(...$args);
            }
        }

        return $this;
    }

    private function handleWith($conditions): static
    {
        $with = $conditions['with'] ?? [];

        if (! empty($with)) {
            $this->builder->with($with);
        }

        return $this;
    }

    private function handleGroupBy($conditions): static
    {
        $groupBy = $conditions['groupBy'] ?? [];

        if (! empty($groupBy)) {
            $this->builder->groupBy($groupBy);
        }

        return $this;
    }

    private function handleHaving($conditions): static
    {
        $havings = $this->sortOutHavingConditions($conditions);
        foreach ($havings as $having) {
            if (is_array($having)) {
                foreach ($having as $field => $operatorAndValue) {
                    $having_raws = [];
                    $mixType = Arr::get($operatorAndValue, 'mix', 'and');
                    unset($operatorAndValue['mix']);

                    foreach ($operatorAndValue as $operator => $value) {
                        $having_raws[] = $field.$this->convertOperator($operator).$value;
                    }

                    if ($having_raws) {
                        $this->builder->havingRaw(implode(' '.$mixType.' ', $having_raws));
                    }
                }
            } elseif ($having instanceof Expression) {
                $this->builder->havingRaw($having);
            }
        }

        return $this;
    }

    private function handleOrderBy($conditions): static
    {
        $order = $conditions['order'] ?? [];

        foreach ($order as $field => $direction) {
            if (is_string($direction)) {
                $this->builder->orderBy($field, $direction);
            }
            if ($direction instanceof Expression) {
                $this->builder->orderByRaw($direction);
            }
        }

        return $this;
    }

    private function sortOutWhereConditions($conditions): array
    {
        $newConditions = [];

        // Handle conditions 'where' key and value.
        foreach (Arr::get($conditions, 'wheres', []) as $key => $item) {
            // Make sure type of conditions' wheres is array.
            if ($item instanceof Closure || $item instanceof Expression || $item instanceof ModelScope) {
                $newConditions[] = $item;

                continue;
            }

            // $item's value must be array|string|bool|int , except Closure|Expression|ModelScope above.
            if (! is_array($item) && ! is_string($item) && ! is_bool($item) && ! is_int($item) && ! is_null($item)) {
                throw new LogicException("[laravel advanced search] conditions' key `{$key}`'s value is trouble, please check it.");
            }

            if (str_contains($key, '.')) { // If `$key` such as `name.like`, will parse the correct field and operator.
                // eg: 'name.like' => 'lara' -----> 'name' => [ 'like' => 'lara']
                $field = explode('.', $key)[0];
                $operatorAndValue = [explode('.', $key)[1] => $item];
            } elseif (! is_array($item)) {   // Default operator is equal.
                //eg: 'name' => 'tom' -----> 'name' => ['eq' => 'tom']
                $field = $key;
                $operatorAndValue = ['eq' => $item];
            } else {
                $field = $key;
                $operatorAndValue = $item;
            }

            // Handle `$field` contain `$`.
            // eg: 'user$name' => [ 'like' => '%tony%' ] -----> 'user.name' => [ 'like' => '%tony%' ]
            $field = str_replace('$', '.', $field);

            $newConditions[] = [
                $field => $operatorAndValue,
            ];
        }

        return $newConditions;
    }

    private function makeComboQuery($builder, $field, $mixType, $operatorAndValue): void
    {
        $whereType = 'and' === $mixType ? 'where' : 'orWhere';

        foreach ($operatorAndValue as $operator => $value) {
            if ('in' === $operator) {
                if ((is_array($value) || $value instanceof Collection) && ! empty($value)) {
                    $builder->{"{$whereType}In"}($field, $value);
                }
            } elseif ('not_in' === $operator) {
                if (is_array($value) && ! empty($value)) {
                    $builder->{"{$whereType}NotIn"}($field, $value);
                }
            } elseif ('is' === $operator) {
                $builder->{"{$whereType}null"}($field);
            } elseif ('is_not' === Str::snake($operator)) {
                $builder->{"{$whereType}NotNull"}($field);
            } else {
                $builder->{$whereType}($field, $this->convertOperator($operator), $value);
            }
        }

    }

    private function convertOperator($operator): string
    {
        $operatorMap = [
            'eq' => '=',
            'ne' => '<>',
            'gt' => '>',
            'gte' => '>=',
            'ge' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'le' => '<=',
        ];

        return $operatorMap[$operator] ?? $operator;
    }

    private function sortOutHavingConditions($conditions): array
    {
        $newConditions = [];

        foreach (Arr::get($conditions, 'having', []) as $key => $item) {
            if (is_int($key)) {
                // If `$item` is closure, will continue.
                if ($item instanceof Closure || $item instanceof Expression || $item instanceof ModelScope) {
                    $newConditions[] = $item;

                    continue;
                }
            } else {
                if (str_contains($key, '.')) {  // If `$key` such as `name.like`, will parse the correct field and operator.
                    // eg: 'name.like' => 'lara' -----> 'name' => [ 'like' => 'lara']
                    $field = explode('.', $key)[0];
                    $operatorAndValue = [explode('.', $key)[1] => $item];
                } elseif (! is_array($item)) {   // Default operator is equal.
                    //eg: 'name' => 'tom' -----> 'name' => ['eq' => 'tom']
                    $field = $key;
                    $operatorAndValue = ['eq' => $item];
                } else {
                    $field = $key;
                    $operatorAndValue = $item;
                }

                // $item's value must be array|string|bool|int , except Closure|Expression|ModelScope above.
                if (! is_array($operatorAndValue)) {
                    throw new LogicException('[laravel advanced search] having has wrong templates, please check.');
                }

                $newConditions[] = [
                    $field => $operatorAndValue,
                ];
            }
        }

        return $newConditions;
    }
}
