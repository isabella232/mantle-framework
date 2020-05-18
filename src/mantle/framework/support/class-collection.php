<?php
/**
 * Collections class file.
 *
 * @package Mantle
 */

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamComment

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag

namespace Mantle\Framework\Support;

use ArrayAccess;
use ArrayIterator;
use Mantle\Framework\Support\Traits\Enumerates_Values;
use function Mantle\Framework\Helpers\value;
use stdClass;

/**
 * Collection
 */
class Collection implements ArrayAccess, Enumerable {
	use Enumerates_Values;

	/**
	 * The items contained in the collection.
	 *
	 * @var array
	 */
	protected $items = [];

	/**
	 * Create a new collection.
	 *
	 * @param    mixed $items
	 * @return void
	 */
	public function __construct( $items = [] ) {
		$this->items = $this->get_arrayable_items( $items );
	}

	/**
	 * Create a new collection by invoking the callback a given amount of times.
	 *
	 * @param    int           $number
	 * @param    callable|null $callback
	 * @return static
	 */
	public static function times( $number, callable $callback = null ) {
		if ( $number < 1 ) {
			return new static();
		}

		if ( is_null( $callback ) ) {
			return new static( range( 1, $number ) );
		}

		return ( new static( range( 1, $number ) ) )->map( $callback );
	}

	/**
	 * Get all of the items in the collection.
	 *
	 * @return array
	 */
	public function all() {
		return $this->items;
	}

	/**
	 * Get a lazy collection for the items in this collection.
	 *
	 * @return \Mantle\Framework\Support\LazyCollection
	 */
	public function lazy() {
		return new LazyCollection( $this->items );
	}

	/**
	 * Get the average value of a given key.
	 *
	 * @param    callable|string|null $callback
	 * @return mixed
	 */
	public function avg( $callback = null ) {
		$callback = $this->value_retriever( $callback );

		$items = $this->map(
			function ( $value ) use ( $callback ) {
				return $callback( $value );
			}
		)->filter(
			function ( $value ) {
				return ! is_null( $value );
			}
		);

		$count = $items->count();
		if ( $count ) {
			return $items->sum() / $count;
		}
	}

	/**
	 * Get the median of a given key.
	 *
	 * @param    string|array|null $key
	 * @return mixed
	 */
	public function median( $key = null ) {
		$values = ( isset( $key ) ? $this->pluck( $key ) : $this )
				->filter(
					function ( $item ) {
						return ! is_null( $item );
					}
				)->sort()->values();


		$count = $values->count();

		if ( 0 === $count ) {
			return;
		}

		$middle = (int) ( $count / 2 );

		if ( $count % 2 ) {
			return $values->get( $middle );
		}

		return ( new static(
			[
				$values->get( $middle - 1 ),
				$values->get( $middle ),
			]
		) )->average();
	}

	/**
	 * Get the mode of a given key.
	 *
	 * @param    string|array|null $key
	 * @return array|null
	 */
	public function mode( $key = null ) {
		if ( $this->count() === 0 ) {
			return;
		}

		$collection = isset( $key ) ? $this->pluck( $key ) : $this;

		$counts = new self();

		$collection->each(
			function ( $value ) use ( $counts ) {
				$counts[ $value ] = isset( $counts[ $value ] ) ? $counts[ $value ] + 1 : 1;
			}
		);

		$sorted = $counts->sort();

		$highest_value = $sorted->last();

		return $sorted->filter(
			function ( $value ) use ( $highest_value ) {
				return $value == $highest_value;
			}
		)->sort()->keys()->all();
	}

	/**
	 * Collapse the collection of items into a single array.
	 *
	 * @return static
	 */
	public function collapse() {
		return new static( Arr::collapse( $this->items ) );
	}

	/**
	 * Determine if an item exists in the collection.
	 *
	 * @param    mixed $key
	 * @param    mixed $operator
	 * @param    mixed $value
	 * @return bool
	 */
	public function contains( $key, $operator = null, $value = null ) {
		if ( func_num_args() === 1 ) {
			if ( $this->use_as_callable( $key ) ) {
				$placeholder = new stdClass();

				return $this->first( $key, $placeholder ) !== $placeholder;
			}

			return in_array( $key, $this->items );
		}

		return $this->contains( $this->operator_for_where( ...func_get_args() ) );
	}

