<?php

declare(strict_types=1);

namespace PdfLib\Security\Encryption;

/**
 * AES-128 encryption handler for PDF.
 *
 * Uses AES-128-CBC with PKCS#7 padding.
 * Requires PDF 1.5+ (V=4, R=4).
 */
final class Aes128Handler implements EncryptionHandler
{
    // Padding string as defined in PDF spec (ISO 32000-1, Section 7.6.3.3)
    private const PADDING = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    // AES "sAlT" marker
    private const AES_SALT = "sAlT";

    public function getAlgorithm(): string
    {
        return 'AES-128';
    }

    public function getKeyLength(): int
    {
        return 128;
    }

    public function getV(): int
    {
        return 4;
    }

    public function getR(): int
    {
        return 4;
    }

    public function encrypt(string $data, string $key, int $objectNumber, int $generationNumber): string
    {
        $objectKey = $this->computeObjectKey($key, $objectNumber, $generationNumber);

        // Generate random IV
        $iv = random_bytes(16);

        // Encrypt with AES-128-CBC
        $encrypted = openssl_encrypt(
            $data,
            'AES-128-CBC',
            $objectKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('AES-128 encryption failed');
        }

        // Prepend IV to encrypted data
        return $iv . $encrypted;
    }

    public function decrypt(string $data, string $key, int $objectNumber, int $generationNumber): string
    {
        if (strlen($data) < 16) {
            throw new \InvalidArgumentException('Encrypted data too short');
        }

        $objectKey = $this->computeObjectKey($key, $objectNumber, $generationNumber);

        // Extract IV
        $iv = substr($data, 0, 16);
        $encryptedData = substr($data, 16);

        // Decrypt with AES-128-CBC
        $decrypted = openssl_decrypt(
            $encryptedData,
            'AES-128-CBC',
            $objectKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new \RuntimeException('AES-128 decryption failed');
        }

        return $decrypted;
    }

    public function computeEncryptionKey(
        string $password,
        string $ownerKey,
        int $permissions,
        string $documentId,
        bool $encryptMetadata = true
    ): string {
        // Pad or truncate password
        $password = substr($password . self::PADDING, 0, 32);

        // Hash password + O + P + ID
        $data = $password
            . $ownerKey
            . pack('V', $permissions)
            . $documentId;

        // Add 0xFFFFFFFF only if NOT encrypting metadata (PDF spec Algorithm 2)
        if (!$encryptMetadata) {
            $data .= "\xFF\xFF\xFF\xFF";
        }

        $hash = md5($data, true);

        // Perform 50 additional MD5 iterations for R >= 3
        for ($i = 0; $i < 50; $i++) {
            $hash = md5(substr($hash, 0, 16), true);
        }

        return substr($hash, 0, 16);
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

        // Perform 50 additional iterations
        for ($i = 0; $i < 50; $i++) {
            $hash = md5($hash, true);
        }

        $key = substr($hash, 0, 16);

        // Pad user password and encrypt
        $userPassword = substr($userPassword . self::PADDING, 0, 32);
        $encrypted = $this->rc4($userPassword, $key);

        // Perform 19 additional encryptions
        for ($i = 1; $i <= 19; $i++) {
            $iterKey = '';
            for ($j = 0; $j < strlen($key); $j++) {
                $iterKey .= chr(ord($key[$j]) ^ $i);
            }
            $encrypted = $this->rc4($encrypted, $iterKey);
        }

        return $encrypted;
    }

    public function computeUserKey(string $encryptionKey, string $documentId = ''): string
    {
        // Hash padding + document ID (Algorithm 5)
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
     * Compute object-specific key for AES.
     */
    private function computeObjectKey(string $key, int $objectNumber, int $generationNumber): string
    {
        // Append object and generation numbers + AES salt
        $objectKey = $key
            . chr($objectNumber & 0xFF)
            . chr(($objectNumber >> 8) & 0xFF)
            . chr(($objectNumber >> 16) & 0xFF)
            . chr($generationNumber & 0xFF)
            . chr(($generationNumber >> 8) & 0xFF)
            . self::AES_SALT;

        $hash = md5($objectKey, true);

        // Key length is min(key length + 5, 16) = 16 for AES-128
        return $hash;
    }

    /**
     * RC4 encryption (used for O and U computation).
     */
    private function rc4(string $data, string $key): string
    {
        $s = range(0, 255);
        $keyLen = strlen($key);

        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % $keyLen])) % 256;
            $temp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $temp;
        }

        $result = '';
        $i = 0;
        $j = 0;
        $dataLen = strlen($data);

        for ($k = 0; $k < $dataLen; $k++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
            $temp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $temp;
            $result .= chr(ord($data[$k]) ^ $s[($s[$i] + $s[$j]) % 256]);
        }

        return $result;
    }
}
