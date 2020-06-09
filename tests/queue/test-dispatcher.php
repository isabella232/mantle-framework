<?php
namespace Mantle\Tests\Queue;

use Mantle\Framework\Application;
use Mantle\Framework\Config\Repository;
use Mantle\Framework\Contracts\Queue\Can_Queue;
use Mantle\Framework\Contracts\Queue\Job;
use Mantle\Framework\Contracts\Queue\Provider;
use Mantle\Framework\Facade\Facade;
use Mantle\Framework\Providers\Queue_Service_Provider;
use Mantle\Framework\Queue\Dispatchable;
use Mantle\Framework\Queue\Dispatcher;
use Mantle\Framework\Queue\Pending_Dispatch;
use Mantle\Framework\Queue\Queue_Manager;
use Mockery as m;

class Test_Dispatcher extends \Mockery\Adapter\Phpunit\MockeryTestCase {
	/**
	 * Provider instance.
	 *
	 * @var Provider
	 */
	protected $provider;

	/**
	 * Queue Manager instance.
	 *
	 * @var Queue_Manager
	 */
	protected $queue;

	protected $app;

	protected function setUp(): void {
		parent::setUp();

		$this->provider = m::mock( Provider::class );

		$config = new Repository(
			[
				'queue' => [
					'default' => 'test',
				],
			]
		);

		$this->app = new Application();
		$this->app->instance( 'config', $config );

		// Load the queue service provider manually.
		$queue_provider = new Queue_Service_Provider( $this->app );
		$queue_provider->register();
		$queue_provider->boot();

		$this->queue = $this->app['queue'];
		$this->queue->add_provider( 'test', $this->provider );

		Facade::clear_resolved_instances();
		Facade::set_facade_application( $this->app );
	}

	public function test_dispatch_to_provider() {
		$job = m::mock( Job::class, Can_Queue::class );
		$this->provider
			->shouldReceive( 'push' )
			->withArgs( [ $job ] )
			->once()
			->andReturn( true );

		$dispatcher = new Dispatcher( $this->app );
		$dispatcher->dispatch( $job );
	}

	public function test_dispatch_non_queueable() {
		$job = m::mock( Job::class );
		$job->shouldReceive( 'handle' )->once();

		$dispatcher = new Dispatcher( $this->app );
		$dispatcher->dispatch( $job );
	}

	public function test_pending_dispatch() {
		$job = m::mock( Job::class, Dispatchable::class, Can_Queue::class );
		$this->provider
			->shouldReceive( 'push' )
			->with( m::type( get_class( $job ) ) )
			->once();

		// $job_class = get_class( $job );
		get_class( $job )::dispatch( [] );
	}

	public function test_pending_dispatch_if() {
		$job = m::mock( Job::class, Dispatchable::class, Can_Queue::class );
		$this->provider
			->shouldReceive( 'push' )
			->with( m::type( get_class( $job ) ) )
			->times( 2 );

		get_class( $job )::dispatch_if( true, [] );
		get_class( $job )::dispatch_if( true, [] );
		get_class( $job )::dispatch_if( false, [] );
	}
}
