<?php

namespace Mitoop\Query;

use Closure;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;

class ConditionsGenerator
{
    protected array $conditions = [];

    protected array $params = [];

    protected function where(): array
    {
        return [];
    }

    protected function order(): array
    {
        $sorts = Arr::get($this->params, 'sorts');
        $orders = [];

        if ($sorts && is_string($sorts)) {
            if (str_contains($sorts, ',')) {
                $fields = explode(',', $sorts);
            } else {
                $fields = (array) $sorts;
            }

            foreach ($fields as $field) {
                if (str_starts_with($field, '-')) {
                    $field = substr($field, 1);
                    $direction = 'DESC';
                } else {
                    $direction = 'ASC';
                }

                $orders[$field] = $direction;
            }
        }

        return $orders;
    }

    protected function groupBy(): array
    {
        return [];
    }

    protected function having(): array
    {
        return [];
    }

    protected function with(): array
    {
        return [];
    }

    private function handleWhere(): static
    {
        $this->appendConditions([
            'wheres' => collect($this->where())
                ->filter(function ($item) {
                    return $item !== [];
                })
                ->mapWithKeys(function ($item, $key) {
                    return $this->generateWhereKeyValue($item, $key);
                })->all(),
        ]);

        return $this;
    }

    private function handleWith(): static
    {
        $this->appendConditions([
            'with' => $this->with(),
        ]);

        return $this;
    }

    private function handleSort(): static
    {
        $sorts = $this->order();
        $orders = [];

        foreach ($sorts as $field => $direction) {
            if ($direction instanceof Expression) {
                $orders[] = $direction;

                continue;
            }
            $orders[$field] = $direction;
        }

        $this->appendConditions(['order' => $orders]);

        return $this;
    }

    private function handleGroupBy(): static
    {
        $groupBy = $this->groupBy();

        $this->appendConditions([
            'groupBy' => collect($groupBy)->filter()->map(function ($item) {
                if ($item instanceof When) {
                    $item = $item->result();
                }

                return $item;
            })->unique()->values()->all(),
        ]);

        return $this;
    }

    private function handleHaving(): static
    {
        $having = $this->having();

        $having = collect($having)->filter()->map(function ($item) {
            if ($item instanceof When) {
                $item = $item->result();
            }

            return $item;
        })->all();

        $havings = [];

        foreach ($having as $index => $item) {
            if (is_int($index) && is_array($item)) {
                $havings = array_merge($havings, $item);
            } else {
                $havings[$index] = $item;
            }
        }

        $this->appendConditions([
            'having' => $havings,
        ]);

        return $this;
    }

    private function appendConditions($appendItems): void
    {
        $this->conditions = array_merge($this->conditions, $appendItems);
    }

    private function generateWhereKeyValue($item, $key): array
    {
        if ($item instanceof When) {
            $item = $item->result();
        }

        if (is_int($key) && ($item instanceof Closure || $item instanceof Expression || $item instanceof ModelScope)) {
            return [$key => $item];
        }

        $field = is_int($key) ? $item : $key;

        if (is_null($field)) {
            return [];
        }

        $value = is_int($key) ? Arr::get($this->params, $field) : ($item instanceof Closure ? $item() : $item);

        if (is_null($value) || $value === '') {
            return [];
        }

        return [$field => $value];
    }

    protected function value($field, Closure $closure = null)
    {
        $value = Arr::get($this->params, $field);

        if ($value === null || $value === [] || $value === '') {
            return null;
        }

        return $closure ? $closure($value) : $value;
    }

    protected function when($condition, $success, $fail = null)
    {
        $success = is_callable($success) ? $success() : $success;
        $fail = is_callable($fail) ? $fail() : $fail;

        return When::make($condition)->success($success)->fail($fail);
    }

    public function setParams(array $params): static
    {
        $this->params = $params;

        return $this;
    }

    public function __invoke(): array
    {
        $this->handleWhere()
            ->handleWith()
            ->handleGroupBy()
            ->handleHaving()
            ->handleSort();

        return $this->conditions;
    }
}
