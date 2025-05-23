<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2019, LNKASIA TECHSOL
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

namespace OCA\Encryption\Crypto;

use OC\Encryption\Exceptions\DecryptionFailedException;
use OC\Encryption\Exceptions\EncryptionFailedException;
use OC\HintException;
use OCA\Encryption\Exceptions\MultiKeyDecryptException;
use OCA\Encryption\Exceptions\MultiKeyEncryptException;
use OCP\Encryption\Exceptions\GenericEncryptionException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserSession;

/**
 * Class Crypt provides the encryption implementation of the default ownCloud
 * encryption module. As default AES-256-CTR is used, it does however offer support
 * for the following modes:
 *
 * - AES-256-CTR
 * - AES-128-CTR
 * - AES-256-CFB
 * - AES-128-CFB
 *
 * For integrity protection Encrypt-Then-MAC using HMAC-SHA256 is used.
 *
 * @package OCA\Encryption\Crypto
 */
class Crypt {
	public const DEFAULT_CIPHER = 'AES-256-CTR';
	// default cipher from old ownCloud versions
	public const LEGACY_CIPHER = 'AES-128-CFB';

	// default key format, old ownCloud version encrypted the private key directly
	// with the user password
	public const LEGACY_KEY_FORMAT = 'password';

	public const HEADER_START = 'HBEGIN';
	public const HEADER_END = 'HEND';

	/**
	 * @var string Encoding type has been changed to binary from base64.
	 * Reading the old format is still supported, new files are written with binary encoding by default.
	 */
	public const DEFAULT_ENCODING_FORMAT = 'binary';

	/**
	 * @var boolean $useLegacyEncoding
	 * Writing file with legacy base64 encoding is still supported for testing purposes
	 */
	private $useLegacyEncoding;

	/** @var ILogger */
	protected $logger;

	/** @var string */
	private $user;

	/** @var IConfig */
	protected $config;

	/** @var array */
	private $supportedKeyFormats;

	/** @var IL10N */
	private $l;

	/** @var array */
	private $supportedCiphersAndKeySize = [
		'AES-256-CTR' => 32,
		'AES-128-CTR' => 16,
		'AES-256-CFB' => 32,
		'AES-128-CFB' => 16,
	];

	/**
	 * @param ILogger $logger
	 * @param IUserSession $userSession
	 * @param IConfig $config
	 * @param IL10N $l
	 */
	public function __construct(ILogger $logger, IUserSession $userSession, IConfig $config, IL10N $l) {
		$this->logger = $logger;
		$this->user = $userSession !== null && $userSession->isLoggedIn() ? $userSession->getUser()->getUID() : '"no user given"';
		$this->config = $config;
		$this->l = $l;
		$this->supportedKeyFormats = ['hash', 'password'];
		$this->useLegacyEncoding = $this->config->getSystemValue('encryption.use_legacy_encoding', false) === true;
	}

	/**
	 * create new private/public key-pair for user
	 *
	 * @return array|bool
	 */
	public function createKeyPair() {
		$log = $this->logger;
		$res = $this->getOpenSSLPKey();

		if (!$res) {
			$log->error(
				"Encryption Library couldn't generate users key-pair for {$this->user}",
				['app' => 'encryption']
			);

			if (\openssl_error_string()) {
				$log->error(
					'Encryption library openssl_pkey_new() fails: ' . \openssl_error_string(),
					['app' => 'encryption']
				);
			}
		} elseif (\openssl_pkey_export(
			$res,
			$privateKey,
			null,
			$this->getOpenSSLConfig()
		)) {
			$keyDetails = \openssl_pkey_get_details($res);
			$publicKey = $keyDetails['key'];

			return [
				'publicKey' => $publicKey,
				'privateKey' => $privateKey
			];
		}
		$log->error(
			'Encryption library couldn\'t export users private key, please check your servers OpenSSL configuration.' . $this->user,
			['app' => 'encryption']
		);
		if (\openssl_error_string()) {
			$log->error(
				'Encryption Library:' . \openssl_error_string(),
				['app' => 'encryption']
			);
		}

		return false;
	}

