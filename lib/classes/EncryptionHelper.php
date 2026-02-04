<?php


namespace NextGenNoise\Utils;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

class EncryptionHelper
{

	private static $key;

	public static function init()
	{
		self::$key = Key::loadFromAsciiSafeString($_ENV['ENCRYPTION_KEY']);
	}

	public static function encrypt($plaintext)
	{
		return Crypto::encrypt($plaintext, self::$key);
	}

	public static function decrypt($ciphertext)
	{
		return Crypto::decrypt($ciphertext, self::$key);

    }
}