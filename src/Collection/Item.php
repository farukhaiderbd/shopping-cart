<?php
namespace FarukHaiderBD\ShoppingCart\Collection;

use Illuminate\Support\Collection;

class Item extends Collection
{
    protected $model;

    public function __get($property)
{
    if ($this->has($property))
    {
        return $this->get($property);
    }

    // Below code will be used for the shopping cart when,
    // we change the storage type to the database.

    if (!$this->get('__model'))
    {
        return;
    }

    $model = $this->get('__model');

    $class = explode('\\', $model);

    if (strtolower(end($class)) === $property)
    {
        $model = new $model();

        return $model->find($this->id);
    }

    return;
}
public function rawId()
{
    return $this->__raw_id;
}
}