	/**
	 * Generates a new private key
	 *
	 * @return resource|bool
	 */
	public function getOpenSSLPKey() {
		$config = $this->getOpenSSLConfig();
		return \openssl_pkey_new($config);
	}

	/**
	 * get openSSL Config
	 *
	 * @return array
	 */
	private function getOpenSSLConfig() {
		$config = ['private_key_bits' => 4096];
		$config = \array_merge(
			$config,
			$this->config->getSystemValue('openssl', [])
		);
		return $config;
	}

	/**
	 * @param string $plainContent
	 * @param string $passPhrase
	 * @param int $version
	 * @param int $position
	 * @return false|string
	 * @throws EncryptionFailedException
	 */
	public function symmetricEncryptFileContent($plainContent, $passPhrase, $version, $position) {
		if (!$plainContent) {
			$this->logger->error(
				'Encryption Library, symmetrical encryption failed no content given',
				['app' => 'encryption']
			);
			return false;
		}

		$iv = $this->generateIv();

		$encryptedContent = $this->encrypt(
			$plainContent,
			$iv,
			$passPhrase,
			$this->getCipher()
		);

		// Create a signature based on the key as well as the current version
		$sig = $this->createSignature($encryptedContent, $passPhrase . $version . "-" . $position);

		// combine content to encrypt the IV identifier and actual IV
		$catFile = $this->concatIV($encryptedContent, $iv);
		$catFile = $this->concatSig($catFile, $sig);
		$padded = $this->addPadding($catFile);

		return $padded;
	}

	/**
	 * generate header for encrypted file
	 *
	 * @param string $keyFormat (can be 'hash' or 'password')
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public function generateHeader($keyFormat = 'hash') {
		if (\in_array($keyFormat, $this->supportedKeyFormats, true) === false) {
			throw new \InvalidArgumentException('key format "' . $keyFormat . '" is not supported');
		}

		$header = self::HEADER_START
			. ':cipher:' . $this->getCipher()
			. ':keyFormat:' . $keyFormat;

		if ($this->useLegacyEncoding !== true) {
			$header .= ':encoding:' . self::DEFAULT_ENCODING_FORMAT;
		}
		return $header . ':' . self::HEADER_END;
	}

	/**
	 * @param string $plainContent
	 * @param string $iv
	 * @param string $passPhrase
	 * @param string $cipher
	 * @return string
	 * @throws EncryptionFailedException
	 */
	private function encrypt($plainContent, $iv, $passPhrase = '', $cipher = self::DEFAULT_CIPHER) {
		$options = $this->useLegacyEncoding === true ? 0 : OPENSSL_RAW_DATA;
		$encryptedContent = \openssl_encrypt(
			$plainContent,
			$cipher,
			$passPhrase,
			$options,
			$iv
		);

		if ($encryptedContent === false) {
			$error = 'Encryption (symmetric) of content failed';
			$this->logger->error(
				$error . \openssl_error_string(),
				['app' => 'encryption']
			);
			throw new EncryptionFailedException($error);
		}

		return $encryptedContent;
	}

	/**
	 * return Cipher either from config.php or the default cipher defined in
	 * this class
	 *
	 * @return string
	 */
	public function getCipher() {
		$cipher = $this->config->getSystemValue('cipher', self::DEFAULT_CIPHER);
		if (!isset($this->supportedCiphersAndKeySize[$cipher])) {
			$this->logger->warning(
				\sprintf(
					'Unsupported cipher (%s) defined in config.php supported. Falling back to %s',
					$cipher,
					self::DEFAULT_CIPHER
				),
				['app' => 'encryption']
			);
			$cipher = self::DEFAULT_CIPHER;
		}

		// Workaround for OpenSSL 0.9.8. Fallback to an old cipher that should work.
		if (OPENSSL_VERSION_NUMBER < 0x1000101f) {
			if ($cipher === 'AES-256-CTR' || $cipher === 'AES-128-CTR') {
				$cipher = self::LEGACY_CIPHER;
			}
		}

		return $cipher;
	}

