<?php

namespace FarukHaiderBD\ShoppingCart\Services;

use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Events\Dispatcher;
use FarukHaiderBD\ShoppingCart\Collection\Item;
use Exception;

class Session
{
    protected $session;

    protected $event;

    protected $name = 'cart.session';

    public function __construct(SessionManager $session, Dispatcher $event)
    {
    $this->session = $session;

    $this->event = $event;
    }
    public function name($name)
    {
    $this->name = $name;
    return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function all()
    {
        return $this->getCart();
   }


   protected function getCart()
    {
        $cart = $this->session->get($this->name);

        return $cart instanceof Collection ? $cart : new Collection();
    }
    /**
 *  Associate as Eloquent Model to Shopping Cart
 *
 * @param $model
 * @return $this
 * @throws Exception
 */
    public function associate($model)
    {
        if (!class_exists($model)) {
            throw new Exception("Invalid model name '$model'.");
        }
        $this->model = $model;
        return $this;
    }

/**
 *  Get the associated Eloquent Model attached with Shopping Cart
 *
 * @return mixed
 */
    public function getModel()
    {
        return $this->model;
    }

    public function get($rawId)
{
    $row = $this->getCart()->get($rawId);

    return is_null($row) ? null : new Item($row);
}

protected function generateRawId($id, $attributes)
{
    ksort($attributes);

    return md5($id.serialize($attributes));
}

public function add($id, $name = null, $qty = null, $price = null, array $attributes = [])
{

    $cart = $this->getCart();

    $this->event->push('cart.adding', [$attributes, $cart]);

    $row = $this->addRow($id, $name, $qty, $price, $attributes);

    $this->event->push('cart.added', [$attributes, $cart]);

    return $row;
}
protected function addRow($id, $name, $qty, $price, array $attributes = [])
{


    if (!is_numeric($qty) || $qty < 1)
    {
        throw new Exception('Invalid quantity.');
    }

    if (!is_numeric($price) || $price < 0)
    {
        throw new Exception('Invalid price.');
    }
    $cart = $this->getCart();

    $rawId = $this->generateRawId($id, $attributes);

    if ($row = $cart->get($rawId))
    {
        $row = $this->updateQty($rawId, $row->qty + $qty);
    } else
    {
        $row = $this->insertRow($rawId, $id, $name, $qty, $price, $attributes);
    }

    return $row;
}

protected function updateQty($rawId, $qty)
{
    if ($qty <= 0) {
        return $this->remove($rawId);
    }
    return $this->updateRow($rawId, ['qty' => $qty]);
}
public function remove($rawId)
{
    if (!$row = $this->get($rawId))
    {
        return true;
    }

    $cart = $this->getCart();

    $this->event->push('cart.removing', [$row, $cart]);
    $cart->forget($rawId);

    $this->event->push('cart.removed', [$row, $cart]);

    $this->save($cart);

    return true;
}

protected function updateRow($rawId, array $attributes)
{
    $cart = $this->getCart();
    $row = $cart->get($rawId);
    foreach ($attributes as $key => $value)
    {
        $row->put($key, $value);
    }

    if (count(array_intersect(array_keys($attributes), ['qty', 'price'])))
    {
        $row->put('total', $row->qty * $row->price);
    }

    $cart->put($rawId, $row);

    return $row;
}
protected function insertRow($rawId, $id, $name, $qty, $price, $attributes = [])
{
    $newRow = $this->makeRow($rawId, $id, $name, $qty, $price, $attributes);

    $cart = $this->getCart();

    $cart->put($rawId, $newRow);

    $this->save($cart);

    return $newRow;
}
protected function makeRow($rawId, $id, $name, $qty, $price, array $attributes = [])
{
    return new Item(array_merge(
        [
            '__raw_id' => $rawId,
            'id' => $id,
            'name' => $name,
            'qty' => $qty,
            'price' => $price,
            'total' => $qty * $price,
            // '__model' => $this->model,
        ],
        $attributes));
}

public function update($rawId, $attribute)
{
    if (!$row = $this->get($rawId))
    {
        throw new Exception('Item not found.');
    }

    $cart = $this->getCart();

    $this->event->push('cart.updating', [$row, $cart]);

    if (is_array($attribute))
    {
        $raw = $this->updateAttribute($rawId, $attribute);
    } else
    {
        $raw = $this->updateQty($rawId, $attribute);
    }

    $this->event->push('cart.updated', [$row, $cart]);

    return $raw;
}

protected function updateAttribute($rawId, $attributes)
{
    return $this->updateRow($rawId, $attributes);
}
protected function save($cart)
{
    $this->session->put($this->name, $cart);
    return $cart;
}
public function destroy()
{
    $cart = $this->getCart();

    $this->event->push('cart.destroying', $cart);

    $this->save(null);

    $this->event->push('cart.destroyed', $cart);

    return true;
}

public function clean()
{
    $this->destroy();
}
public function total()
{
    return $this->totalPrice();
}
public function totalPrice()
{
    $total = 0;

    $cart = $this->getCart();

    if ($cart->isEmpty())
    {
        return $total;
    }

    foreach ($cart as $row)
    {
        $total += $row->qty * $row->price;
    }

    return $total;
}
public function isEmpty()
{
    return $this->count() <= 0;
}

public function count($totalItems = true)
{
    $items = $this->getCart();
    if (!$totalItems)
    {
        return $items->count();
    }

    $count = 0;

    foreach ($items as $row)
    {
        $count += $row->qty;
    }

    return $count;
}
public function countRows()
{
    return $this->count(false);
}
public function search(array $search)
{
    $rows = new Collection();
    if (empty($search))
    {
        return $rows;
    }

    foreach ($this->getCart() as $item)
    {
        if (array_intersect_assoc($item->intersect($search)->toArray(), $search))
        {
            $rows->put($item->__raw_id, $item);
        }
    }
    return $rows;
}



}
