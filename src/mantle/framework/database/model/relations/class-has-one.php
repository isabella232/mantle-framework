<?php
/**
 * Has_One class file.
 *
 * @package Mantle
 */

namespace Mantle\Framework\Database\Model\Relations;

use Mantle\Framework\Support\Collection;

/**
 * Has One Relationship
 */
class Has_One extends Has_One_Or_Many {
	/**
	 * Get the results of the relationship.
	 *
	 * @return \Mantle\Framework\Database\Model\Model|null
	 */
	public function get_results() {
		$this->add_constraints();

		return $this->query->first();
	}

	/**
	 * Match the eagerly loaded results to their parents.
	 *
	 * @param Collection $models Parent models.
	 * @param Collection $results Eagerly loaded results to match.
	 * @return Collection
	 */
	public function match( Collection $models, Collection $results ): Collection {
		$dictionary = $this->build_dictionary( $results );

		return $models->each(
			function( $model ) use ( $dictionary ) {
				$key = $model[ $this->local_key ];
				$model->set_relation( $this->relationship, $dictionary[ $key ][0] ?? null );
			}
		);
	}
}