	/**
	 * get key size depending on the cipher
	 *
	 * @param string $cipher
	 * @return int
	 * @throws \InvalidArgumentException
	 */
	protected function getKeySize($cipher) {
		if (isset($this->supportedCiphersAndKeySize[$cipher])) {
			return $this->supportedCiphersAndKeySize[$cipher];
		}

		throw new \InvalidArgumentException(
			\sprintf(
				'Unsupported cipher (%s) defined.',
				$cipher
			)
		);
	}

	/**
	 * get legacy cipher
	 *
	 * @return string
	 */
	public function getLegacyCipher() {
		return self::LEGACY_CIPHER;
	}

	/**
	 * @param string $encryptedContent
	 * @param string $iv
	 * @return string
	 */
	private function concatIV($encryptedContent, $iv) {
		return $encryptedContent . '00iv00' . $iv;
	}

	/**
	 * @param string $encryptedContent
	 * @param string $signature
	 * @return string
	 */
	private function concatSig($encryptedContent, $signature) {
		return $encryptedContent . '00sig00' . $signature;
	}

	/**
	 * Note: This is _NOT_ a padding used for encryption purposes. It is solely
	 * used to achieve the PHP stream size. It has _NOTHING_ to do with the
	 * encrypted content and is not used in any crypto primitive.
	 *
	 * @param string $data
	 * @return string
	 */
	private function addPadding($data) {
		return $data . 'xxx';
	}

	/**
	 * generate password hash used to encrypt the users private key
	 *
	 * @param string $password
	 * @param string $cipher
	 * @param string $uid only used for user keys
	 * @return string
	 */
	protected function generatePasswordHash($password, $cipher, $uid = '') {
		$instanceId = $this->config->getSystemValue('instanceid');
		$instanceSecret = $this->config->getSystemValue('secret');
		$salt = \hash('sha256', $uid . $instanceId . $instanceSecret, true);
		$keySize = $this->getKeySize($cipher);

		$hash = \hash_pbkdf2(
			'sha256',
			$password,
			$salt,
			100000,
			$keySize,
			true
		);

		return $hash;
	}

	/**
	 * encrypt private key
	 *
	 * @param string $privateKey
	 * @param string $password
	 * @param string $uid for regular users, empty for system keys
	 * @return false|string
	 */
	public function encryptPrivateKey($privateKey, $password, $uid = '') {
		$cipher = $this->getCipher();
		$hash = $this->generatePasswordHash($password, $cipher, $uid);
		$encryptedKey = $this->symmetricEncryptFileContent(
			$privateKey,
			$hash,
			0,
			0
		);

		return $encryptedKey;
	}

	/**
	 * @param string $privateKey
	 * @param string $password
	 * @param string $uid for regular users, empty for system keys
	 * @return false|string
	 */
	public function decryptPrivateKey($privateKey, $password = '', $uid = '') {
		$header = $this->parseHeader($privateKey);

		if (isset($header['cipher'])) {
			$cipher = $header['cipher'];
		} else {
			$cipher = self::LEGACY_CIPHER;
		}

		if (isset($header['keyFormat'])) {
			$keyFormat = $header['keyFormat'];
		} else {
			$keyFormat = self::LEGACY_KEY_FORMAT;
		}

		if ($keyFormat === 'hash') {
			$password = $this->generatePasswordHash($password, $cipher, $uid);
		}

		$binaryEncode = isset($header['encoding']) && $header['encoding'] === self::DEFAULT_ENCODING_FORMAT;

		// If we found a header we need to remove it from the key we want to decrypt
		if (!empty($header)) {
			$privateKey = \substr(
				$privateKey,
				\strpos(
					$privateKey,
					self::HEADER_END
				) + \strlen(self::HEADER_END)
			);
		}

		$plainKey = $this->symmetricDecryptFileContent(
			$privateKey,
			$password,
			$cipher,
			0,
			0,
			$binaryEncode
		);

		if ($this->isValidPrivateKey($plainKey) === false) {
			return false;
		}

		return $plainKey;
	}

