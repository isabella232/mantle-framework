<?php
/**
 * Test_Generator_Command test file.
 *
 * @package Mantle
 */

namespace Mantle\Tests\Console;

use Mantle\Framework\Console\Hook_Usage_Command;
use Mantle\Support\Collection;
use Mantle\Testing\Framework_Test_Case;

class Test_Hook_Usage_Command extends Framework_Test_Case {

	public function test_do_action() {
		$usage = $this->run_command( 'init' )->all();
		$this->assertCount( 8, $usage );

		$expected = [
			[
				'file' => __DIR__ . '/hook-usage/base-example.php',
				'line' => 2,
				'method' => 'add_action',
			],
			[
				'file' => __DIR__ . '/hook-usage/base-example.php',
				'line' => 6,
				'method' => 'add_action',
			],
			[
				'file' => __DIR__ . '/hook-usage/base-example.php',
				'line' => 10,
				'method' => 'add_action',
			],
			[
				'file' => __DIR__ . '/hook-usage/base-example.php',
				'line' => 28,
				'method' => 'add_action',
			],
			[
				'file' => __DIR__ . '/hook-usage/base-example.php',
				'line' => 40,
				'method' => 'add_action',
			],
			[
				'file' => __DIR__ . '/hook-usage/sub/sub-example.php',
				'line' => 2,
				'method' => 'add_action',
			],
			[
				'file' => __DIR__ . '/hook-usage/sub/sub-example.php',
				'line' => 6,
				'method' => 'add_action',
			],
			[
				'file' => __DIR__ . '/hook-usage/sub/sub-example.php',
				'line' => 10,
				'method' => 'add_action',
			],
		];

		foreach ( $expected as $item ) {
			$this->assertContains( $item, $usage );
		}
	}

	/**
	 * Run the command.
	 *
	 * @param string $hook Hook to check against.
	 * @return Collection
	 */
	protected function run_command( string $hook ): Collection {
		$command = new Hook_Usage_Command( $this->app );
		$command->set_command_args( [ $hook ] );
		$command->set_command_flags( [ 'search-path' => __DIR__ . '/hook-usage' ] );
		return $command->get_usage();
	}
}
