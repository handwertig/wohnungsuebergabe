<?php
declare(strict_types=1);

namespace App;

/**
 * UUID v4 Generator Helper
 */
class UuidHelper
{
    /**
     * Generiert eine UUID v4
     * @return string
     */
    public static function generate(): string
    {
        // Generate 16 random bytes
        $data = random_bytes(16);
        
        // Set version (4) and variant bits according to RFC 4122
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant 10
        
        // Format as UUID string
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Validiert eine UUID
     * @param string $uuid
     * @return bool
     */
    public static function isValid(string $uuid): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return preg_match($pattern, $uuid) === 1;
    }
}
