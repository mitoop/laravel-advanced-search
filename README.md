# Laravel advanced search

几乎任何一个系统，都会涉及到搜索，并且可以搜索的项也很可能多。特别是做一些 OA 、ERP 、 CMS 、CRM 等后台系统的时候，各种报表，各种维度的搜索，非常常见。

一个系统会有非常多的列表。每个列表，可能要搜索的字段会有十来个或者更多。搜索的种类有 like 、全等、包含、区间、具体的业务条件等等。

我们之前可能会在 controller 里面写非常多的判断，非常多的 query 查询。就算是一些有经验的程序员，也很头疼该如何设置写这些逻辑，如何写的优雅。

我做了很多的后台系统，深知其中的痛楚，所以有了这个包，来一刀命中要害，让复杂的搜索简单起来，便于维护，容易理解，同时也变得优雅起来。

## 示例和对比

#### 过去你可能这么写

#### 现在你可能这么写

```php
public function wheres()
{
    return [
        'status',
        // 如果传参 status=1 的话 where status = '1'；
        // 如果 status 前端没有传值，那么不会构造

        'created_at.gt' => $this->value('year', fn($year) => carbon($year.'-01-01 00:00:00')),
        // 如果 year 不传值，什么都不会构造。
        //如果传值 year=2018，那么就会执行闭包方法， 根据闭包结果构造 where year>'2018-01-01 00:00:00'

        'name.like' => $this->value('name', fn($name) => $name.'%'),
        // 如果 name 不传值，什么都不会构造。
        // 如果传值 name=张 ，那么就会构造 where name like '张%'

        'id.in' => $this->value('ids'),
        // 如果 ids 不传值，什么都不会构造，因为默认值为 [] ，构造时会被忽略。
        // 如果传值 ids=[1,3,4] ，那么就会构造 where id in (1,3,4)

        'deleted_at.is_not' => true,
        // 如果判断某个字段是否为 null ，使用 is_not 或者 is ，但是注意对应的值不能为 null ，因为值为 null 时，会被自动跳过

        'age' => [
            'gt' => 12,
            'lt' => 16,
        ],
        // where age > 12 and age < 16

        'height' => [
            'gt'  => '180',
            'lt'  => '160',
            'mix' => 'or',
        ],
        // (age > 180 or age < 160)

        DB::raw('3=4'),
        // where 3=4

        function (Builder $q) {
            $q->where('id', 4);
        },
        // where id=4

        new ModelScope('popular'),
        // 会调用的对应的模型  scopePopular 方法

        new ModelScope('older', 60),
        // 等同于
        function (Builder $q) {
            $q->older(60);
        },
         
        // when 方法
        'status' => $this->when(true, 2),
        // where status = 2
        'status' => $this->when(false, 3, 4),
        // where status = 4
        'status' => $this->when(fn() => false, fn() => 3, fn() => 4),
        // 支持闭包 等同于 $this->when(false, 3, 4)
        // where status = 4
    ];
}
```

## 安装

`composer require "mitoop/laravel-advanced-search"`

## 使用

### 丰富的传参内容

#### value
如果传递的参数内容并不能满足需要，还需要进行一些简单的加工，可以这样做：

```php
return [
	'name.like' => $this->value('name', fn($name) => $name.'%')
];
```

这样就能通过 `?name=张` 来获取所有姓张的员工。

`value` 方法
 第一个参数：前端的传参 key
 第二个参数：处理这个传参的内容
 
`value` 行为
如果不能够获取前端传参，那么直接返回 null ，也就是后续处理中会过滤都这条 where 规则
如果能够获取值，那么将获取值传递到闭包，可以自由的进行处理

#### when

>当这个人是被禁用户的时候，我们会额外添加一个搜索条件，不让该用户搜索到任何内容

```php
return [
    'id' => $this->when(user()->locked_at, 0),
];
```

这里会根据 `user()->locked_at` 值进行判断，得到的结果如下

```php
// user()->locked_at 值存在
return [
    'id' => 0,
];

// user()->locked_at 值不存在
return [
    'id' => null,
];
```

根据之前约定的，如果 `键值` 为空值（包括空字符串，但不包括 0 ），这个条件就不会生效

`when` 还有以下用法，满足你的各种需求：
```php
// 如果需要, 三个参数都支持闭包
'your_field_in_mysql' => $this->when(fn() => true, fn() => 2, fn() => 3),
```

#### RAW

原生的 DB::raw 我们也要支持，这个不需要别的，只需要你的语句，只要你会 `sql`，就可以写

```php
return [
    DB::raw('1=0'),
];
```

#### 闭包

如果你的查询*特别*复杂，以上各种形式都满足不了，那么你可以祭出终极大招了

```php
use Illuminate\Database\Eloquent\Builder;

return [
    function (Builder $q) {
        $q->whereHas('role');
    },
];
```

闭包只有一个传参，`$q` 为 `Illuminate\Database\Eloquent\Builder`。看到了这个类，你应该知道如何去使用了吧！

这个就是原生的 `laravel` 查询对象，把所有你需要的查询放里面吧！剩下不多说，自由发挥去吧！

#### and or

查询的时候，经常会有一些逻辑，他们之间可能是 and 或者是 or

```php
return [
    'created_at' => [
        'gt' => '2017-09-01',
        'lt' => '2017-09-30'
    ],
];
```

默认 `created_at` 的 `大于小于` 操作是 `and` 关联，

如果需要 `or` 操作，可以这样写

```php
return [
    'id' => [
        'in'  => [23, 24, 30],
        'lt'  => 25,
        'mix' => 'or'
    ],
];
```


#### 自定义的 laravel 本地作用域

```php
return [
    new ModelScope('listByUser'),
];
```

上面的代码会在执行的时候，会调用模型的 scopeListByUser 方法。

如果需要传参：

```php
return [
    new ModelScope('older', 60),
];
```

上面的代码等同于

```php
function (Builder $q) {
    $q->older(60);
},
```