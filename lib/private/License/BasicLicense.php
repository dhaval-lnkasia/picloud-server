<?php
/**
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
namespace OC\License;

use OCP\License\AbstractLicense;

class BasicLicense extends AbstractLicense {
	private $rawLicense;
	private $org;
	private $expirationDateTimestamp = 0;  // to ensure an integer as expiration value
	private $rawCodes;
	private $codes;
	private $checksum;

	public function __construct(string $licenseKey) {
		$this->rawLicense = $licenseKey;

		$parts = \explode('-', $licenseKey);

		if (\count($parts) === 4) {
			// require 4 parts to initialize the object properly
			// otherwise the "isValid" will return false, and the "expirationTime" will be 0
			$this->org = $parts[0];
			$this->expirationDateTimestamp = \strtotime(\substr($parts[1], 0, 4) . '-' . \substr($parts[1], 4, 2) . '-' . \substr($parts[1], 6));
			$this->rawCodes = $parts[2];
			$this->codes = \str_split(\strtoupper($this->rawCodes), 8);
			$this->checksum = $parts[3];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getLicenseString(): string {
		return $this->rawLicense;
	}

	/**
	 * @inheritDoc
	 */
	public function isValid(): bool {
		$dateString = \date('Ymd', $this->expirationDateTimestamp);

		$checksum = \sprintf('%x', \crc32(\strtolower($this->org) . 'zz' . \strtolower($this->rawCodes) . 'zz' . $dateString));
		$dateCode = \strtoupper(\hash("crc32b", $dateString));

		return $checksum === $this->checksum && \in_array($dateCode, $this->codes);
	}

	/**
	 * @inheritDoc
	 */
	public function getExpirationTime(): int {
		// The license expires at the end of the expiration date.
		// So add 1 day of seconds to the expiration date timestamp.
		return $this->expirationDateTimestamp + (24 * 60 * 60);
	}

	/**
	 * @inheritDoc
	 */
	public function getType(): int {
		$hash = \strtoupper(\hash("crc32b", 'demo'));
		if (\in_array($hash, $this->codes)) {
			return ILicense::LICENSE_TYPE_DEMO;
		} else {
			return ILicense::LICENSE_TYPE_NORMAL;
		}
	}
}
