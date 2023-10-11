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

    private array $conditions;

    public function __construct(Builder $builder, array $conditions)
    {
        $this->builder = $builder;
        $this->conditions = $conditions;
    }

    private function handleWhere(): static
    {
        $wheres = $this->sortOutWhereConditions($this->conditions);

        foreach ($wheres as $where) {
            if (is_array($where)) {
                foreach ($where as $field => $operatorAndValue) {
                    $mixType = Arr::get($operatorAndValue, 'mix', 'and');
                    unset($operatorAndValue['mix']);

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

    private function handleWith(): static
    {
        $with = $this->conditions['with'] ?? [];

        if (! empty($with)) {
            $this->builder->with($with);
        }

        return $this;
    }

    private function handleGroupBy(): static
    {
        $groupBy = $this->conditions['groupBy'] ?? [];

        if (! empty($groupBy)) {
            $this->builder->groupBy($groupBy);
        }

        return $this;
    }

    private function handleHaving(): static
    {
        $havings = $this->sortOutHavingConditions($this->conditions);
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

    private function handleOrderBy(): static
    {
        $order = $this->conditions['order'] ?? [];

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

    private function sortOutWhereConditions(): array
    {
        $newConditions = [];

        foreach (Arr::get($this->conditions, 'wheres', []) as $key => $item) {
            if ($item instanceof Closure || $item instanceof Expression || $item instanceof ModelScope) {
                $newConditions[] = $item;

                continue;
            }

            if (! is_array($item) && ! is_string($item) && ! is_bool($item) && ! is_int($item) && ! is_null($item)) {
                throw new LogicException("[laravel advanced search] conditions' key `{$key}`'s value is trouble, please check it.");
            }

            if (str_contains($key, '.')) {
                $field = explode('.', $key)[0];
                $operatorAndValue = [explode('.', $key)[1] => $item];
            } elseif (! is_array($item)) {
                $field = $key;
                $operatorAndValue = ['eq' => $item];
            } else {
                $field = $key;
                $operatorAndValue = $item;
            }

            $field = str_replace('$', '.', $field);

            $newConditions[] = [
                $field => $operatorAndValue,
            ];
        }

        return $newConditions;
    }

    private function makeComboQuery($builder, $field, $mixType, $operatorAndValue): void
    {
        $whereType = $mixType === 'and' ? 'where' : 'orWhere';

        foreach ($operatorAndValue as $operator => $value) {
            if ($operator === 'in') {
                if ((is_array($value) || $value instanceof Collection) && ! empty($value)) {
                    $builder->{"{$whereType}In"}($field, $value);
                }
            } elseif ($operator === 'not_in') {
                if (is_array($value) && ! empty($value)) {
                    $builder->{"{$whereType}NotIn"}($field, $value);
                }
            } elseif ($operator === 'is') {
                $builder->{"{$whereType}null"}($field);
            } elseif (Str::snake($operator) === 'is_not') {
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

    private function sortOutHavingConditions(): array
    {
        $newConditions = [];

        foreach (Arr::get($this->conditions, 'having', []) as $key => $item) {
            if (is_int($key)) {
                if ($item instanceof Closure || $item instanceof Expression || $item instanceof ModelScope) {
                    $newConditions[] = $item;
                }
            } else {
                if (str_contains($key, '.')) {
                    $field = explode('.', $key)[0];
                    $operatorAndValue = [explode('.', $key)[1] => $item];
                } elseif (! is_array($item)) {
                    $field = $key;
                    $operatorAndValue = ['eq' => $item];
                } else {
                    $field = $key;
                    $operatorAndValue = $item;
                }

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

    public function __invoke(): Builder
    {
        $this->handleWhere()
            ->handleWith()
            ->handleGroupBy()
            ->handleHaving()
            ->handleOrderBy();

        return $this->builder;
    }
}
