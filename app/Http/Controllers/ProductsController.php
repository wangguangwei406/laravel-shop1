<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Exceptions\InvalidRequestException;
use App\Models\OrderItem;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Pagination\LengthAwarePaginator;
use App\SearchBuilders\ProductSearchBuilder;
use App\Services\ProductService;

class ProductsController extends Controller
{
    // 使用 Laravel 的依赖注入，自动创建 $categoryService 对象
    // public function index(Request $request, CategoryService $categoryService)
    // {
    //     // 创建一个查询构造器
    //     $builder = Product::query()->where('on_sale', true);
    //     // 判断是否有提交 search 参数，如果有就赋值给 $search 变量
    //     // search 参数用来模糊搜索商品
    //     if ($search = $request->input('search', '')) {
    //         $like = '%'.$search.'%';
    //         // 模糊搜索商品标题、商品详情、SKU 标题、SKU描述
    //         $builder->where(function ($query) use ($like) {
    //             $query->where('title', 'like', $like)
    //                 ->orWhere('description', 'like', $like)
    //                 ->orWhereHas('skus', function ($query) use ($like) {
    //                     $query->where('title', 'like', $like)
    //                         ->orWhere('description', 'like', $like);
    //                 });
    //         });
    //     }

    //     // 如果有传入 category_id 字段，并且在数据库中有对应的类目
    //     if ($request->input('category_id') && $category = Category::find($request->input('category_id'))) {
    //         // 如果这是一个父类目
    //         if ($category->is_directory) {
    //             // 则筛选出该父类目下所有子类目的商品
    //             $builder->whereHas('category', function ($query) use ($category) {
    //                 // 这里的逻辑参考本章第一节
    //                 $query->where('path', 'like', $category->path . $category->id . '-%');
    //             });
    //         } else {
    //             // 如果这不是一个父类目，则直接筛选此类目下的商品
    //             $builder->where('category_id', $category->id);
    //         }
    //     }

    //     // 是否有提交 order 参数，如果有就赋值给 $order 变量
    //     // order 参数用来控制商品的排序规则
    //     if ($order = $request->input('order', '')) {
    //         // 是否是以 _asc 或者 _desc 结尾
    //         if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
    //             // 如果字符串的开头是这 3 个字符串之一，说明是一个合法的排序值
    //             if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
    //                 // 根据传入的排序值来构造排序参数
    //                 $builder->orderBy($m[1], $m[2]);
    //             }
    //         }
    //     }

    //     $products = $builder->paginate(16);

    //     return view('products.index', [
    //         'products' => $products,
    //         // 等价于 isset($category) ? $category : null
    //         'category' => $category ?? null,
    //         // 将类目树传递给模板文件
    //         // 'categoryTree' => $categoryService->getCategoryTree(),
    //         'filters'  => [
    //             'search' => $search,
    //             'order'  => $order,
    //         ],
    //     ]);
    // }

