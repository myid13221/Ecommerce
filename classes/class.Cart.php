<?php

/**
 * Cart: A very simple PHP cart library.
 *
 * Copyright (c) 2017 Sei Kan
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright  2017 Sei Kan <seikan.dev@gmail.com>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 *
 * @see       https://github.com/seikan/Cart
 */
class Cart
{
	/**
	 * An unique ID for the cart.
	 *
	 * @var string
	 */
	protected $cartId;

	/**
	 * Maximum item allowed in the cart.
	 *
	 * @var int
	 */
	protected $cartMaxItem = 0;

	/**
	 * Maximum quantity of a item allowed in the cart.
	 *
	 * @var int
	 */
	protected $itemMaxQuantity = 200000;

	/**
	 * Enable or disable cookie.
	 *
	 * @var bool
	 */
	protected $useCookie = false;

	/**
	 * A collection of cart items.
	 *
	 * @var array
	 */
	private $items = [];

	/**
	 * Initialize cart.
	 *
	 * @param array $options
	 */
	public function __construct($options = [])
        {
		if (!session_id()) {
		    session_start();
		}
	
		$this->cartMaxItem = isset($options['cartMaxItem']) && is_numeric($options['cartMaxItem']) ? $options['cartMaxItem'] : $this->cartMaxItem;
		$this->itemMaxQuantity = isset($options['itemMaxQuantity']) && is_numeric($options['itemMaxQuantity']) ? $options['itemMaxQuantity'] : $this->itemMaxQuantity;
		$this->useCookie = isset($options['useCookie']) && $options['useCookie'];
	
		$this->cartId = md5(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'SimpleCart') . '_cart';
		$this->read();
        }

	/**
	 * Get items in  cart.
	 *
	 * @return array
	 */
	public function getItems()
	{
		return $this->items;
	}

	/**
	 * Check if the cart is empty.
	 *
	 * @return bool
	 */
	public function isEmpty()
	{
		return empty(array_filter($this->items));
	}

	/**
	 * Get the total of item in cart.
	 *
	 * @return int
	 */
	public function getTotalItem()
	{
		$total = 0;

		foreach ($this->items as $items) {
			foreach ($items as $item) {
				++$total;
			}
		}

		return $total;
	}

	/**
	 * Get the total of item quantity in cart.
	 *
	 * @return int
	 */
	public function getTotalQuantity()
	{
		$quantity = 0;

		foreach ($this->items as $items) {
			foreach ($items as $item) {
				$quantity += $item['quantity'];
			}
		}

		return $quantity;
	}

	/**
	 * Get the sum of a attribute from cart.
	 *
	 * @param string $attribute
	 *
	 * @return int
	 */
	public function getAttributeTotal($attribute = 'price')
	{
		$total = 0;

		foreach ($this->items as $items) {
			foreach ($items as $item) {
				if (isset($item['attributes'][$attribute])) {
					$total += $item['attributes'][$attribute] * $item['quantity'];
				}
			}
		}

		return $total;
	}

	/**
	 * Remove all items from cart.
	 */
	public function clear()
	{
		$this->items = [];
		$this->write();
	}

	/**
	 * Check if a item exist in cart.
	 *
	 * @param string $id
	 * @param array  $attributes
	 *
	 * @return bool
	 */
	public function isItemExists($id, $attributes = [])
	{
		$attributes = (is_array($attributes)) ? array_filter($attributes) : [$attributes];

		if (isset($this->items[$id])) {
			$hash = md5(json_encode($attributes));
			foreach ($this->items[$id] as $item) {
				if ($item['hash'] == $hash) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Add item to cart.
	 *
	 * @param string $id
	 * @param int    $quantity
	 * @param array  $attributes
	 *
	 * @return bool
	 */
	public function add($id, $quantity = 1, $attributes = [])
	{
		$quantity = (preg_match('/^\d+$/', $quantity)) ? $quantity : 1;
		$attributes = (is_array($attributes)) ? array_filter($attributes) : [$attributes];
		$hash = md5(json_encode($attributes));

		if (isset($this->items[$id])) {
			$index = 0;
			foreach ($this->items[$id] as $item) {
				if ($item['hash'] == $hash) {
					$this->items[$id][$index]['quantity'] += $quantity;

					$this->items[$id][$index]['quantity'] = ($this->itemMaxQuantity < $this->items[$id][$index]['quantity']) ? $this->itemMaxQuantity : $this->items[$id][$index]['quantity'];

					$this->write();

					return true;
				}
				++$index;
			}
		}

		$this->items[$id][] = [
			'quantity'   => ($quantity > $this->itemMaxQuantity) ? $this->itemMaxQuantity : $quantity,
			'hash'       => $hash,
			'attributes' => $attributes,
		];

		$this->write();

		return true;
	}

	/**
	 * Update item quantity.
	 *
	 * @param string $id
	 * @param int    $quantity
	 * @param array  $attributes
	 *
	 * @return bool
	 */
	public function update($id, $quantity = 1, $attributes = [])
	{
		$quantity = (preg_match('/^\d+$/', $quantity)) ? $quantity : 1;

		if ($quantity == 0) {
			$this->remove($id, $attributes);

			return true;
		}

		if (isset($this->items[$id])) {
			$hash = md5(json_encode(array_filter($attributes)));

			$index = 0;
			foreach ($this->items[$id] as $item) {
				if ($item['hash'] == $hash) {
					$this->items[$id][$index]['quantity'] = $quantity;
					$this->items[$id][$index]['quantity'] = ($this->itemMaxQuantity < $this->items[$id][$index]['quantity']) ? $this->itemMaxQuantity : $this->items[$id][$index]['quantity'];

					$this->write();

					return true;
				}
				++$index;
			}
		}

		return false;
	}

	/**
	 * Remove item from cart.
	 *
	 * @param string $id
	 * @param array  $attributes
	 *
	 * @return bool
	 */
	public function remove($id, $attributes = [])
	{
		if (!isset($this->items[$id])) {
			return false;
		}

		if (empty($attributes)) {
			unset($this->items[$id]);

			$this->write();

			return true;
		}
		$hash = md5(json_encode(array_filter($attributes)));
		$index = 0;

		foreach ($this->items[$id] as $item) {
			if ($item['hash'] == $hash) {
				unset($this->items[$id][$index]);

				$this->write();

				return true;
			}
			++$index;
		}

		return false;
	}

	/**
	 * Destroy cart session.
	 */
	public function destroy()
	{
		$this->items = [];

		if ($this->useCookie) {
			setcookie($this->cartId, '', -1);
		} else {
			unset($_SESSION[$this->cartId]);
		}
	}

	/**
	 * Read items from cart session.
	 */
	private function read()
	{
		$this->items = ($this->useCookie) ? json_decode((isset($_COOKIE[$this->cartId])) ? $_COOKIE[$this->cartId] : '[]', true) : json_decode((isset($_SESSION[$this->cartId])) ? $_SESSION[$this->cartId] : '[]', true);
	}

	/**
	 * Write changes into cart session.
	 */
	private function write()
	{
		if ($this->useCookie) {
			setcookie($this->cartId, json_encode(array_filter($this->items)), time() + 604800);
		} else {
			$_SESSION[$this->cartId] = json_encode(array_filter($this->items));
		}
	}
}
