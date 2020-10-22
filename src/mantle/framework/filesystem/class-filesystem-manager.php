<?php
/**
 * Filesystem_Manager class file.
 *
 * @package Mantle
 */

namespace Mantle\Framework\Filesystem;

use Closure;
use InvalidArgumentException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\AbstractCache;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Cached\Storage\Memory as MemoryStore;
use Mantle\Framework\Contracts\Application;
use Mantle\Framework\Contracts\Filesystem\Filesystem;
use Mantle\Framework\Contracts\Filesystem\Filesystem_Manager as Filesystem_Manager_Contract;
use Mantle\Framework\Support\Arr;
use RuntimeException;

/**
 * Filesystem Manager
 *
 * @mixin \Mantle\Framework\Contracts\Filesystem\Filesystem
 */
class Filesystem_Manager implements Filesystem_Manager_Contract {
	/**
	 * Application instance
	 *
	 * @var Application
	 */
	protected $app;

	/**
	 * Disk storage.
	 *
	 * @var Filesystem[]
	 */
	protected $disks = [];

	/**
	 * Storage of custom drivers for the filesystem.
	 *
	 * @var Closure[]
	 */
	protected $custom_drivers;

	/**
	 * Constructor.
	 *
	 * @param Application $app Application instance.
	 */
	public function __construct( Application $app ) {
		$this->app = $app;
	}

	/**
	 * Retrieve a filesystem disk.
	 *
	 * @param string $name Disk name.
	 * @return \Mantle\Framework\Contracts\Filesystem\Filesystem
	 *
	 * @throws InvalidArgumentException Thrown on invalid disk configuration.
	 */
	public function drive( string $name = null ): Filesystem {
		return $this->resolve_disk( $name ?: $this->get_default_driver() );
	}

	/**
	 * Retrieve a disk by name.
	 *
	 * @param string $name Disk name.
	 * @return Filesystem
	 * @throws InvalidArgumentException Thrown on invalid disk/driver configuration.
	 */
	protected function resolve_disk( string $name ): Filesystem {
		if ( isset( $this->disks[ $name ] ) ) {
			return $this->disks[ $name ];
		}

		$config = $this->get_config( $name );
		if ( empty( $config['driver'] ) ) {
			throw new InvalidArgumentException( "Disk [{$name}] does not have a configured driver." );
		}

		$driver = $config['driver'];

		// Call a custom driver callback.
		if ( isset( $this->custom_drivers[ $driver ] ) ) {
			$this->disks[ $name ] = $this->call_custom_driver( $driver, $config );
			return $this->disks[ $name ];
		}

		$driver_method = 'create_' . strtolower( $driver ) . '_driver';

		if ( ! method_exists( $this, $driver_method ) ) {
			throw new InvalidArgumentException( "Disk [{$name}] uses a driver [{$driver}] that is not supported." );
		}

		$this->disks[ $name ] = $this->{$driver_method}( $config );
		return $this->disks[ $name ];
	}

	/**
	 * Retrieve configuration for a specific filesystem disk.
	 *
	 * @param string $disk Disk name.
	 * @return array
	 */
	protected function get_config( string $disk ): array {
		return (array) ( $this->app['config'][ "filesystem.disks.{$disk}" ] ?? [] );
	}

	/**
	 * Retrieve the default disk driver.
	 *
	 * @return string
	 */
	protected function get_default_driver(): string {
		return (string) $this->app['config']['filesystem.default'];
	}

	/**
	 * Add a custom driver to the filesystem.
	 *
	 * @param string  $driver Driver name.
	 * @param Closure $callback Callback to invoke to create an instance of the driver.
	 * @return static
	 */
	public function extend( string $driver, Closure $callback ) {
		$this->custom_drivers[ $driver ] = $callback;
		return $this;
	}

	/**
	 * Call a custom driver.
	 *
	 * @param string $driver Driver name.
	 * @param array  $config Configuration from disk.
	 * @return Filesystem
	 */
	protected function call_custom_driver( string $driver, array $config ): Filesystem {
		$instance = $this->custom_drivers[ $driver ]( $this->app, $config );

		if ( $instance instanceof AdapterInterface ) {
			$instance = $this->create_flysystem( $instance, $config );
		}

		if ( $instance instanceof FilesystemInterface ) {
			$instance = $this->adapt( $instance );
		}

		return $instance;
	}

	/**
	 * Adapt a adapter instance.
	 *
	 * @param FilesystemInterface $filesystem Filesystem instance.
	 * @return Filesystem_Adapter
	 */
	protected function adapt( FilesystemInterface $filesystem ) {
		return new Filesystem_Adapter( $filesystem );
	}

	/**
	 * Create a Flysystem instance with the given adapter.
	 *
	 * @param AdapterInterface $adapter
	 * @param array            $config Adapter configuration.
	 * @return FilesystemInterface
	 *
	 * @throws RuntimeException Thrown on missing CachedAdapter.
	 */
	protected function create_flysystem( AdapterInterface $adapter, array $config = [] ): FilesystemInterface {
		$cache  = Arr::pull( $config, 'cache' );
		$config = Arr::only( $config, [ 'visibility', 'disable_asserts', 'url' ] );

		if ( $cache ) {

			if ( ! class_exists( CachedAdapter::class ) ) {
				throw new RuntimeException( 'CachedAdapter class is not loaded.' );
			}

			$adapter = new CachedAdapter( $adapter, $this->create_cache_store( $cache ) );
		}

		return new Flysystem( $adapter, $config );
	}

	/**
	 * Create a cache store instance.
	 *
	 * @param mixed $config Adapter configuration.
	 * @return AbstractCache
	 *
	 * @todo Add support for other caching adapters.
	 */
	protected function create_cache_store( $config ): AbstractCache {
		return new MemoryStore( $config );
	}

	/**
	 * Pass the method calls to the default disk.
	 *
	 * @param string $method Method to invoke.
	 * @param array  $arguments Arguments for the method.
	 * @return mixed
	 */
	public function __call( string $method, array $arguments ) {
		return $this->disk()->$method( ...$arguments );
	}
}
