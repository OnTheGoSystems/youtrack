<?php
/**
 * @author OnTheGo Systems
 */

namespace OTGS;

class FileSystem implements Stream
{

    /**
     * @see file_get_contents()
     *
     * @param string $filename
     *
     * @return string
     */
    public function get($filename)
    {
        return file_get_contents($filename);
    }

	/**
	 * @see file_put_contents()
	 *
	 * @param string $filename
	 * @param string $data
	 * @param int    $flags
	 *
	 * @return int
	 */
    public function put($filename, $data, $flags = 0)
    {
        return file_put_contents($filename, (string)$data, $flags);
    }

    public function sanitizeUri($uri)
    {
        return realpath($uri);
    }

    /**
     * @param string $uri
     *
     * @return bool
     */
    public function delete($uri)
    {
        return unlink($uri);
    }

    public function getLines($filename)
    {
        return file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    public function uriExists($uri)
    {
        return file_exists($uri);
    }
}