    public function index(Request $request)
    {
        $page    = $request->input('page', 1);
        $perPage = 16;

        // 新建查询构造器对象，设置只搜索上架商品，设置分页
        $builder = (new ProductSearchBuilder())->onSale()->paginate($perPage, $page);

        // 构建查询
        $params = [
            'index' => 'products',
            'body'  => [
                'from'  => ($page - 1) * $perPage, // 通过当前页数与每页数量计算偏移值
                'size'  => $perPage,
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['on_sale' => true]],
                        ],
                    ],
                ],
            ],
        ];

        // 是否有提交 order 参数，如果有就赋值给 $order 变量
        // order 参数用来控制商品的排序规则
        // if ($order = $request->input('order', '')) {
        //     // 是否是以 _asc 或者 _desc 结尾
        //     if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
        //         // 如果字符串的开头是这 3 个字符串之一，说明是一个合法的排序值
        //         if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
        //             // 根据传入的排序值来构造排序参数
        //             $params['body']['sort'] = [[$m[1] => $m[2]]];
        //         }
        //     }
        // }

        if ($order = $request->input('order', '')) {
            if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
                if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
                    // 调用查询构造器的排序
                    $builder->orderBy($m[1], $m[2]);
                }
            }
        }

        // if ($request->input('category_id') && $category = Category::find($request->input('category_id'))) {
        //     if ($category->is_directory) {
        //         // 如果是一个父类目，则使用 category_path 来筛选
        //         $params['body']['query']['bool']['filter'][] = [
        //             'prefix' => ['category_path' => $category->path . $category->id . '-'],
        //         ];
        //     } else {
        //         // 否则直接通过 category_id 筛选
        //         $params['body']['query']['bool']['filter'][] = ['term' => ['category_id' => $category->id]];
        //     }
        // }

        if ($request->input('category_id') && $category = Category::find($request->input('category_id'))) {
            // 调用查询构造器的类目筛选
            $builder->category($category);
        }

        // if ($search = $request->input('search', '')) {
        //     // 将搜索词根据空格拆分成数组，并过滤掉空项
        //     $keywords = array_filter(explode(' ', $search));
        //     $params['body']['query']['bool']['must'] = [];
        //     // $params['body']['query']['bool']['must'] = [
        //     //     [
        //     //         'multi_match' => [
        //     //             'query'  => $search,
        //     //             'fields' => [
        //     //                 'title^3',
        //     //                 'long_title^2',
        //     //                 'category^2', // 类目名称
        //     //                 'description',
        //     //                 'skus_title',
        //     //                 'skus_description',
        //     //                 'properties_value',
        //     //             ],
        //     //         ],
        //     //     ]
        //     // ];
        //     // 遍历搜索词数组，分别添加到 must 查询中
        //     foreach ($keywords as $keyword) {
        //         $params['body']['query']['bool']['must'][] = [
        //             'multi_match' => [
        //                 'query'  => $keyword,
        //                 'fields' => [
        //                     'title^2',
        //                     'long_title^2',
        //                     'category^2',
        //                     'description',
        //                     'skus_title',
        //                     'skus_description',
        //                     'properties_value',
        //                 ],
        //             ],
        //         ];
        //     }
        // }

        if ($search = $request->input('search', '')) {
            $keywords = array_filter(explode(' ', $search));
            // 调用查询构造器的关键词筛选
            $builder->keywords($keywords);
        }

        // 只有当用户有输入搜索词或者使用了类目筛选的时候才会做聚合
        // if ($search || isset($category)) {
        //     $params['body']['aggs'] = [
        //         'properties' => [
        //             'nested' => [
        //                 'path' => 'properties',
        //             ],
        //             'aggs'   => [
        //                 'properties' => [
        //                     'terms' => [
        //                         'field' => 'properties.name',
        //                     ],
        //                     'aggs'  => [
        //                         'value' => [
        //                             'terms' => [
        //                                 'field' => 'properties.value',
        //                             ],
        //                         ],
        //                     ],
        //                 ],
        //             ],
        //         ],
        //     ];
        // }

        if ($search || isset($category)) {
            // 调用查询构造器的分面搜索
            $builder->aggregateProperties();
        }

        // 定义一个数组
        $propertyFilters = [];
        // 从用户请求参数获取 filters
        // if ($filterString = $request->input('filters')) {
        //     // 将获取到的字符串用符号 | 拆分成数组
        //     $filterArray = explode('|', $filterString);
        //     foreach ($filterArray as $filter) {
        //         // 将字符串用符号 : 拆分成两部分并且分别赋值给 $name 和 $value 两个变量
        //         list($name, $value) = explode(':', $filter);

        //         // 将用户筛选的属性添加到数组中
        //         $propertyFilters[$name] = $value;

        //         // 添加到 filter 类型中
        //         // $params['body']['query']['bool']['filter'][] = [
        //         //     // 由于我们要筛选的是 nested 类型下的属性，因此需要用 nested 查询
        //         //     'nested' => [
        //         //         // 指明 nested 字段
        //         //         'path'  => 'properties',
        //         //         'query' => [
        //         //             ['term' => ['properties.name' => $name]],
        //         //             ['term' => ['properties.value' => $value]],
        //         //         ],
        //         //     ],
        //         // ];
        //         $params['body']['query']['bool']['filter'][] = [
        //             'nested' => [
        //                 'path'  => 'properties',
        //                 'query' => [
        //                     // 将原来的两个 term 查询改成一个
        //                     ['term' => ['properties.search_value' => $filter]],
        //                 ],
        //             ],
        //         ];
        //     }
        // }

        if ($filterString = $request->input('filters')) {
            $filterArray = explode('|', $filterString);
            foreach ($filterArray as $filter) {
                list($name, $value) = explode(':', $filter);
                $propertyFilters[$name] = $value;
                // 调用查询构造器的属性筛选
                $builder->propertyFilter($name, $value);
            }
        }

        // $result = app('es')->search($params);
        // 最后通过 getParams() 方法取回构造好的查询参数
        $result = app('es')->search($builder->getParams());

        $properties = [];
        // 如果返回结果里有 aggregations 字段，说明做了分面搜索
        if (isset($result['aggregations'])) {
            // 使用 collect 函数将返回值转为集合
            $properties = collect($result['aggregations']['properties']['properties']['buckets'])
            ->map(function ($bucket) {
                // 通过 map 方法取出我们需要的字段
                return [
                    'key'    => $bucket['key'],
                    'values' => collect($bucket['value']['buckets'])->pluck('key')->all(),
                ];
            })
            ->filter(function ($property) use ($propertyFilters) {
                // 过滤掉只剩下一个值 或者 已经在筛选条件里的属性
                return count($property['values']) > 1 && !isset($propertyFilters[$property['key']]);
            });
        }


        // 通过 collect 函数将返回结果转为集合，并通过集合的 pluck 方法取到返回的商品 ID 数组
        $productIds = collect($result['hits']['hits'])->pluck('_id')->all();
        // 通过 whereIn 方法从数据库中读取商品数据
        // $products = Product::query()
        // ->whereIn('id', $productIds)
        // ->get();
        // $products = Product::query()
        //     ->whereIn('id', $productIds)
        //     // orderByRaw 可以让我们用原生的 SQL 来给查询结果排序
        //     ->orderByRaw(sprintf("FIND_IN_SET(id, '%s')", join(',', $productIds)))
        //     ->get();
        $products = Product::query()->byIds($productIds)->get();
        // 返回一个 LengthAwarePaginator 对象
        $pager = new LengthAwarePaginator($products, $result['hits']['total']['value'], $perPage, $page, [
            'path' => route('products.index', false), // 手动构建分页的 url
        ]);

        return view('products.index', [
            'products' => $pager,
            'filters'  => [
                // 'search' => '',
                'search' => $search,
                'order'  => $order,
            ],
            // 'category' => null,
            'category' => $category ?? null,
            'properties' => $properties,
            'propertyFilters' => $propertyFilters,
        ]);
    }
    // 利用 Laravel 的自动注入来初始化 ProductService 对象
    public function show(Product $product, Request$request, ProductService $service)
    {
        if (!$product->on_sale) {
            throw new InvalidRequestException('商品未上架');
        }

        $favored = false;
        // 用户未登录时返回的是 null，已登录时返回的是对应的用户对象
        if($user = $request->user()) {
            // 从当前用户已收藏的商品中搜索 id 为当前商品 id 的商品
            // boolval() 函数用于把值转为布尔值
            $favored = boolval($user->favoriteProducts()->find($product->id));
        }

        $reviews = OrderItem::query()
            ->with(['order.user', 'productSku']) // 预先加载关联关系
            ->where('product_id', $product->id)
            ->whereNotNull('reviewed_at') // 筛选出已评价的
            ->orderBy('reviewed_at', 'desc') // 按评价时间倒序
            ->limit(10) // 取出 10 条
            ->get();


        // 创建一个查询构造器，只搜索上架的商品，取搜索结果的前 4 个商品
        $builder = (new ProductSearchBuilder())->onSale()->paginate(4, 1);
        // 遍历当前商品的属性
        foreach ($product->properties as $property) {
            // 添加到 should 条件中
            $builder->propertyFilter($property->name, $property->value, 'should');
        }
        // 设置最少匹配一半属性
        $builder->minShouldMatch(ceil(count($product->properties) / 2));
        $params = $builder->getParams();
        // 同时将当前商品的 ID 排除
        $params['body']['query']['bool']['must_not'] = [['term' => ['_id' => $product->id]]];
        // 搜索
        $result = app('es')->search($params);
        // $similarProductIds = collect($result['hits']['hits'])->pluck('_id')->all();
        // // 根据 Elasticsearch 搜索出来的商品 ID 从数据库中读取商品数据
        // $similarProducts   = Product::query()
        //     ->whereIn('id', $similarProductIds)
        //     ->orderByRaw(sprintf("FIND_IN_SET(id, '%s')", join(',', $similarProductIds)))
        //     ->get();
        $similarProductIds = $service->getSimilarProductIds($product, 4);
        // $similarProducts   = Product::query()
        //     ->whereIn('id', $similarProductIds)
        //     ->orderByRaw(sprintf("FIND_IN_SET(id, '%s')", join(',', $similarProductIds)))
        //     ->get();
        $similarProducts   = Product::query()->byIds($similarProductIds)->get();

        // 最后别忘了注入到模板中
        return view('products.show', [
            'product' => $product,
            'favored' => $favored,
            'reviews' => $reviews,
            'similar' => $similarProducts,
        ]);
        // // 最后别忘了注入到模板中
        // return view('products.show', [
        //     'product' => $product,
        //     'favored' => $favored,
        //     'reviews' => $reviews
        // ]);
    }

    public function favor(Product $product, Request $request)
    {
        $user = $request->user();
        if ($user->favoriteProducts()->find($product->id)) {
            return [];
        }

        $user->favoriteProducts()->attach($product);

        return [];
    }

    public function disfavor(Product $product, Request $request)
    {
        $user = $request->user();
        $user->favoriteProducts()->detach($product);

        return [];
    }

    public function favorites(Request $request)
    {
        $products = $request->user()->favoriteProducts()->paginate(16);

        return view('products.favorites', ['products' => $products]);
    }
}
