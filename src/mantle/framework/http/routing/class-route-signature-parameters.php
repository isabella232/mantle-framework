<?php
/**
 * Route_Signature_Parameters class file.
 *
 * @package Mantle
 */

namespace Mantle\Framework\Http\Routing;

use Mantle\Framework\Support\Str;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Route Signature Parameters
 *
 * Extract route action parameters for binding resolution.
 */
class Route_Signature_Parameters {

	/**
	 * Extract the route action's signature parameters.
	 *
	 * @param  array       $action
	 * @param  string|null $sub_class
	 * @return array
	 */
	public static function from_action( array $action, $sub_class = null ) {
		$parameters = isset( $action['callback'] ) && is_string( $action['callback'] )
			? static::from_class_method_string( $action['callback'] )
			: ( new ReflectionFunction( $action['callback'] ) )->getParameters();

		return is_null( $sub_class ) ? $parameters : array_filter(
			$parameters,
			function ( $p ) use ( $sub_class ) {
				return $p->getClass() && $p->getClass()->isSubclassOf( $sub_class );
			}
		);
	}

	/**
	 * Get the parameters for the given class / method by string.
	 *
	 * @param  string $uses
	 * @return array
	 */
	protected static function from_class_method_string( $uses ) {
		[ $class, $method ] = Str::parse_callback( $uses );

		if ( ! method_exists( $class, $method ) && is_callable( $class, $method ) ) {
			return [];
		}

		return ( new ReflectionMethod( $class, $method ) )->getParameters();
	}
}
