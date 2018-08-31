<?php
/**
 * @author OnTheGo Systems
 */

namespace OTGS;

interface Stream {
	/**
	 * @param string $uri
	 *
	 * @return bool
	 */
	public function delete( $uri );

	/**
	 * @param string $uri
	 *
	 * @return string
	 */
	public function get( $uri );

	public function getLines( $file );

	/**
	 * @param string     $uri
	 * @param string     $data
	 * @param null|mixed $flags
	 *
	 * @return int
	 */
	public function put( $uri, $data, $flags = null );

	public function sanitizeUri( $uri );

	public function uriExists( $uri );
}
