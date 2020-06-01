<?php
namespace Mantle\Tests\Database\Builder\Post_Query_Relationships;

use Mantle\Framework\Database\Model\Post;
use Mantle\Framework\Database\Model\Relationships;
use Mantle\Framework\Database\Model\Term;
use Mantle\Framework\Database\Query\Post_Query_Builder as Builder;
use Mantle\Framework\Database\Query\Post_Query_Builder;
use WP_UnitTestCase;

/**
 * @todo Replace with the Mantle Testing Framework
 */
class Test_Post_Query_Relationships extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();
		register_post_type( Testable_Sponsor::get_object_name() );
	}

	public function tearDown() {
		parent::tearDown();
		unregister_post_type( Testable_Sponsor::get_object_name() );
	}

	public function test_relationship_has() {
		$post_a     = $this->get_random_post_id();
		$sponsor_id = $this->get_random_post_id( [ 'post_type' => 'sponsor' ] );

		// Associate the post with the sponsor.
		update_post_meta( $post_a, 'testable_sponsor_id', $sponsor_id );

		$first = Testable_Post::has( 'sponsor' )->first();
		$this->assertEquals( $post_a, $first->id() );
	}

	public function test_relationship_has_compare() {
		$post_a     = $this->get_random_post_id();
		$sponsor_id = $this->get_random_post_id( [ 'post_type' => 'sponsor' ] );

		// Associate the post with the sponsor.
		update_post_meta( $post_a, 'testable_sponsor_id', $sponsor_id );

		$missing = Testable_Post::has( 'sponsor', 'non-exist' )->first();
		$this->assertEmpty( $missing );

		$expected = Testable_Post::has( 'sponsor', $sponsor_id )->first();
		$this->assertEquals( $post_a, $expected->id() );
	}

	public function test_relationship_doesnt_have() {
		$post_a     = $this->get_random_post_id();
		$sponsor_id = $this->get_random_post_id( [ 'post_type' => 'sponsor' ] );

		// Associate the post with the sponsor.
		update_post_meta( $post_a, 'testable_sponsor_id', $sponsor_id );

		$first = Testable_Post::doesnt_have( 'sponsor' )->first();
		$this->assertNotEquals( $post_a, $first->id() );
	}

	/**
	 * Get a random post ID, ensures the post ID is not the last in the set.
	 *
	 * @return integer
	 */
	protected function get_random_post_id( $args = [] ): int {
		$post_ids = static::factory()->post->create_many( 11, $args );
		array_pop( $post_ids );
		return $post_ids[ array_rand( $post_ids ) ];
	}
}

class Testable_Post extends Post {
	use Relationships;
	public static $object_name = 'post';

	public function sponsor() {
		return $this->belongs_to( Testable_Sponsor::class );
	}
}

class Testable_Sponsor extends Post {
	use Relationships;

	public static $object_name = 'sponsor';

	public function post() {
		return $this->has_one( Testable_Post::class );
	}

	public function posts() {
		return $this->has_many( Testable_Post::class );
	}
}
