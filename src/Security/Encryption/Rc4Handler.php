<?php

declare(strict_types=1);

namespace PdfLib\Security\Encryption;

/**
 * RC4 encryption handler for PDF.
 *
 * Supports both 40-bit and 128-bit RC4 encryption.
 */
final class Rc4Handler implements EncryptionHandler
{
    // Padding string as defined in PDF spec (ISO 32000-1, Section 7.6.3.3)
    private const PADDING = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    private int $keyLength;
    private int $v;
    private int $r;

    /**
     * @param int $keyLength Key length in bits (40 or 128)
     */
    public function __construct(int $keyLength = 128)
    {
        if ($keyLength !== 40 && $keyLength !== 128) {
            throw new \InvalidArgumentException('RC4 key length must be 40 or 128 bits');
        }

        $this->keyLength = $keyLength;

        if ($keyLength === 40) {
            $this->v = 1;
            $this->r = 2;
        } else {
            $this->v = 2;
            $this->r = 3;
        }
    }

    /**
     * Create 40-bit RC4 handler.
     */
    public static function rc4_40(): self
    {
        return new self(40);
    }

    /**
     * Create 128-bit RC4 handler.
     */
    public static function rc4_128(): self
    {
        return new self(128);
    }

    public function getAlgorithm(): string
    {
        return 'RC4';
    }

    public function getKeyLength(): int
    {
        return $this->keyLength;
    }

    public function getV(): int
    {
        return $this->v;
    }

    public function getR(): int
    {
        return $this->r;
    }

    public function encrypt(string $data, string $key, int $objectNumber, int $generationNumber): string
    {
        $objectKey = $this->computeObjectKey($key, $objectNumber, $generationNumber);
        return $this->rc4($data, $objectKey);
    }

    public function decrypt(string $data, string $key, int $objectNumber, int $generationNumber): string
    {
        // RC4 is symmetric - encryption and decryption are the same
        return $this->encrypt($data, $key, $objectNumber, $generationNumber);
    }

    public function computeEncryptionKey(
        string $password,
        string $ownerKey,
        int $permissions,
        string $documentId
    ): string {
        // Pad or truncate password
        $password = substr($password . self::PADDING, 0, 32);

        // Hash password + O + P + ID
        $hash = md5(
            $password
            . $ownerKey
            . pack('V', $permissions)
            . $documentId,
            true
        );

        // For R=3, perform 50 additional MD5 iterations
        if ($this->r >= 3) {
            $keyLen = $this->keyLength / 8;
            for ($i = 0; $i < 50; $i++) {
                $hash = md5(substr($hash, 0, $keyLen), true);
            }
        }

        return substr($hash, 0, $this->keyLength / 8);
    }

    public function computeOwnerKey(string $ownerPassword, string $userPassword): string
    {
        // If owner password is empty, use user password
        if ($ownerPassword === '') {
            $ownerPassword = $userPassword;
        }

        // Pad owner password
        $ownerPassword = substr($ownerPassword . self::PADDING, 0, 32);

        // Hash owner password
        $hash = md5($ownerPassword, true);

        // For R=3, perform 50 additional iterations
        if ($this->r >= 3) {
            for ($i = 0; $i < 50; $i++) {
                $hash = md5($hash, true);
            }
        }

        $key = substr($hash, 0, $this->keyLength / 8);

        // Pad user password and encrypt
        $userPassword = substr($userPassword . self::PADDING, 0, 32);
        $encrypted = $this->rc4($userPassword, $key);

        // For R=3, perform 19 additional encryptions
        if ($this->r >= 3) {
            for ($i = 1; $i <= 19; $i++) {
                $iterKey = '';
                for ($j = 0; $j < strlen($key); $j++) {
                    $iterKey .= chr(ord($key[$j]) ^ $i);
                }
                $encrypted = $this->rc4($encrypted, $iterKey);
            }
        }

        return $encrypted;
    }

    public function computeUserKey(string $encryptionKey, string $documentId = ''): string
    {
        if ($this->r === 2) {
            // R=2: Encrypt padding string
            return $this->rc4(self::PADDING, $encryptionKey);
        }

        // R=3: Hash padding + document ID, then encrypt with iterations
        $hash = md5(self::PADDING . $documentId, true);
        $encrypted = $this->rc4($hash, $encryptionKey);

        for ($i = 1; $i <= 19; $i++) {
            $iterKey = '';
            for ($j = 0; $j < strlen($encryptionKey); $j++) {
                $iterKey .= chr(ord($encryptionKey[$j]) ^ $i);
            }
            $encrypted = $this->rc4($encrypted, $iterKey);
        }

        // Pad to 32 bytes
        return $encrypted . str_repeat("\x00", 16);
    }

    /**
     * Compute object-specific key.
     */
    private function computeObjectKey(string $key, int $objectNumber, int $generationNumber): string
    {
        // Append object and generation numbers
        $objectKey = $key
            . chr($objectNumber & 0xFF)
            . chr(($objectNumber >> 8) & 0xFF)
            . chr(($objectNumber >> 16) & 0xFF)
            . chr($generationNumber & 0xFF)
            . chr(($generationNumber >> 8) & 0xFF);

        $hash = md5($objectKey, true);

        // Key length is min(key length + 5, 16) bytes
        $keyLen = min(strlen($key) + 5, 16);
        return substr($hash, 0, $keyLen);
    }

    /**
     * RC4 encryption/decryption.
     */
    private function rc4(string $data, string $key): string
    {
        // Initialize S-box
        $s = range(0, 255);
        $keyLen = strlen($key);

        // Key scheduling algorithm (KSA)
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % $keyLen])) % 256;
            // Swap
            $temp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $temp;
        }

        // Pseudo-random generation algorithm (PRGA)
        $result = '';
        $i = 0;
        $j = 0;
        $dataLen = strlen($data);

        for ($k = 0; $k < $dataLen; $k++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
            // Swap
            $temp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $temp;
            // XOR
            $result .= chr(ord($data[$k]) ^ $s[($s[$i] + $s[$j]) % 256]);
        }

        return $result;
    }
}
