<h1 align="center">Laravel advanced search</h1>

## 安装

`composer require "mitoop/laravel-advanced-search"`

## 使用
```php
// Controller
class AdminController extends Controller
{
    public function index()
    {
        return $this->success(
            // 调用 `advanced` 方法后还可以继续调用 `Builder` 的其他方法
            Admin::advanced(new AdminFilter)->paginate()
        );
    }
}

// AdminFilter 使用 `make:filter` 命令生成
<?php

namespace App\Http\Filters\Admin;

use Mitoop\Query\ConditionsGenerator;

class AdminFilter extends ConditionsGenerator
{
    protected function where(): array
    {
        return [
            'id',
            'email.like' => $this->value('email', fn ($v) => '%'.$v.'%'),
        ];
    }

    // 要预加载的模型
    protected function with(): array
    {
        return [
            'lora:id,name,tags',
        ];
    }
}

```

#### where 方法详解

```php
public function where()
{
    return [
        // 1. 简单字段
        // 如果传参 status=1 的话 where status = '1'；
        // 如果 status 前端没有传值，那么不会构造
        'status',

        // 2. gt 运算符
        // 如果 year 不传值，什么都不会构造。
        // 如果传值 year=2018，那么就会执行闭包方法， 根据闭包结果构造 where year>'2018-01-01 00:00:00'
        'created_at.gt' => $this->value('year', fn($year) => carbon($year.'-01-01 00:00:00')),

        // 3. like 运算符
        // 如果 name 不传值，什么都不会构造。
        // 如果传值 name=张 ，那么就会构造 where name like '张%'
        'name.like' => $this->value('name', fn($name) => $name.'%'),

        // 4. in 运算符
        // 如果 ids 不传值，什么都不会构造
        // 如果传值 ids=[1,3,4] ，那么就会构造 where id in (1,3,4)
        'id.in' => $this->value('ids'),

        // 5. not null 运算符
        // 如果判断某个字段是否为 null ，使用 is_not 或者 is ，
        // 但是注意对应的值不能为 null ，因为值为 null 时，会被自动跳过
        'deleted_at.is_not' => true,

        // 6. 多运算符
        // where age > 12 and age < 16
        'age' => [
            'gt' => 12,
            'lt' => 16,
        ],

        // 7. 多运算符以及逻辑关系
        // where age > 180 or age < 160
        'height' => [
            'gt'  => '180',
            'lt'  => '160',
            'mix' => 'or',
        ],

        // 8. 支持 Laravel DB Expression
        // where 3=4
        DB::raw('3=4'),

        // 9. 支持闭包
        // where id=4
        // Builder 是 `Illuminate\Database\Eloquent\Builder` 对象
        function (Builder $q) {
            $q->where('id', 4);
        },

        // 10. 支持局部作用域
        // 会调用的对应的模型  scopePopular 方法
        new ModelScope('popular'),
        // 局部作用域参数
        new ModelScope('older', 60),
        // 等同于
        function (Builder $q) {
            $q->older(60);
        },

        // 11. when 方法
        // 如果是经理搜索 type=1 的数据 否则 搜索 type=2 的数据
        'type' => $this->when(user()->isManager(), 1, 2),
        // 当不是经理的时候 只搜索 type = 2 的数据
        'type' => $this->when(!user()->isManager(), 2),
        // 也可以不指定 key 直接使用 when 方法, 但要结合闭包使用
        $this->when(!user()->isManager(), fn() => new ModelScope('user')),

        // 12. whenValue 方法
        // $keyword 取自 $this->value('keyword') 返回的值, 前端没传就是 null, 这个条件就会被过滤掉
        $this->whenValue('keyword', function(Builder $q, $keyword){
            $keyword = '%'.$keyword.'%';
            $q->where('name', 'like', $keyword)->orWhere('nickname', 'like', $keyword);
        }),
        // !!! 注意 !!!
        // 其中 8, 9, 10, 12 都是稍微复杂的查询, 使用他们的目的通常都不是针对一个具体字段进行过滤的,
        // 所以在使用时, 不要再指定字段 key
    ];
}

// value 方法 : 获取参数的值
// 如果值为 null,空字符串或者空数组返回 null (where 方法中 对于值为 null 会自动排除掉)
$this->value('name')
// value 方法第二个参数支持对参数值进一步处理 闭包参数是获取的对应的值
$this->value('name', fn($name) => strtolower($name))
```
#### sorts 排序
```text
支持前端传入多个排序字段

https://foo.bar?sorts=-id,uid 等同于 `order by `id` desc, `uid` asc`

字段前面增加`-`代表倒序, 多个字段用`,`分割

在 filter 类中增加 `order` 方法可以强制指定排序规则
```


