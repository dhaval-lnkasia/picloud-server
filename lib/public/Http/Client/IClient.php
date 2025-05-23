<?php
/**
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 *
 * @copyright LNKASIA TECHSOL
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP\Http\Client;

/**
 * Interface IClient
 *
 * @package OCP\Http
 * @since 8.1.0
 */
interface IClient {
	/**
	 * Sends a GET request
	 * @param string $uri
	 * @param array $options Array such as
	 *              'query' => [
	 *                  'field' => 'abc',
	 *                  'other_field' => '123',
	 *                  'file_name' => fopen('/path/to/file', 'r'),
	 *              ],
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'sink' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 * @since 8.1.0
	 */
	public function get($uri, array $options = []);

	/**
	 * Sends a HEAD request
	 * @param string $uri
	 * @param array $options Array such as
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'sink' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 * @since 8.1.0
	 */
	public function head($uri, $options = []);

	/**
	 * Sends a POST request
	 * @param string $uri
	 * @param array $options Array such as
	 *              'multipart' => [
	 *                  ['name' => 'field', 'contents' => 'abc'],
	 *                  ['name' => 'other_field', 'contents' => '123'],
	 *                  ['name' => 'file_name', 'contents' => fopen('/path/to/file', 'r'), 'filename' => 'custom_name.txt'],
	 *              ],
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'sink' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 * @since 8.1.0
	 */
	public function post($uri, array $options = []);

	/**
	 * Sends a PUT request
	 * @param string $uri
	 * @param array $options Array such as
	 *              'multipart' => [
	 *                  ['name' => 'field', 'contents' => 'abc'],
	 *                  ['name' => 'other_field', 'contents' => '123'],
	 *                  ['name' => 'file_name', 'contents' => fopen('/path/to/file', 'r'), 'filename' => 'custom_name.txt'],
	 *              ],
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'sink' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 * @since 8.1.0
	 */
	public function put($uri, array $options = []);

	/**
	 * Sends a DELETE request
	 * @param string $uri
	 * @param array $options Array such as
	 *              'multipart' => [
	 *                  ['name' => 'field', 'contents' => 'abc'],
	 *                  ['name' => 'other_field', 'contents' => '123'],
	 *                  ['name' => 'file_name', 'contents' => fopen('/path/to/file', 'r'), 'filename' => 'custom_name.txt'],
	 *              ],
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'sink' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 * @since 8.1.0
	 */
	public function delete($uri, array $options = []);

	/**
	 * Sends a options request
	 * @param string $uri
	 * @param array $options Array such as
	 *              'multipart' => [
	 *                  ['name' => 'field', 'contents' => 'abc'],
	 *                  ['name' => 'other_field', 'contents' => '123'],
	 *                  ['name' => 'file_name', 'contents' => fopen('/path/to/file', 'r'), 'filename' => 'custom_name.txt'],
	 *              ],
	 *              'headers' => [
	 *                  'foo' => 'bar',
	 *              ],
	 *              'cookies' => ['
	 *                  'foo' => 'bar',
	 *              ],
	 *              'allow_redirects' => [
	 *                   'max'       => 10,  // allow at most 10 redirects.
	 *                   'strict'    => true,     // use "strict" RFC compliant redirects.
	 *                   'referer'   => true,     // add a Referer header
	 *                   'protocols' => ['https'] // only allow https URLs
	 *              ],
	 *              'sink' => '/path/to/file', // save to a file or a stream
	 *              'verify' => true, // bool or string to CA file
	 *              'debug' => true,
	 * @return IResponse
	 * @throws \Exception If the request could not get completed
	 * @since 8.1.0
	 */
	public function options($uri, array $options = []);
}