	/**
	 * Cross join with the given lists, returning all possible permutations.
	 *
	 * @param    mixed ...$lists
	 * @return static
	 */
	public function cross_join( ...$lists ) {
		return new static(
			Arr::cross_join(
				$this->items,
				...array_map( [ $this, 'get_arrayable_items' ], $lists )
			)
		);
	}

	/**
	 * Get the items in the collection that are not present in the given items.
	 *
	 * @param    mixed $items
	 * @return static
	 */
	public function diff( $items ) {
		return new static( array_diff( $this->items, $this->get_arrayable_items( $items ) ) );
	}

	/**
	 * Get the items in the collection that are not present in the given items, using the callback.
	 *
	 * @param    mixed    $items
	 * @param    callable $callback
	 * @return static
	 */
	public function diff_using( $items, callable $callback ) {
		return new static( array_udiff( $this->items, $this->get_arrayable_items( $items ), $callback ) );
	}

	/**
	 * Get the items in the collection whose keys and values are not present in the given items.
	 *
	 * @param    mixed $items
	 * @return static
	 */
	public function diff_assoc( $items ) {
		return new static( array_diff_assoc( $this->items, $this->get_arrayable_items( $items ) ) );
	}

	/**
	 * Get the items in the collection whose keys and values are not present in the given items, using the callback.
	 *
	 * @param    mixed    $items
	 * @param    callable $callback
	 * @return static
	 */
	public function diff_assoc_using( $items, callable $callback ) {
		return new static( array_diff_uassoc( $this->items, $this->get_arrayable_items( $items ), $callback ) );
	}

	/**
	 * Get the items in the collection whose keys are not present in the given items.
	 *
	 * @param    mixed $items
	 * @return static
	 */
	public function diff_keys( $items ) {
		return new static( array_diff_key( $this->items, $this->get_arrayable_items( $items ) ) );
	}

	/**
	 * Get the items in the collection whose keys are not present in the given items, using the callback.
	 *
	 * @param    mixed    $items
	 * @param    callable $callback
	 * @return static
	 */
	public function diff_keys_using( $items, callable $callback ) {
		return new static( array_diff_ukey( $this->items, $this->get_arrayable_items( $items ), $callback ) );
	}

	/**
	 * Retrieve duplicate items from the collection.
	 *
	 * @param    callable|null $callback
	 * @param    bool          $strict
	 * @return static
	 */
	public function duplicates( $callback = null, $strict = false ) {
		$items = $this->map( $this->value_retriever( $callback ) );

		$unique_items = $items->unique( null, $strict );

		$compare = $this->duplicate_comparator( $strict );

		$duplicates = new static();

		foreach ( $items as $key => $value ) {
			if ( $unique_items->is_not_empty() && $compare( $value, $unique_items->first() ) ) {
				$unique_items->shift();
			} else {
				$duplicates[ $key ] = $value;
			}
		}

		return $duplicates;
	}

	/**
	 * Retrieve duplicate items from the collection using strict comparison.
	 *
	 * @param    callable|null $callback
	 * @return static
	 */
	public function duplicates_strict( $callback = null ) {
		return $this->duplicates( $callback, true );
	}

	/**
	 * Get the comparison function to detect duplicates.
	 *
	 * @param    bool $strict
	 * @return \Closure
	 */
	protected function duplicate_comparator( $strict ) {
		if ( $strict ) {
			return function ( $a, $b ) {
				return $a === $b;
			};
		}

		return function ( $a, $b ) {
			return $a == $b;
		};
	}

	/**
	 * Get all items except for those with the specified keys.
	 *
	 * @param    \Mantle\Framework\Support\Collection|mixed $keys
	 * @return static
	 */
	public function except( $keys ) {
		if ( $keys instanceof Enumerable ) {
			$keys = $keys->all();
		} elseif ( ! is_array( $keys ) ) {
			$keys = func_get_args();
		}

		return new static( Arr::except( $this->items, $keys ) );
	}

	/**
	 * Run a filter over each of the items.
	 *
	 * @param    callable|null $callback
	 * @return static
	 */
	public function filter( callable $callback = null ) {
		if ( $callback ) {
			return new static( Arr::where( $this->items, $callback ) );
		}

		return new static( array_filter( $this->items ) );
	}

