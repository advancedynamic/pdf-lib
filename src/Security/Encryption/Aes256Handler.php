<?php

declare(strict_types=1);

namespace PdfLib\Security\Encryption;

/**
 * AES-256 encryption handler for PDF.
 *
 * Uses AES-256-CBC with PKCS#7 padding.
 * Implements PDF 2.0 (ISO 32000-2) with V=5, R=6.
 */
final class Aes256Handler implements EncryptionHandler
{
    public function getAlgorithm(): string
    {
        return 'AES-256';
    }

    public function getKeyLength(): int
    {
        return 256;
    }

    public function getV(): int
    {
        return 5;
    }

    public function getR(): int
    {
        return 6;
    }

    public function encrypt(string $data, string $key, int $objectNumber, int $generationNumber): string
    {
        // AES-256 uses the file encryption key directly (no object key derivation)
        // Generate random IV
        $iv = random_bytes(16);

        // Encrypt with AES-256-CBC
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('AES-256 encryption failed');
        }

        // Prepend IV to encrypted data
        return $iv . $encrypted;
    }

    public function decrypt(string $data, string $key, int $objectNumber, int $generationNumber): string
    {
        if (strlen($data) < 16) {
            throw new \InvalidArgumentException('Encrypted data too short');
        }

        // Extract IV
        $iv = substr($data, 0, 16);
        $encryptedData = substr($data, 16);

        // Decrypt with AES-256-CBC
        $decrypted = openssl_decrypt(
            $encryptedData,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new \RuntimeException('AES-256 decryption failed');
        }

        return $decrypted;
    }

    public function computeEncryptionKey(
        string $password,
        string $ownerKey,
        int $permissions,
        string $documentId
    ): string {
        // For AES-256 R=6, generate random 32-byte key
        return random_bytes(32);
    }

    public function computeOwnerKey(string $ownerPassword, string $userPassword): string
    {
        // For R=6, this is computed differently using the file key
        // This is a placeholder - actual implementation needs file key
        $ownerValidation = random_bytes(8);
        $ownerSalt = random_bytes(8);

        return $this->computeHashR6($ownerPassword, $ownerValidation, '')
            . $ownerValidation
            . $ownerSalt;
    }

    public function computeUserKey(string $encryptionKey): string
    {
        // For R=6, this is computed using the file key
        $userValidation = random_bytes(8);
        $userSalt = random_bytes(8);

        return $this->computeHashR6('', $userValidation, '')
            . $userValidation
            . $userSalt;
    }

    /**
     * Generate AES-256 encryption data (O, U, OE, UE, Perms).
     *
     * Implements Algorithm 8, 9, and 10 from ISO 32000-2 (PDF 2.0).
     *
     * @return array{
     *     key: string,
     *     O: string,
     *     U: string,
     *     OE: string,
     *     UE: string,
     *     Perms: string
     * }
     */
    public function generateEncryptionData(
        string $userPassword,
        string $ownerPassword,
        int $permissions
    ): array {
        // Truncate passwords to 127 UTF-8 bytes (per spec)
        $userPassword = substr($userPassword, 0, 127);
        $ownerPassword = substr($ownerPassword, 0, 127);

        // Generate random file encryption key (32 bytes)
        $fileKey = random_bytes(32);

        // Generate random salts (8 bytes each)
        $userValidationSalt = random_bytes(8);
        $userKeySalt = random_bytes(8);
        $ownerValidationSalt = random_bytes(8);
        $ownerKeySalt = random_bytes(8);

        // Algorithm 8: Compute U value (48 bytes)
        // U = hash(user_password + UVS) + UVS + UKS
        $userHash = $this->computeHashR6($userPassword, $userValidationSalt, '');
        $u = $userHash . $userValidationSalt . $userKeySalt;

        // Algorithm 8 step b: Compute UE value (32 bytes)
        // UE = AES-256-CBC(fileKey, key=hash(user_password + UKS), iv=0)
        $ueHashKey = $this->computeHashR6($userPassword, $userKeySalt, '');
        $ue = $this->aesEncryptNoPadding($fileKey, $ueHashKey);

        // Algorithm 9: Compute O value (48 bytes)
        // O = hash(owner_password + OVS + U) + OVS + OKS
        $ownerHash = $this->computeHashR6($ownerPassword, $ownerValidationSalt, $u);
        $o = $ownerHash . $ownerValidationSalt . $ownerKeySalt;

        // Algorithm 9 step b: Compute OE value (32 bytes)
        // OE = AES-256-CBC(fileKey, key=hash(owner_password + OKS + U), iv=0)
        $oeHashKey = $this->computeHashR6($ownerPassword, $ownerKeySalt, $u);
        $oe = $this->aesEncryptNoPadding($fileKey, $oeHashKey);

        // Algorithm 10: Compute Perms value (16 bytes)
        $perms = $this->computePermsValue($permissions, $fileKey);

        return [
            'key' => $fileKey,
            'O' => $o,
            'U' => $u,
            'OE' => $oe,
            'UE' => $ue,
            'Perms' => $perms,
        ];
    }

    /**
     * Compute hash using Algorithm 2.B (ISO 32000-2).
     *
     * This is the iterative hash algorithm for AES-256 R=6.
     *
     * @param string $password The password (up to 127 bytes)
     * @param string $salt 8-byte salt (validation or key salt)
     * @param string $userKey The U value (for owner key computation) or empty string
     */
    private function computeHashR6(string $password, string $salt, string $userKey): string
    {
        // Step a: Compute initial SHA-256 hash
        $input = $password . $salt . $userKey;
        $k = hash('sha256', $input, true);

        // Step b-d: Iterative hash loop
        $round = 0;
        $lastByte = 0;

        do {
            // Step b: Prepare K1 = (password + K + userKey) repeated 64 times
            $k1 = str_repeat($password . $k . $userKey, 64);

            // Step c: Encrypt K1 using AES-128-CBC
            // Key = first 16 bytes of K, IV = next 16 bytes of K
            $aesKey = substr($k, 0, 16);
            $aesIv = substr($k, 16, 16);

            $e = openssl_encrypt($k1, 'AES-128-CBC', $aesKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $aesIv);

            if ($e === false) {
                throw new \RuntimeException('AES-128-CBC encryption failed in hash computation');
            }

            // Step d: Compute sum of first 16 bytes of E, mod 3 to select hash
            $sum = 0;
            for ($i = 0; $i < 16; $i++) {
                $sum += ord($e[$i]);
            }

            $k = match ($sum % 3) {
                0 => hash('sha256', $e, true),
                1 => hash('sha384', $e, true),
                2 => hash('sha512', $e, true),
            };

            // Get last byte for termination check
            $lastByte = ord(substr($e, -1));
            $round++;

        } while ($round < 64 || ($round < 96 && $lastByte > ($round - 32)));

        // Return first 32 bytes
        return substr($k, 0, 32);
    }

    /**
     * AES-256-CBC encryption without padding.
     *
     * Used for encrypting the file key to produce UE and OE values.
     */
    private function aesEncryptNoPadding(string $data, string $key): string
    {
        // Use zero IV per spec
        $iv = str_repeat("\x00", 16);

        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('AES-256 encryption failed');
        }

        return $encrypted;
    }

    /**
     * Compute Perms value (Algorithm 10).
     *
     * Creates a 16-byte plaintext and encrypts it with AES-256-ECB.
     */
    private function computePermsValue(int $permissions, string $fileKey): string
    {
        // Build 16-byte plaintext per Algorithm 10
        // Bytes 0-3: Permission flags (little-endian, unsigned)
        // Bytes 4-7: 0xFFFFFFFF
        // Byte 8: 'T' if encrypt metadata, 'F' otherwise
        // Bytes 9-11: "adb" (magic string)
        // Bytes 12-15: Random

        $perms = pack('V', $permissions)  // Bytes 0-3 (little-endian)
            . "\xFF\xFF\xFF\xFF"          // Bytes 4-7
            . 'T'                          // Byte 8 (EncryptMetadata = true)
            . 'adb'                        // Bytes 9-11 (magic)
            . random_bytes(4);             // Bytes 12-15 (random)

        // Encrypt with AES-256-ECB (no IV, no padding)
        $encrypted = openssl_encrypt(
            $perms,
            'AES-256-ECB',
            $fileKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Perms encryption failed');
        }

        return $encrypted;
    }
}
