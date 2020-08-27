<?php
/**
 * Application class file.
 *
 * @package Mantle
 */

namespace Mantle\Framework;

use Mantle\Framework\Contracts\Application as Application_Contract;
use Mantle\Framework\Contracts\Container as Container_Contract;
use Mantle\Framework\Contracts\Kernel as Kernel_Contract;
use Mantle\Framework\Log\Log_Service_Provider;
use Mantle\Framework\Providers\Event_Service_Provider;
use Mantle\Framework\Providers\Routing_Service_Provider;
use Mantle\Framework\Providers\View_Service_Provider;
use Mantle\Framework\Support\Arr;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Mantle\Framework\Helpers\collect;

/**
 * Mantle Application
 */
class Application extends Container\Container implements Application_Contract {
	/**
	 * Base path of the application.
	 *
	 * @var string
	 */
	protected $base_path;

	/**
	 * Root URL of the application.
	 *
	 * @var string
	 */
	protected $root_url;

	/**
	 * Indicates if the application has been bootstrapped before.
	 *
	 * @var bool
	 */
	protected $has_been_bootstrapped = false;

	/**
	 * Indicates if the application has "booted".
	 *
	 * @var bool
	 */
	protected $booted = false;

	/**
	 * The array of booting callbacks.
	 *
	 * @var callable[]
	 */
	protected $booting_callbacks = [];

	/**
	 * The array of booted callbacks.
	 *
	 * @var callable[]
	 */
	protected $booted_callbacks = [];

	/**
	 * All of the registered service providers.
	 *
	 * @var ServiceProvider[]
	 */
	protected $service_providers = [];

	/**
	 * Storage of the overridden environment name.
	 *
	 * @var string
	 */
	protected $environment;

	/**
	 * Constructor.
	 *
	 * @param string $base_path Base path to set.
	 * @param string $root_url Root URL of the application.
	 */
	public function __construct( string $base_path = '', string $root_url = null ) {
		if ( empty( $base_path ) && defined( 'MANTLE_BASE_DIR' ) ) {
			$base_path = \MANTLE_BASE_DIR;
		}

		if ( ! $root_url ) {
			$root_url = \home_url();
		}

		$this->set_base_path( $base_path );
		$this->set_root_url( $root_url );
		$this->register_base_bindings();
		$this->register_base_service_providers();
		$this->register_core_aliases();
	}

	/**
	 * Set the base path of the application.
	 *
	 * @param string $path Path to set.
	 */
	public function set_base_path( string $path ) {
		$this->base_path = $path;
	}

	/**
	 * Getter for the base path.
	 *
	 * @param string $path Path to append.
	 * @return string
	 */
	public function get_base_path( string $path = '' ): string {
		return $this->base_path . ( $path ? '/' . $path : '' );
	}

	/**
	 * Set the root URL of the application.
	 *
	 * @param string $url Root URL to set.
	 */
	public function set_root_url( string $url ) {
		$this->root_url = $url;
	}

	/**
	 * Getter for the root URL.
	 *
	 * @param string $path Path to append.
	 * @return string
	 */
	public function get_root_url( string $path = '' ): string {
		return $this->root_url . ( $path ? '/' . $path : '' );
	}

	/**
	 * Get the cache folder root
	 *
	 * @return string
	 */
	public function get_cache_path(): string {
		return $this->base_path . '/bootstrap/cache';
	}

	/**
	 * Get the cached Composer packages path.
	 * Folder that stores all compiled server-side assets for the application.
	 *
	 * @return string
	 */
	public function get_cached_packages_path() {
		return $this->get_cache_path() . '/packages.php';
	}

	/**
	 * Get the path to the application configuration files.
	 *
	 * @return string
	 */
	public function get_config_path(): string {
		return $this->base_path . '/config';
	}

	/**
	 * Determine if the application has been bootstrapped before.
	 *
	 * @return bool
	 */
	public function has_been_bootstrapped(): bool {
		return (bool) $this->has_been_bootstrapped;
	}

