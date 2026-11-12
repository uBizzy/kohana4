<?php
/**
 * Kohana Cache Tagging Interface
 *
 * @package    Kohana/Cache
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2026 Koseven Team
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
interface Kohana_Cache_Tagging {

	/**
	 * Set a value based on an id. Optionally add tags.
	 *
	 * Note : Some caching engines do not support
	 * tagging
	 *
	 * @param   string   $id        id
	 * @param   mixed    $data      data
	 * @param   int  $lifetime  lifetime [Optional]
	 * @param   array<int, string>    $tags      tags [Optional]
	 * @return  bool
	 */
	public function set_with_tags(string $id, mixed $data, ?int $lifetime = null, ?array $tags = null): bool;

	/**
	 * Delete cache entries based on a tag
	 *
	 * @param   string  $tag  tag
	 */
	public function delete_tag(string $tag): bool;

	/**
	 * Find cache entries based on a tag
	 *
	 * @param   string  $tag  tag
	 * @return  array<string, mixed>
	 */
	public function find(string $tag): array;
}
