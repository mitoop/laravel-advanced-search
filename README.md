# Laravel advanced search

几乎任何一个系统，都会涉及到搜索，并且可以搜索的项也很可能多。特别是做一些 OA 、ERP 、 CMS 、CRM 等后台系统的时候，各种报表，各种维度的搜索，非常常见。

一个系统会有非常多的列表。每个列表，可能要搜索的字段会有十来个或者更多。搜索的种类有 like 、全等、包含、区间、具体的业务条件等等。

我们之前可能会在 controller 里面写非常多的判断，非常多的 query 查询。就算是一些有经验的程序员，也很头疼该如何优雅的设置这些逻辑。

我做了很多的后台系统，深知其中的痛楚，所以有了这个包，来一刀命中要害，让复杂的搜索简单起来，便于维护，容易理解，同时也变得优雅起来。

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
            Admin::advanced(new AdminFilter)->paginate(page_size())
            // 调用`advanced`方法后还可以继续调用`Builder`的其他方法
        );
    }
}    
    
// AdminFilter 使用 `make:filter` 命令生成
<?php

namespace App\Http\Filters\Admin;

use Mitoop\Query\ConditionsGenerator;

class AdminFilter extends ConditionsGenerator
{
    // 构建 where 条件
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
        // 简单字段
        // 如果传参 status=1 的话 where status = '1'；
        // 如果 status 前端没有传值，那么不会构造
        'status',

        // gt 运算符
        // 如果 year 不传值，什么都不会构造。
        // 如果传值 year=2018，那么就会执行闭包方法， 根据闭包结果构造 where year>'2018-01-01 00:00:00'
        'created_at.gt' => $this->value('year', fn($year) => carbon($year.'-01-01 00:00:00')),

        // like 运算符
        // 如果 name 不传值，什么都不会构造。
        // 如果传值 name=张 ，那么就会构造 where name like '张%'
        'name.like' => $this->value('name', fn($name) => $name.'%'),

        // int 运算符
        // 如果 ids 不传值，什么都不会构造
        // 如果传值 ids=[1,3,4] ，那么就会构造 where id in (1,3,4)
        'id.in' => $this->value('ids'),

        // not null 运算符
        // 如果判断某个字段是否为 null ，使用 is_not 或者 is ，
        // 但是注意对应的值不能为 null ，因为值为 null 时，会被自动跳过
        'deleted_at.is_not' => true,

        // 多运算符
        // where age > 12 and age < 16
        'age' => [
            'gt' => 12,
            'lt' => 16,
        ],

        // 多运算符以及逻辑关系
        // where age > 180 or age < 160
        'height' => [
            'gt'  => '180',
            'lt'  => '160',
            'mix' => 'or',
        ],

        // 支持 Laravel DB Expression
        // where 3=4
        DB::raw('3=4'),

        // 支持闭包
        // where id=4
        // Builder 是 `Illuminate\Database\Eloquent\Builder` 对象
        function (Builder $q) {
            $q->where('id', 4);
        },
        
        // 支持局部作用域 
        // 会调用的对应的模型  scopePopular 方法
        new ModelScope('popular'),
        // 局部作用域参数
        new ModelScope('older', 60),
        // 等同于
        function (Builder $q) {
            $q->older(60);
        },
                 
        // when 条件
        // where status = 2
        'status' => $this->when(true, 2),
        // where status = 4
        'status' => $this->when(false, 3, 4),
        // 三个参数都支持闭包 等同于 $this->when(false, 3, 4)
        // where status = 4
        'status' => $this->when(fn() => false, fn() => 3, fn() => 4),
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


