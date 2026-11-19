<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Test\Unit\Model\Validator;

use PHPUnit\Framework\TestCase;
use Sidelah\CurrencyConverter\Api\CurrencyProviderInterface;
use Sidelah\CurrencyConverter\Model\Validator\CurrencyCodeValidator;

class CurrencyCodeValidatorTest extends TestCase
{
    /**
     * @var CurrencyCodeValidator|null
     */
    private ?CurrencyCodeValidator $validator = null;

    protected function setUp(): void
    {
        $provider = $this->createMock(CurrencyProviderInterface::class);
        $provider->method('getCurrencies')->willReturn([
            'USD' => ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'type' => 'currency'],
            '1INCH' => ['code' => '1INCH', 'name' => '1inch', 'symbol' => '', 'type' => 'crypto'],
        ]);
        $this->validator = new CurrencyCodeValidator($provider);
    }

    protected function tearDown(): void
    {
        $this->validator = null;
        parent::tearDown();
    }

    /**
     * @dataProvider codeProvider
     */
    public function testValidate(string $code, bool $expected): void
    {
        $this->assertSame($expected, $this->validator->validate($code));
    }

    public static function codeProvider(): array
    {
        return [
            'supported fiat' => ['USD', true],
            'supported code starting with digit' => ['1INCH', true],
            'well-formed but unsupported' => ['XYZ', false],
            'empty' => ['', false],
            'lower case is not normalised here' => ['usd', false],
            'path traversal' => ['USD/../currencies', false],
        ];
    }
}