	/**
	 * Get the first item from the collection passing the given truth test.
	 *
	 * @param    callable|null $callback
	 * @param    mixed         $default
	 * @return mixed
	 */
	public function first( callable $callback = null, $default = null ) {
		return Arr::first( $this->items, $callback, $default );
	}

	/**
	 * Get a flattened array of the items in the collection.
	 *
	 * @param    int $depth
	 * @return static
	 */
	public function flatten( $depth = INF ) {
		return new static( Arr::flatten( $this->items, $depth ) );
	}

	/**
	 * Flip the items in the collection.
	 *
	 * @return static
	 */
	public function flip() {
		return new static( array_flip( $this->items ) );
	}

	/**
	 * Remove an item from the collection by key.
	 *
	 * @param    string|array $keys
	 * @return $this
	 */
	public function forget( $keys ) {
		foreach ( (array) $keys as $key ) {
			$this->offsetUnset( $key );
		}

		return $this;
	}

	/**
	 * Get an item from the collection by key.
	 *
	 * @param    mixed $key
	 * @param    mixed $default
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( $this->offsetExists( $key ) ) {
			return $this->items[ $key ];
		}

		return value( $default );
	}

	/**
	 * Group an associative array by a field or using a callback.
	 *
	 * @param    array|callable|string $group_by
	 * @param    bool                  $preserve_keys
	 * @return static
	 */
	public function group_by( $group_by, $preserve_keys = false ) {
		if ( ! $this->use_as_callable( $group_by ) && is_array( $group_by ) ) {
			$next_groups = $group_by;

			$group_by = array_shift( $next_groups );
		}

		$group_by = $this->value_retriever( $group_by );

		$results = [];

		foreach ( $this->items as $key => $value ) {
			$group_keys = $group_by( $value, $key );

			if ( ! is_array( $group_keys ) ) {
				$group_keys = [ $group_keys ];
			}

			foreach ( $group_keys as $group_key ) {
				$group_key = is_bool( $group_key ) ? (int) $group_key : $group_key;

				if ( ! array_key_exists( $group_key, $results ) ) {
					$results[ $group_key ] = new static();
				}

				$results[ $group_key ]->offsetSet( $preserve_keys ? $key : null, $value );
			}
		}

		$result = new static( $results );

		if ( ! empty( $next_groups ) ) {
			return $result->map->groupBy( $next_groups, $preserve_keys );
		}

		return $result;
	}

	/**
	 * Key an associative array by a field or using a callback.
	 *
	 * @param    callable|string $key_by
	 * @return static
	 */
	public function key_by( $key_by ) {
		$key_by = $this->value_retriever( $key_by );

		$results = [];

		foreach ( $this->items as $key => $item ) {
			$resolved_key = $key_by( $item, $key );

			if ( is_object( $resolved_key ) ) {
				$resolved_key = (string) $resolved_key;
			}

			$results[ $resolved_key ] = $item;
		}

		return new static( $results );
	}

