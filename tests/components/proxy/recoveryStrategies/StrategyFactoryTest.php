<?php
declare(strict_types=1);

namespace tests\components\recoveryStrategies;

use components\exceptions\ProxyRecoveryStrategy\UndefinedErrorTypeException;
use components\proxy\recoveryStrategies\StrategyFactory;
use components\proxy\recoveryStrategies\StrategyInterface;
use PHPUnit\Framework\TestCase;

class StrategyFactoryTest extends TestCase
{
    /**
     * @return array
     */
    public function createProvider(): array
    {
        return [
            'classic error type' => [StrategyFactory::ERROR_TYPE_CLASSIC],
            'akamai error type' => [StrategyFactory::ERROR_TYPE_AKAMAI],
        ];
    }

    /**
     * @param string $errorType
     * @throws UndefinedErrorTypeException
     * @dataProvider createProvider
     */
    public function testCreate(string $errorType): void
    {
        $strategyObject = (new StrategyFactory())->create($errorType);
        $this::assertInstanceOf(StrategyInterface::class, $strategyObject);
    }

    /**
     * @throws UndefinedErrorTypeException
     */
    public function testCreateUndefinedErrorTypeException(): void
    {
        $this::expectException(UndefinedErrorTypeException::class);
        (new StrategyFactory())->create('unused error type');
    }
}
