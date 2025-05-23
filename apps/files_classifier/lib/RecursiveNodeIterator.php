<?php
/**
 *
 * @copyright LNKASIA TECHSOL
 * @license OCL
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\FilesClassifier;

use OCP\Files\FileInfo;
use OCP\Files\Node;

class RecursiveNodeIterator implements \RecursiveIterator {
	/** @var Node[] $rootNodes  */
	private $rootNodes;
	private $nodeCount;
	private $currentIndex;

	public function __construct(array $nodes) {
		$this->rootNodes = \array_values($nodes);
		$this->nodeCount = \count($nodes);
		$this->currentIndex = 0;
	}

	public function hasChildren() {
		return ($this->rootNodes[$this->currentIndex]->getType() === FileInfo::TYPE_FOLDER) &&
			/* @phan-suppress-next-line PhanUndeclaredMethod */
			(\count($this->rootNodes[$this->currentIndex]->getDirectoryListing()) > 0);
	}

	public function getChildren() {
		/* @phan-suppress-next-line PhanUndeclaredMethod */
		return new RecursiveNodeIterator($this->rootNodes[$this->currentIndex]->getDirectoryListing());
	}

	public function current() {
		return $this->rootNodes[$this->currentIndex];
	}

	public function next() {
		$this->currentIndex = $this->currentIndex +1;
	}

	public function key() {
		return $this->currentIndex;
	}

	public function valid() {
		return $this->currentIndex < $this->nodeCount;
	}

	public function rewind() {
		$this->currentIndex = 0;
	}
}
