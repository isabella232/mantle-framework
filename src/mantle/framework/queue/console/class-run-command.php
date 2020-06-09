<?php
/**
 * Run_Command class file.
 *
 * @package Mantle
 */

namespace Mantle\Framework\Queue\Console;

use Mantle\Framework\Console\Command;
use Mantle\Framework\Contracts\Application;
use Mantle\Framework\Queue\Events\Job_Processed;
use Mantle\Framework\Queue\Events\Job_Processing;
use Mantle\Framework\Queue\Events\Run_Complete;
use Mantle\Framework\Queue\Events\Run_Start;

/**
 * Queue Run Command
 */
class Run_Command extends Command {
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'queue:run';

	/**
	 * Command Short Description.
	 *
	 * @var string
	 */
	protected $short_description = 'Run items from a queue.';

	/**
	 * Command Description.
	 *
	 * @var string
	 */
	protected $description = 'Run items from a queue.';

	/**
	 * Command synopsis.
	 *
	 * @var string|array
	 */
	protected $synopsis = [
		[
			'description' => 'Queue name.',
			'name'        => 'queue',
			'optional'    => false,
			'type'        => 'positional',
		],
		[
			'description' => 'Batch size, defaults to application default.',
			'name'        => 'count',
			'optional'    => true,
			'type'        => 'flag',
		],
	];

	/**
	 * Application instance.
	 *
	 * @var Application
	 */
	protected $app;

	/**
	 * Constructor.
	 *
	 * @param Application $app Application instance.
	 */
	public function __construct( Application $app ) {
		$this->app = $app;
	}

	/**
	 * Queue Run Command.
	 *
	 * @param array $args Command Arguments.
	 * @param array $assoc_args Command flags.
	 */
	public function handle( array $args, array $assoc_args = [] ) {
		[ $queue ] = $args;

		// Register the event listeners to pipe the events back to the console.
		$this->app['events']->listen(
			Run_Start::class,
			function() {
				$this->log( 'Run started' );
			}
		);

		$this->app['events']->listen(
			Job_Processing::class,
			function( Job_Processing $job ) {
				$this->log( 'Queue item started: ' . $job->get_id() );
			}
		);

		$this->app['events']->listen(
			Job_Processed::class,
			function( Job_Processed $job ) {
				$this->log( 'Queue item complete: ' . $job->get_id() );
			}
		);

		$this->app['events']->listen(
			Run_Complete::class,
			function() {
				$this->log( 'Run complete' );
			}
		);

		$this->app['queue.worker']->run_batch(
			(int) $this->get_flag( 'count', (int) $this->app['config']['queue.batch_size'] ?? 1 ),
			$queue
		);
	}
}
