<?php

declare(strict_types=1);

namespace PdfLib\Security\Encryption;

/**
 * Interface for PDF encryption handlers.
 */
interface EncryptionHandler
{
    /**
     * Get the encryption algorithm name.
     */
    public function getAlgorithm(): string;

    /**
     * Get the key length in bits.
     */
    public function getKeyLength(): int;

    /**
     * Get the V value for the encryption dictionary.
     */
    public function getV(): int;

    /**
     * Get the R value for the encryption dictionary.
     */
    public function getR(): int;

    /**
     * Encrypt data.
     *
     * @param string $data The data to encrypt
     * @param string $key The encryption key
     * @param int $objectNumber The PDF object number
     * @param int $generationNumber The PDF generation number
     * @return string The encrypted data
     */
    public function encrypt(string $data, string $key, int $objectNumber, int $generationNumber): string;

    /**
     * Decrypt data.
     *
     * @param string $data The data to decrypt
     * @param string $key The encryption key
     * @param int $objectNumber The PDF object number
     * @param int $generationNumber The PDF generation number
     * @return string The decrypted data
     */
    public function decrypt(string $data, string $key, int $objectNumber, int $generationNumber): string;

    /**
     * Compute the encryption key.
     *
     * @param string $password The password
     * @param string $ownerKey The O value from encryption dictionary
     * @param int $permissions The P value (permissions)
     * @param string $documentId The document ID
     * @return string The computed key
     */
    public function computeEncryptionKey(
        string $password,
        string $ownerKey,
        int $permissions,
        string $documentId
    ): string;

    /**
     * Compute the O (owner) value.
     */
    public function computeOwnerKey(string $ownerPassword, string $userPassword): string;

    /**
     * Compute the U (user) value.
     */
    public function computeUserKey(string $encryptionKey): string;
}