	/**
	 * Register the basic bindings into the container.
	 *
	 * @return void
	 */
	protected function register_base_bindings() {
		static::set_instance( $this );

		$this->instance( 'app', $this );
		$this->instance( Container\Container::class, $this );
		$this->instance( Container_Contract::class, $this );
		$this->instance( static::class, $this );

		$this->singleton(
			Package_Manifest::class,
			function( $app ) {
				return new Package_Manifest( $this->get_base_path(), $this->get_cached_packages_path(), $app );
			}
		);
	}

	/**
	 * Register the base service providers.
	 */
	protected function register_base_service_providers() {
		$this->register( Event_Service_Provider::class );
		$this->register( Log_Service_Provider::class );
		$this->register( View_Service_Provider::class );
		$this->register( Routing_Service_Provider::class );
	}

	/**
	 * Register the core aliases.
	 */
	protected function register_core_aliases() {
		$core_aliases = [
			'app'         => [ static::class, \Mantle\Framework\Contracts\Application::class ],
			'config'      => [ \Mantle\Framework\Config\Repository::class, \Mantle\Framework\Contracts\Config\Repository::class ],
			'log'         => [ \Mantle\Framework\Log\Log_Manager::class, \Psr\Log\LoggerInterface::class ],
			'queue'       => [ \Mantle\Framework\Queue\Queue_Manager::class, \Mantle\Framework\Contracts\Queue\Queue_Manager::class ],
			'redirect'    => [ \Mantle\Framework\Http\Routing\Redirector::class ],
			'request'     => [ \Mantle\Framework\Http\Request::class, \Symfony\Component\HttpFoundation\Request::class ],
			'router'      => [ \Mantle\Framework\Http\Routing\Router::class, \Mantle\Framework\Contracts\Http\Routing\Router::class ],
			'url'         => [ \Mantle\Framework\Http\Routing\Url_Generator::class, \Mantle\Framework\Contracts\Http\Routing\Url_Generator::class ],
			'view'        => [ \Mantle\Framework\Http\View\Factory::class, \Mantle\Framework\Contracts\Http\View\Factory::class ],
			'view.loader' => [ \Mantle\Framework\Http\View\View_Loader::class, \Mantle\Framework\Contracts\Http\View\View_Loader::class ],
		];

		foreach ( $core_aliases as $key => $aliases ) {
			foreach ( $aliases as $alias ) {
				$this->alias( $key, $alias );
			}
		}
	}

	/**
	 * Flush the container of all bindings and resolved instances.
	 */
	public function flush() {
		parent::flush();

		$this->booted_callbacks  = [];
		$this->booting_callbacks = [];
		$this->service_providers = [];
	}

	/**
	 * Run the given array of bootstrap classes.
	 *
	 * Bootstrap classes should implement `Mantle\Framework\Contracts\Bootstrapable`.
	 *
	 * @param string[]        $bootstrappers Class names of packages to boot.
	 * @param Kernel_Contract $kernel Kernel instance.
	 */
	public function bootstrap_with( array $bootstrappers, Kernel_Contract $kernel ) {
		$this->has_been_bootstrapped = true;

		foreach ( $bootstrappers as $bootstrapper ) {
			$this->make( $bootstrapper )->bootstrap( $this, $kernel );
		}
	}

	/**
	 * Register all of the configured providers.
	 */
	public function register_configured_providers() {
		// Get providers from the application config.
		$providers = collect( $this->config->get( 'app.providers', [] ) );

		// Include providers from the package manifest.
		$providers->push( ...$this->make( Package_Manifest::class )->providers() );

		$providers->each( [ $this, 'register' ] );
	}

	/**
	 * Get an instance of a service provider.
	 *
	 * @param string $name Provider class name.
	 * @return Service_Provider|null
	 */
	public function get_provider( string $name ): ?Service_Provider {
		$providers = Arr::where(
			$this->get_providers(),
			function( Service_Provider $provider ) use ( $name ) {
				return $provider instanceof $name;
			}
		);

		return array_shift( $providers );
	}