	/**
	 * Determine if an item exists in the collection by key.
	 *
	 * @param    mixed $key
	 * @return bool
	 */
	public function has( $key ) {
		$keys = is_array( $key ) ? $key : func_get_args();

		foreach ( $keys as $value ) {
			if ( ! $this->offsetExists( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Concatenate values of a given key as a string.
	 *
	 * @param    string      $value
	 * @param    string|null $glue
	 * @return string
	 */
	public function implode( $value, $glue = null ) {
		$first = $this->first();

		if ( is_array( $first ) || is_object( $first ) ) {
			return implode( $glue, $this->pluck( $value )->all() );
		}

		return implode( $value, $this->items );
	}

	/**
	 * Intersect the collection with the given items.
	 *
	 * @param    mixed $items
	 * @return static
	 */
	public function intersect( $items ) {
		return new static( array_intersect( $this->items, $this->get_arrayable_items( $items ) ) );
	}

	/**
	 * Intersect the collection with the given items by key.
	 *
	 * @param    mixed $items
	 * @return static
	 */
	public function intersect_by_keys( $items ) {
		return new static(
			array_intersect_key(
				$this->items,
				$this->get_arrayable_items( $items )
			)
		);
	}

	/**
	 * Determine if the collection is empty or not.
	 *
	 * @return bool
	 */
	public function is_empty() {
		return empty( $this->items );
	}

	/**
	 * Join all items from the collection using a string. The final items can use a separate glue string.
	 *
	 * @param    string $glue
	 * @param    string $final_glue
	 * @return string
	 */
	public function join( $glue, $final_glue = '' ) {
		if ( '' === $final_glue ) {
			return $this->implode( $glue );
		}

		$count = $this->count();

		if ( 0 === $count ) {
			return '';
		}

		if ( 1 === $count ) {
			return $this->last();
		}

		$collection = new static( $this->items );

		$final_item = $collection->pop();

		return $collection->implode( $glue ) . $final_glue . $final_item;
	}

	/**
	 * Get the keys of the collection items.
	 *
	 * @return static
	 */
	public function keys() {
		return new static( array_keys( $this->items ) );
	}

	/**
	 * Get the last item from the collection.
	 *
	 * @param    callable|null $callback
	 * @param    mixed         $default
	 * @return mixed
	 */
	public function last( callable $callback = null, $default = null ) {
		return Arr::last( $this->items, $callback, $default );
	}

	/**
	 * Get the values of a given key.
	 *
	 * @param    string|array $value
	 * @param    string|null  $key
	 * @return static
	 */
	public function pluck( $value, $key = null ) {
		return new static( Arr::pluck( $this->items, $value, $key ) );
	}

	/**
	 * Run a map over each of the items.
	 *
	 * @param    callable $callback
	 * @return static
	 */
	public function map( callable $callback ) {
		$keys = array_keys( $this->items );

		$items = array_map( $callback, $this->items, $keys );

		return new static( array_combine( $keys, $items ) );
	}

	/**
	 * Run a dictionary map over the items.
	 *
	 * The callback should return an associative array with a single key/value pair.
	 *
	 * @param    callable $callback
	 * @return static
	 */
	public function map_to_dictionary( callable $callback ) {
		$dictionary = [];

		foreach ( $this->items as $key => $item ) {
			$pair = $callback( $item, $key );

			$key = key( $pair );

			$value = reset( $pair );

			if ( ! isset( $dictionary[ $key ] ) ) {
				$dictionary[ $key ] = [];
			}

			$dictionary[ $key ][] = $value;
		}

		return new static( $dictionary );
	}

	/**
	 * Run an associative map over each of the items.
	 *
	 * The callback should return an associative array with a single key/value pair.
	 *
	 * @param    callable $callback
	 * @return static
	 */
	public function map_with_keys( callable $callback ) {
		$result = [];

		foreach ( $this->items as $key => $value ) {
			$assoc = $callback( $value, $key );

			foreach ( $assoc as $map_key => $map_value ) {
				$result[ $map_key ] = $map_value;
			}
		}

		return new static( $result );
	}

	/**
	 * Merge the collection with the given items.
	 *
	 * @param    mixed $items
	 * @return static
	 */
	public function merge( $items ) {
		return new static( array_merge( $this->items, $this->get_arrayable_items( $items ) ) );
	}

	/**
	 * Recursively merge the collection with the given items.
	 *
	 * @param    mixed $items
	 * @return static
	 */
	public function merge_recursive( $items ) {
		return new static( array_merge_recursive( $this->items, $this->get_arrayable_items( $items ) ) );
	}

	/**
	 * Create a collection by using this collection for keys and another for its values.
	 *
	 * @param    mixed $values
	 * @return static
	 */
	public function combine( $values ) {
		return new static( array_combine( $this->all(), $this->get_arrayable_items( $values ) ) );
	}

	/**
	 * Union the collection with the given items.
	 *
	 * @param    mixed $items
	 * @return static
	 */
	public function union( $items ) {
		return new static( $this->items + $this->get_arrayable_items( $items ) );
	}

	/**
	 * Create a new collection consisting of every n-th element.
	 *
	 * @param    int $step
	 * @param    int $offset
	 * @return static
	 */
	public function nth( $step, $offset = 0 ) {
		$new = [];

		$position = 0;

		foreach ( $this->items as $item ) {
			if ( $position % $step === $offset ) {
				$new[] = $item;
			}

			$position++;
		}

		return new static( $new );
	}

	/**
	 * Get the items with the specified keys.
	 *
	 * @param    mixed $keys
	 * @return static
	 */
	public function only( $keys ) {
		if ( is_null( $keys ) ) {
			return new static( $this->items );
		}

		if ( $keys instanceof Enumerable ) {
			$keys = $keys->all();
		}

		$keys = is_array( $keys ) ? $keys : func_get_args();

		return new static( Arr::only( $this->items, $keys ) );
	}

	/**
	 * Get and remove the last item from the collection.
	 *
	 * @return mixed
	 */
	public function pop() {
		return array_pop( $this->items );
	}

	/**
	 * Push an item onto the beginning of the collection.
	 *
	 * @param    mixed $value
	 * @param    mixed $key
	 * @return $this
	 */
	public function prepend( $value, $key = null ) {
		$this->items = Arr::prepend( $this->items, $value, $key );

		return $this;
	}

	/**
	 * Push one or more items onto the end of the collection.
	 *
	 * @param    mixed ...$values [optional].
	 * @return $this
	 */
	public function push( ...$values ) {
		foreach ( $values as $value ) {
			$this->items[] = $value;
		}

		return $this;
	}

	/**
	 * Push all of the given items onto the collection.
	 *
	 * @param    iterable $source
	 * @return static
	 */
	public function concat( $source ) {
		$result = new static( $this );

		foreach ( $source as $item ) {
			$result->push( $item );
		}

		return $result;
	}

	/**
	 * Get and remove an item from the collection.
	 *
	 * @param    mixed $key
	 * @param    mixed $default
	 * @return mixed
	 */
	public function pull( $key, $default = null ) {
		return Arr::pull( $this->items, $key, $default );
	}

	/**
	 * Put an item in the collection by key.
	 *
	 * @param    mixed $key
	 * @param    mixed $value
	 * @return $this
	 */
	public function put( $key, $value ) {
		$this->offsetSet( $key, $value );

		return $this;
	}

	/**
	 * Get one or a specified number of items randomly from the collection.
	 *
	 * @param    int|null $number
	 * @return static|mixed
	 *
	 * @throws \InvalidArgumentException Throws on number larger than collection length.
	 */
	public function random( $number = null ) {
		if ( is_null( $number ) ) {
			return Arr::random( $this->items );
		}

		return new static( Arr::random( $this->items, $number ) );
	}

	/**
	 * Reduce the collection to a single value.
	 *
	 * @param    callable $callback
	 * @param    mixed    $initial
	 * @return mixed
	 */
	public function reduce( callable $callback, $initial = null ) {
		return array_reduce( $this->items, $callback, $initial );
	}

	/**
	 * Replace the collection items with the given items.
	 *
	 * @param    mixed $items
	 * @return static
	 */
	public function replace( $items ) {
		return new static( array_replace( $this->items, $this->get_arrayable_items( $items ) ) );
	}

	/**
	 * Recursively replace the collection items with the given items.
	 *
	 * @param    mixed $items
	 * @return static
	 */
	public function replace_recursive( $items ) {
		return new static( array_replace_recursive( $this->items, $this->get_arrayable_items( $items ) ) );
	}

	/**
	 * Reverse items order.
	 *
	 * @return static
	 */
	public function reverse() {
		return new static( array_reverse( $this->items, true ) );
	}

	/**
	 * Search the collection for a given value and return the corresponding key if successful.
	 *
	 * @param    mixed $value
	 * @param    bool  $strict
	 * @return mixed
	 */
	public function search( $value, $strict = false ) {
		if ( ! $this->use_as_callable( $value ) ) {
			return array_search( $value, $this->items, $strict );
		}

		foreach ( $this->items as $key => $item ) {
			if ( $value( $item, $key ) ) {
				return $key;
			}
		}

		return false;
	}

	/**
	 * Get and remove the first item from the collection.
	 *
	 * @return mixed
	 */
	public function shift() {
		return array_shift( $this->items );
	}

	/**
	 * Shuffle the items in the collection.
	 *
	 * @param    int|null $seed
	 * @return static
	 */
	public function shuffle( $seed = null ) {
		return new static( Arr::shuffle( $this->items, $seed ) );
	}

	/**
	 * Skip the first {$count} items.
	 *
	 * @param    int $count
	 * @return static
	 */
	public function skip( $count ) {
		return $this->slice( $count );
	}

	/**
	 * Skip items in the collection until the given condition is met.
	 *
	 * @param    mixed $value
	 * @return static
	 */
	public function skip_until( $value ) {
		return new static( $this->lazy()->skipUntil( $value )->all() );
	}

	/**
	 * Skip items in the collection while the given condition is met.
	 *
	 * @param    mixed $value
	 * @return static
	 */
	public function skip_while( $value ) {
		return new static( $this->lazy()->skipWhile( $value )->all() );
	}

	/**
	 * Slice the underlying collection array.
	 *
	 * @param    int      $offset
	 * @param    int|null $length
	 * @return static
	 */
	public function slice( $offset, $length = null ) {
		return new static( array_slice( $this->items, $offset, $length, true ) );
	}

	/**
	 * Split a collection into a certain number of groups.
	 *
	 * @param    int $number_of_groups
	 * @return static
	 */
	public function split( $number_of_groups ) {
		if ( $this->is_empty() ) {
			return new static();
		}

		$groups = new static();

		$group_size = floor( $this->count() / $number_of_groups );

		$remain = $this->count() % $number_of_groups;

		$start = 0;

		for ( $i = 0; $i < $number_of_groups; $i++ ) {
			$size = $group_size;

			if ( $i < $remain ) {
				$size++;
			}

			if ( $size ) {
				$groups->push( new static( array_slice( $this->items, $start, $size ) ) );

				$start += $size;
			}
		}

		return $groups;
	}

	/**
	 * Chunk the collection into chunks of the given size.
	 *
	 * @param    int $size
	 * @return static
	 */
	public function chunk( $size ) {
		if ( $size <= 0 ) {
			return new static();
		}

		$chunks = [];

		foreach ( array_chunk( $this->items, $size, true ) as $chunk ) {
			$chunks[] = new static( $chunk );
		}

		return new static( $chunks );
	}

	/**
	 * Sort through each item with a callback.
	 *
	 * @param    callable|null $callback
	 * @return static
	 */
	public function sort( $callback = null ) {
		$items = $this->items;

		$callback && is_callable( $callback )
			? uasort( $items, $callback )
			: asort( $items, $callback );

		return new static( $items );
	}

	/**
	 * Sort items in descending order.
	 *
	 * @param    int $options
	 * @return static
	 */
	public function sort_desc( $options = SORT_REGULAR ) {
		$items = $this->items;

		arsort( $items, $options );

		return new static( $items );
	}

	/**
	 * Sort the collection using the given callback.
	 *
	 * @param    callable|string $callback
	 * @param    int             $options
	 * @param    bool            $descending
	 * @return static
	 */
	public function sort_by( $callback, $options = SORT_REGULAR, $descending = false ) {
		$results = [];

		$callback = $this->value_retriever( $callback );

		// First we will loop through the items and get the comparator from a callback
		// function which we were given. Then, we will sort the returned values and
		// and grab the corresponding values for the sorted keys from this array.
		foreach ( $this->items as $key => $value ) {
			$results[ $key ] = $callback( $value, $key );
		}

		$descending ? arsort( $results, $options )
							: asort( $results, $options );

		// Once we have sorted all of the keys in the array, we will loop through them
		// and grab the corresponding model so we can set the underlying items list
		// to the sorted version. Then we'll just return the collection instance.
		foreach ( array_keys( $results ) as $key ) {
			$results[ $key ] = $this->items[ $key ];
		}

		return new static( $results );
	}

	/**
	 * Sort the collection in descending order using the given callback.
	 *
	 * @param    callable|string $callback
	 * @param    int             $options
	 * @return static
	 */
	public function sort_by_desc( $callback, $options = SORT_REGULAR ) {
		return $this->sort_by( $callback, $options, true );
	}

	/**
	 * Sort the collection keys.
	 *
	 * @param    int  $options
	 * @param    bool $descending
	 * @return static
	 */
	public function sort_keys( $options = SORT_REGULAR, $descending = false ) {
		$items = $this->items;

		$descending ? krsort( $items, $options ) : ksort( $items, $options );

		return new static( $items );
	}

	/**
	 * Sort the collection keys in descending order.
	 *
	 * @param    int $options
	 * @return static
	 */
	public function sort_keys_desc( $options = SORT_REGULAR ) {
		return $this->sort_keys( $options, true );
	}

	/**
	 * Splice a portion of the underlying collection array.
	 *
	 * @param    int      $offset
	 * @param    int|null $length
	 * @param    mixed    $replacement
	 * @return static
	 */
	public function splice( $offset, $length = null, $replacement = [] ) {
		if ( func_num_args() === 1 ) {
			return new static( array_splice( $this->items, $offset ) );
		}

		return new static( array_splice( $this->items, $offset, $length, $replacement ) );
	}

	/**
	 * Take the first or last {$limit} items.
	 *
	 * @param    int $limit
	 * @return static
	 */
	public function take( $limit ) {
		if ( $limit < 0 ) {
			return $this->slice( $limit, abs( $limit ) );
		}

		return $this->slice( 0, $limit );
	}

	/**
	 * Take items in the collection until the given condition is met.
	 *
	 * @param    mixed $value
	 * @return static
	 */
	public function take_until( $value ) {
		return new static( $this->lazy()->takeUntil( $value )->all() );
	}

	/**
	 * Take items in the collection while the given condition is met.
	 *
	 * @param    mixed $value
	 * @return static
	 */
	public function take_while( $value ) {
		return new static( $this->lazy()->takeWhile( $value )->all() );
	}

	/**
	 * Transform each item in the collection using a callback.
	 *
	 * @param    callable $callback
	 * @return $this
	 */
	public function transform( callable $callback ) {
		$this->items = $this->map( $callback )->all();

		return $this;
	}

	/**
	 * Reset the keys on the underlying array.
	 *
	 * @return static
	 */
	public function values() {
		return new static( array_values( $this->items ) );
	}

	/**
	 * Zip the collection together with one or more arrays.
	 *
	 * E.g. new Collection([1, 2, 3])->zip([4, 5, 6]);
	 *          => [[1, 4], [2, 5], [3, 6]]
	 *
	 * @param    mixed $items
	 * @return static
	 */
	public function zip( $items ) {
		$arrayable_items = array_map(
			function ( $items ) {
				return $this->get_arrayable_items( $items );
			},
			func_get_args()
		);

		$params = array_merge(
			[
				function () {
					return new static( func_get_args() );
				},
				$this->items,
			],
			$arrayable_items
		);

		return new static( call_user_func_array( 'array_map', $params ) );
	}

	/**
	 * Pad collection to the specified length with a value.
	 *
	 * @param    int   $size
	 * @param    mixed $value
	 * @return static
	 */
	public function pad( $size, $value ) {
		return new static( array_pad( $this->items, $size, $value ) );
	}

	/**
	 * Get an iterator for the items.
	 *
	 * @return \ArrayIterator
	 */
	public function getIterator() {
		return new ArrayIterator( $this->items );
	}

	/**
	 * Count the number of items in the collection.
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->items );
	}

	/**
	 * Add an item to the collection.
	 *
	 * @param    mixed $item
	 * @return $this
	 */
	public function add( $item ) {
		$this->items[] = $item;

		return $this;
	}

	/**
	 * Get a base Support collection instance from this collection.
	 *
	 * @return \Mantle\Framework\Support\Collection
	 */
	public function to_base() {
		return new self( $this );
	}

	/**
	 * Determine if an item exists at an offset.
	 *
	 * @param    mixed $key
	 * @return bool
	 */
	public function offsetExists( $key ) {
		return array_key_exists( $key, $this->items );
	}

	/**
	 * Get an item at a given offset.
	 *
	 * @param    mixed $key
	 * @return mixed
	 */
	public function offsetGet( $key ) {
		return $this->items[ $key ];
	}

	/**
	 * Set the item at a given offset.
	 *
	 * @param    mixed $key
	 * @param    mixed $value
	 * @return void
	 */
	public function offsetSet( $key, $value ) {
		if ( is_null( $key ) ) {
			$this->items[] = $value;
		} else {
			$this->items[ $key ] = $value;
		}
	}

	/**
	 * Unset the item at a given offset.
	 *
	 * @param    string $key
	 * @return void
	 */
	public function offsetUnset( $key ) {
		unset( $this->items[ $key ] );
	}
}