	/**
	 * check if it is a valid private key
	 *
	 * @param string $plainKey
	 * @return bool
	 */
	protected function isValidPrivateKey($plainKey) {
		$res = \openssl_get_privatekey($plainKey);
		if (\is_resource($res)) {
			$sslInfo = \openssl_pkey_get_details($res);
			if (isset($sslInfo['key'])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $keyFileContents
	 * @param string $passPhrase
	 * @param string $cipher
	 * @param int $version
	 * @param int $position
	 * @param boolean $binaryEncode
	 * @return string
	 * @throws DecryptionFailedException
	 * @throws HintException
	 */
	public function symmetricDecryptFileContent($keyFileContents, $passPhrase, $cipher = self::DEFAULT_CIPHER, $version = 0, $position = 0, $binaryEncode = false) {
		$catFile = $this->splitMetaData($keyFileContents, $cipher);

		if ($catFile['signature'] !== false) {
			try {
				$this->checkSignature($catFile['encrypted'], $passPhrase . $version . "-" . $position, $catFile['signature']);
			} catch (HintException $e) {
				// Check legacy format...
				$this->checkSignature($catFile['encrypted'], $passPhrase . $version . $position, $catFile['signature']);
			}
		}

		return $this->decrypt(
			$catFile['encrypted'],
			$catFile['iv'],
			$passPhrase,
			$cipher,
			$binaryEncode
		);
	}

	/**
	 * check for valid signature
	 *
	 * @param string $data
	 * @param string $passPhrase
	 * @param string $expectedSignature
	 * @throws HintException
	 */
	private function checkSignature($data, $passPhrase, $expectedSignature) {
		$signature = $this->createSignature($data, $passPhrase);
		if (!\hash_equals($expectedSignature, $signature)) {
			throw new HintException('Bad Signature', $this->l->t('Bad Signature'));
		}
	}

	/**
	 * create signature
	 *
	 * @param string $data
	 * @param string $passPhrase
	 * @return string
	 */
	private function createSignature($data, $passPhrase) {
		$passPhrase = \hash('sha512', $passPhrase . 'a', true);
		$signature = \hash_hmac('sha256', $data, $passPhrase);
		return $signature;
	}

	/**
	 * remove padding
	 *
	 * @param string $padded
	 * @param bool $hasSignature did the block contain a signature, in this case we use a different padding
	 * @return string|false
	 */
	private function removePadding($padded, $hasSignature = false) {
		if ($hasSignature === false && \substr($padded, -2) === 'xx') {
			return \substr($padded, 0, -2);
		} elseif ($hasSignature === true && \substr($padded, -3) === 'xxx') {
			return \substr($padded, 0, -3);
		}
		return false;
	}

	/**
	 * split meta data from encrypted file
	 * Note: for now, we assume that the meta data always start with the iv
	 *       followed by the signature, if available
	 *
	 * @param string $catFile
	 * @param string $cipher
	 * @return array
	 */
	private function splitMetaData($catFile, $cipher) {
		if ($this->hasSignature($catFile, $cipher)) {
			$catFile = $this->removePadding($catFile, true);
			$meta = \substr($catFile, -93);
			$iv = \substr($meta, \strlen('00iv00'), 16);
			$sig = \substr($meta, 22 + \strlen('00sig00'));
			$encrypted = \substr($catFile, 0, -93);
		} else {
			$catFile = $this->removePadding($catFile);
			$meta = \substr($catFile, -22);
			$iv = \substr($meta, -16);
			$sig = false;
			$encrypted = \substr($catFile, 0, -22);
		}

		return [
			'encrypted' => $encrypted,
			'iv' => $iv,
			'signature' => $sig
		];
	}

	/**
	 * check if encrypted block is signed
	 *
	 * @param string $catFile
	 * @param string $cipher
	 * @return bool
	 * @throws HintException
	 */
	private function hasSignature($catFile, $cipher) {
		$meta = \substr($catFile, -93);
		$signaturePosition = \strpos($meta, '00sig00');

		// enforce signature for the new 'CTR' ciphers
		if ($signaturePosition === false && \strpos(\strtolower($cipher), 'ctr') !== false) {
			throw new HintException('Missing Signature', $this->l->t('Missing Signature'));
		}

		return ($signaturePosition !== false);
	}

	/**
	 * @param string $encryptedContent
	 * @param string $iv
	 * @param string $passPhrase
	 * @param string $cipher
	 * @param boolean $binaryEncode
	 * @return string
	 * @throws DecryptionFailedException
	 */
	private function decrypt($encryptedContent, $iv, $passPhrase = '', $cipher = self::DEFAULT_CIPHER, $binaryEncode = false) {
		$options = $binaryEncode === true ? OPENSSL_RAW_DATA : 0;
		$plainContent = \openssl_decrypt(
			$encryptedContent,
			$cipher,
			$passPhrase,
			$options,
			$iv
		);

		if ($plainContent === false) {
			throw new DecryptionFailedException('Encryption library: Decryption (symmetric) of content failed: ' . \openssl_error_string());
		}

		return $plainContent;
	}

	/**
	 * @param string $data
	 * @return array
	 */
	protected function parseHeader($data) {
		$result = [];

		if (\substr($data, 0, \strlen(self::HEADER_START)) === self::HEADER_START) {
			$endAt = \strpos($data, self::HEADER_END);
			$header = \substr($data, 0, $endAt + \strlen(self::HEADER_END));

			// +1 not to start with an ':' which would result in empty element at the beginning
			$exploded = \explode(
				':',
				\substr($header, \strlen(self::HEADER_START) + 1)
			);

			$element = \array_shift($exploded);

			while ($element != self::HEADER_END) {
				$result[$element] = \array_shift($exploded);
				$element = \array_shift($exploded);
			}
		}

		return $result;
	}

	/**
	 * generate initialization vector
	 *
	 * @return string
	 * @throws GenericEncryptionException
	 */
	private function generateIv() {
		return \random_bytes(16);
	}

	/**
	 * Generate a cryptographically secure pseudo-random 256-bit ASCII key, used
	 * as file key
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function generateFileKey() {
		return \random_bytes(32);
	}

	/**
	 * @param string $encKeyFile
	 * @param string $shareKey
	 * @param string $privateKey
	 * @return string
	 * @throws MultiKeyDecryptException
	 */
	public function multiKeyDecrypt($encKeyFile, $shareKey, $privateKey) {
		if (!$encKeyFile) {
			throw new MultiKeyDecryptException('Cannot multikey decrypt empty plain content');
		}

		/** @phan-suppress-next-line PhanParamTooFewInternal */
		if (\openssl_open($encKeyFile, $plainContent, $shareKey, $privateKey)) {
			return $plainContent;
		} else {
			throw new MultiKeyDecryptException('multikeydecrypt with share key failed:' . \openssl_error_string());
		}
	}

	/**
	 * @param string $plainContent
	 * @param array $keyFiles
	 * @return array
	 * @throws MultiKeyEncryptException
	 */
	public function multiKeyEncrypt($plainContent, array $keyFiles) {
		// openssl_seal returns false without errors if plaincontent is empty
		// so trigger our own error
		if (empty($plainContent)) {
			throw new MultiKeyEncryptException('Cannot multikeyencrypt empty plain content');
		}

		// Set empty vars to be set by openssl by reference
		$sealed = '';
		$shareKeys = [];
		$mappedShareKeys = [];

		/** @phan-suppress-next-line PhanParamTooFewInternal */
		if (\openssl_seal($plainContent, $sealed, $shareKeys, $keyFiles)) {
			$i = 0;

			// Ensure each shareKey is labelled with its corresponding key id
			foreach ($keyFiles as $userId => $publicKey) {
				/** @phan-suppress-next-line PhanTypeInvalidDimOffset */
				$mappedShareKeys[$userId] = $shareKeys[$i];
				$i++;
			}

			return [
				'keys' => $mappedShareKeys,
				'data' => $sealed
			];
		} else {
			throw new MultiKeyEncryptException('multikeyencryption failed ' . \openssl_error_string());
		}
	}

	/**
	 * @return bool
	 */
	public function useLegacyEncoding(): bool {
		return $this->useLegacyEncoding;
	}
}