	/**
	 * Get all service providers.
	 *
	 * @return Service_Provider[]
	 */
	public function get_providers(): array {
		return $this->service_providers;
	}

	/**
	 * Register a Service Provider
	 *
	 * @param Service_Provider|string $provider Provider instance or class name to register.
	 * @return Application
	 */
	public function register( $provider ): Application {
		$provider_name = is_string( $provider ) ? $provider : get_class( $provider );

		if ( ! empty( $this->service_providers[ $provider_name ] ) ) {
			return $this;
		}

		if ( is_string( $provider ) ) {
			$provider = new $provider( $this );
		}

		if ( ! ( $provider instanceof Service_Provider ) ) {
			\wp_die( 'Provider is not instance of Service_Provider: ' . $provider_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$provider->register();
		$this->service_providers[ $provider_name ] = $provider;
		return $this;
	}

	/**
	 * Determine if the application has booted.
	 *
	 * @return bool
	 */
	public function is_booted(): bool {
		return $this->booted;
	}

	/**
	 * Boot the application's service providers.
	 *
	 * @return static
	 */
	public function boot() {
		if ( $this->is_booted() ) {
			return $this;
		}

		// Fire the 'booting' callbacks.
		$this->fire_app_callbacks( $this->booting_callbacks );

		foreach ( $this->service_providers as $provider ) {
			$provider->boot();
		}

		$this->booted = true;

		// Fire the 'booted' callbacks.
		$this->fire_app_callbacks( $this->booted_callbacks );

		return $this;
	}

	/**
	 * Get the Application's Environment
	 *
	 * @return string
	 */
	public function environment(): string {
		if ( ! empty( $this->environment ) ) {
			return $this->environment;
		}

		$environment = wp_get_environment_type();
		if ( ! empty( $environment ) ) {
			return (string) $environment;
		}

		if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && ! empty( VIP_GO_APP_ENVIRONMENT ) ) {
			return (string) VIP_GO_APP_ENVIRONMENT;
		}

		if ( ! empty( $_SERVER['PANTHEON_ENVIRONMENT'] ) ) {
			return (string) $_SERVER['PANTHEON_ENVIRONMENT']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		return $_ENV['env'] ?? 'local';
	}

	/**
	 * Check if the Application's Environment matches a list.
	 *
	 * @param string|array ...$environments Environments to check.
	 * @return bool
	 */
	public function is_environment( ...$environments ): bool {
		return in_array( $this->environment(), (array) $environments, true );
	}

	/**
	 * Set the environment for the application.
	 *
	 * @param string $environment Environment to set.
	 * @return static
	 */
	public function set_environment( string $environment ) {
		$this->environment = $environment;
		return $this;
	}

	/**
	 * Throw an HttpException with the given data.
	 *
	 * @param int    $code HTTP status code.
	 * @param string $message Response message.
	 * @param array  $headers Response headers.
	 *
	 * @throws NotFoundHttpException Thrown on 404 error.
	 * @throws HttpException Thrown on other HTTP error.
	 */
	public function abort( int $code, string $message = '', array $headers = [] ) {
		if ( 404 === $code ) {
			throw new NotFoundHttpException( $message, null, 404, $headers );
		} else {
			throw new HttpException( $code, $message, null, $headers );
		}
	}

	/**
	 * Register a new boot listener.
	 *
	 * @param callable $callback Callback for the listener.
	 * @return static
	 */
	public function booting( $callback ) {
		$this->booting_callbacks[] = $callback;
		return $this;
	}

	/**
	 * Register a new "booted" listener.
	 *
	 * @param callable $callback Callback for the listener.
	 * @return static
	 */
	public function booted( $callback ) {
		$this->booted_callbacks[] = $callback;

		if ( $this->is_booted() ) {
			$this->fire_app_callbacks( [ $callback ] );
		}

		return $this;
	}

	/**
	 * Call the booting callbacks for the application.
	 *
	 * @param callable[] $callbacks Callbacks to fire.
	 */
	protected function fire_app_callbacks( array $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$callback( $this );
		}
	}
}
