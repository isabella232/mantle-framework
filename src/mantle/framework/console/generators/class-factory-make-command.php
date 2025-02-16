<?php
/**
 * Factory_Make_Command class file.
 *
 * @package Mantle
 */

namespace Mantle\Framework\Console\Generators;

/**
 * Factory Generator
 */
class Factory_Make_Command extends Stub_Generator_Command {
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'make:factory';

	/**
	 * Command Description.
	 *
	 * @var string
	 */
	protected $description = 'Generate a factory.';

	/**
	 * The type of class being generated.
	 *
	 * @var string
	 */
	protected $type = 'Factories';

	/**
	 * Command synopsis.
	 *
	 * @var string|array
	 */
	protected $synopsis = [
		[
			'description' => 'Factory name',
			'name'        => 'name',
			'optional'    => false,
			'type'        => 'positional',
		],
		[
			'description' => 'Model Type',
			'name'        => 'model_type',
			'optional'    => false,
			'type'        => 'assoc',
			'options'     => [ 'post', 'term' ],
		],
		[
			'description' => 'Object name to use, defaults to inferring from the class name',
			'name'        => 'object_name',
			'optional'    => true,
			'type'        => 'flag',
		],
	];

	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	public function get_file_stub(): string {
		$type = $this->flag( 'model_type' );

		$filename = '';

		if ( 'post' === $type ) {
			$filename = 'factory-post.stub';
		} elseif ( 'term' === $type ) {
			$filename = 'factory-term.stub';
		} else {
			$this->error( 'Unknown factory type: ' . $type, true );
		}

		// Set the object type to use.
		$this->replacements->add(
			'{{ object_name }}',
			$this->flag( 'object_name', $this->get_default_object_name() )
		);

		return __DIR__ . '/stubs/' . $filename;
	}

	/**
	 * Get the default object name.
	 *
	 * @return string
	 */
	protected function get_default_object_name(): string {
		$class_name = $this->get_class_name( $this->argument( 'name' ) );
		return strtolower( str_replace( '_', '-', $class_name ) );
	}

	/**
	 * Get the default label.
	 *
	 * @return string
	 */
	protected function get_default_label(): string {
		$class_name = str_replace( [ '_', '-' ], ' ', $this->get_class_name( $this->argument( 'name' ) ) );
		return ucwords( $class_name );
	}

	/**
	 * Command synopsis.
	 *
	 * @param string $name Class name.
	 * @todo Edit or remove this.
	 */
	public function complete_synopsis( string $name ) {
		$this->log(
			PHP_EOL . sprintf(
				'You can customize this factory by editing the file in "database/factories".',
				$this->get_namespace( $name ),
				$this->get_class_name( $name )
			)
		);
	}

	/**
	 * Get the folder location of the file.
	 *
	 * @param string $name Name to use.
	 * @return string
	 */
	protected function get_folder_path( string $name ): string {
		return untrailingslashit( $this->app->get_base_path() . '/database/' . strtolower( $this->type ) . '/' );
	}

	/**
	 * Get the location for the generated file.
	 *
	 * @param string $name Name to use.
	 * @return string
	 */
	protected function get_file_path( string $name ): string {
		$parts    = explode( '\\', $name );
		$filename = array_pop( $parts );
		$filename = sanitize_title_with_dashes( str_replace( '_', '-', $filename ) );

		return $this->get_folder_path( $name ) . '/' . $filename . '-factory.php';
	}
}
