<?php
declare(strict_types=1);

/**
 * Tests de référence TOTP — codes PHP pour valider le JS
 *
 * Ces tests génèrent les mêmes codes que le JS devrait produire.
 * Ils servent de vecteurs de test pour la validation cross-language.
 */
class TotpReferenceTest extends TestCase
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Decode Base32 en PHP (copie de SENTINEL Base32::decode)
     */
    private function base32Decode(string $data): string
    {
        $data = strtoupper(rtrim($data, '='));
        $binary = '';
        foreach (str_split($data) as $char) {
            $index = strpos(self::ALPHABET, $char);
            $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $result = '';
        $chunks = str_split($binary, 8);
        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 8) break;
            $result .= chr(bindec($chunk));
        }
        return $result;
    }

    /**
     * Generate TOTP code en PHP (copie de SENTINEL TotpService::computeCode)
     */
    private function generateCode(string $secret, int $timestamp): string
    {
        $key = $this->base32Decode($secret);
        $counter = intdiv($timestamp, 30);
        $timeBytes = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $timeBytes, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;
        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    // ─── Tests de référence avec seed connu ──────

    public function testFredSeedAtTimestamp1740000000(): void
    {
        // JBSWY3DPEHPK3PXP = seed de Fred (CIV-0001-0001-6)
        $code = $this->generateCode('JBSWY3DPEHPK3PXP', 1740000000);
        $this->assertEquals('655327', $code);
    }

    public function testFredSeedAtTimestamp1740000030(): void
    {
        $code = $this->generateCode('JBSWY3DPEHPK3PXP', 1740000030);
        $this->assertEquals('126155', $code);
    }

    public function testJasonSeedAtTimestamp1740000000(): void
    {
        // GEZDGNBVGY3TQOJQ = seed de Jason (CIV-0001-0002-4)
        $code = $this->generateCode('GEZDGNBVGY3TQOJQ', 1740000000);
        $this->assertMatchesRegex('/^\d{6}$/', $code, 'Code doit être 6 chiffres');
    }

    public function testBase32DecodeKnownValues(): void
    {
        // "Hello!" en Base32 = JBSWY3DPEE
        // JBSWY3DPEHPK3PXP décode en "Hello!^_P" (les bytes bruts du secret)
        $decoded = $this->base32Decode('JBSWY3DPEHPK3PXP');
        $this->assertGreaterThan(0, strlen($decoded));
        $this->assertEquals(10, strlen($decoded), 'JBSWY3DPEHPK3PXP = 10 bytes');
    }

    public function testCodeIs6Digits(): void
    {
        $code = $this->generateCode('JBSWY3DPEHPK3PXP', time());
        $this->assertMatchesRegex('/^\d{6}$/', $code);
    }

    public function testDifferentPeriodsProduceDifferentCodes(): void
    {
        $code1 = $this->generateCode('JBSWY3DPEHPK3PXP', 1740000000);
        $code2 = $this->generateCode('JBSWY3DPEHPK3PXP', 1740000030);
        $this->assertTrue($code1 !== $code2, 'Différentes périodes doivent produire différents codes');
    }

    public function testSamePeriodProducesSameCode(): void
    {
        $code1 = $this->generateCode('JBSWY3DPEHPK3PXP', 1740000000);
        $code2 = $this->generateCode('JBSWY3DPEHPK3PXP', 1740000015);
        $this->assertEquals($code1, $code2, 'Même période (30s) doit donner le même code');
    }

    public function testDifferentSecretsProduceDifferentCodes(): void
    {
        $code1 = $this->generateCode('JBSWY3DPEHPK3PXP', 1740000000);
        $code2 = $this->generateCode('GEZDGNBVGY3TQOJQ', 1740000000);
        $this->assertTrue($code1 !== $code2, 'Différents seeds doivent produire différents codes');
    }
}
