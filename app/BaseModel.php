<?php

namespace App;


use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 基础模型
 *
 * @mixin Builder
 * @method static static first(...$columns)
 * @method static static firstOrFail(...$columns)
 * @method static static select(...$columns)
 * @method static static chunk($count, callable $callback)
 * @method static static create(array $attributes = [])
 * @method static static find($id, $columns = ['*'])
 * @method static static findOrFail($id, $columns = ['*'])
 * @method static static when($value, $callback, $default = null)
 * @method static static where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static static whereIn($column, $values, $boolean = 'and', $not = false)
 * @method static static whereDate($column, $operator, $value = null, $boolean = 'and')
 * @method static static orderBy($column, $direction = 'asc')
 * @method static static orderByDesc($column)
 * @method static static withTrashed()
 * @method static static has($relation, $operator = '>=', $count = 1, $boolean = 'and', Closure $callback = null)
 * @method static LengthAwarePaginator paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
 * @method int increment($column, $amount = 1, array $extra = [])
 * @method
 * @mixin SoftDeletes
 */
class BaseModel extends Model
{

}