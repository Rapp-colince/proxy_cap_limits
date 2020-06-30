<?php
declare(strict_types=1);

namespace tests\components\recoveryStrategies;

use components\proxy\recoveryStrategies\LinearStrategy;
use models\ProxyCapLimit;
use models\ProxyCapLimitError;
use PHPUnit\Framework\TestCase;

class LinearStrategyTest extends TestCase
{
    /**
     * Some combination of parameters for each of 6 control points of a linear strategy
     * 1. float $reductionFactor
     * 2. int $capMax
     * 3. int $coolDown
     * 4. int $recoveryTimespan
     * 5. array $expectedResults. [0] - value for 1, 2 and 3 time points. [1] - value for 4-th time point.
     * @return array
     */
    public function getCurrentCapProvider(): array
    {
        return [
            'classic data' => [.25, 2800, 15, 5400, [700, 2100]],
            'reductionFactor = 1' => [1, 2800, 15, 5400, [2800, 2800]],
            'reductionFactor = .84' => [.84, 2800, 15, 5400, [2352, 2800]],
            'reductionFactor = 0' => [0, 2800, 15, 5400, [0, 1400]],
            'capMax = 1000' => [.25, 1000, 15, 5400, [250, 750]],
            'capMax = 0' => [.25, 0, 15, 5400, [0, 0]],
            'coolDown = 0' => [.25, 2800, 0, 5400, [700, 2100]],
            'recoveryTimespan = 1' => [.25, 2800, 15, 1, [700, 700]],
        ];
    }

    /**
     * @param float $reductionFactor
     * @param int $capMax
     * @param int $coolDown
     * @param int $recoveryTimespan
     * @param array $expectedResults
     * @dataProvider getCurrentCapProvider
     */
    public function testGetCurrentCap(
        float $reductionFactor,
        int $capMax,
        int $coolDown,
        int $recoveryTimespan,
        array $expectedResults
    ): void {
        $lastCaughtDttm = date('Y-m-d H:i:s');

        $timeControlPoints = [
            0 => strtotime($lastCaughtDttm) - 1, // Moment of before of caught
            1 => strtotime($lastCaughtDttm), // Time of caught
            2 => strtotime($lastCaughtDttm) + (int)floor($coolDown / 2), // Some time after caught
            3 => strtotime($lastCaughtDttm) + $coolDown, // A moment of begin of recovery
            4 => strtotime($lastCaughtDttm) + $coolDown + (int)floor($recoveryTimespan / 2), // Some time after begin of recovery
            5 => strtotime($lastCaughtDttm) + $coolDown + $recoveryTimespan, // A moment of end of recovery
            6 => strtotime($lastCaughtDttm) + $coolDown + $recoveryTimespan + 1, // A moment after end of recovery
        ];

        foreach ($timeControlPoints as $timePointIndex => $currentTs) {
            $proxyCapLimit = new ProxyCapLimit();
            $proxyCapLimit->cap_max = $capMax;

            $proxyCapLimitError = new ProxyCapLimitError();
            $proxyCapLimitError->last_caught_dttm = $lastCaughtDttm;
            $proxyCapLimitError->reduction_factor = $reductionFactor;

            $strategyObject = new LinearStrategy(.95, $recoveryTimespan, $coolDown);
            $currentCap = $strategyObject->getCurrentCap($proxyCapLimit, $proxyCapLimitError, $currentTs);

            if ($timePointIndex === 0) {
                $expectedResult = $capMax;
            } elseif ($timePointIndex < 4) {
                $expectedResult = $expectedResults[0];
            } elseif ($timePointIndex === 4) {
                $expectedResult = $expectedResults[1];
            } else {
                $expectedResult = $capMax;
            }
            $this->assertSame($currentCap, $expectedResult);
        }
    }

    /**
     * @return array
     */
    public function calculateCurrentReductionFactorProvider(): array
    {
        return [
            'classic data' => [2800, 1, .95, .95],
            'cap_max = 1000' => [1000, 1, .95, .95],
            'cap_max = 0' => [0, 1, .95, 1],
            'reductionFactor = .84' => [2800, .84, .95, 0.79],
            'reductionFactor = 0' => [2800, 0, .95, 0],
            'reduceFactor = 1' => [2800, 1, 1, 1],
            'reduceFactor = 0' => [2800, 1, 0, 0],
        ];
    }

    /**
     * @param int $capMax
     * @param float $reductionFactor
     * @param float $reduceFactor
     * @param float $expectedResult
     * @dataProvider calculateCurrentReductionFactorProvider
     */
    public function testCalculateCurrentReductionFactor(
        int $capMax,
        float $reductionFactor,
        float $reduceFactor,
        float $expectedResult
    ): void {
        $currentTs = time();

        $proxyCapLimit = new ProxyCapLimit();
        $proxyCapLimit->cap_max = $capMax;

        $proxyCapLimitError = new ProxyCapLimitError();
        $proxyCapLimitError->last_caught_dttm = date('Y-m-d H:i:s', $currentTs);
        $proxyCapLimitError->reduction_factor = $reductionFactor;

        $strategyObject = new LinearStrategy($reduceFactor, 540, 15);
        $currentReductionFactor = $strategyObject->calculateCurrentReductionFactor(
            $proxyCapLimit,
            $proxyCapLimitError,
            $currentTs
        );

        $this->assertSame($currentReductionFactor, $expectedResult);
    }

    /**
     * 1. float reduceFactor
     * 2. int recoveryTimespan
     * 3. int coolDown
     * 4. int expectedResult
     */
    public function isValidProvider(): array
    {
        return [
            'correct data' => [.95, 5400, 15, true],
            'reduceFactor < 0' => [-.1, 5400, 15, false],
            'reduceFactor > 1' => [1.01, 5400, 15, false],
            'recoveryTimespan < 0' => [.95, -1, 15, false],
            'recoveryTimespan = 0' => [.95, 0, 15, false],
            'coolDown < 0' => [.95, 5400, -1, false],
        ];
    }

    /**
     * @param float $reduceFactor
     * @param int $recoveryTimespan
     * @param int $coolDown
     * @param bool $expectedResult
     * @dataProvider isValidProvider
     */
    public function testIsValid(float $reduceFactor, int $recoveryTimespan, int $coolDown, bool $expectedResult): void
    {
        $strategyObject = new LinearStrategy($reduceFactor, $recoveryTimespan, $coolDown);
        $this->assertSame($strategyObject->isValid(), $expectedResult);
    }

    /**
     * 1. float reduceFactor
     * 2. int recoveryTimespan
     * 3. int coolDown
     * 4. int expectedResult
     */
    public function isOverdueOptionsProvider(): array
    {
        $time = time();
        return [
            'correct data' => [0, 0, date('Y-m-d H:i:s', $time), $time, false],
            'recoveryTimespan test' => [-1, 0, date('Y-m-d H:i:s', $time), $time, true],
            'coolDown test' => [0, -1, date('Y-m-d H:i:s', $time), $time, true],
            'last_caught_dttm test' => [0, 0, date('Y-m-d H:i:s', $time - 1), $time, true],
        ];
    }

    /**
     * @param int $recoveryTimespan
     * @param int $coolDown
     * @param string $lastCaughtDttm
     * @param int $time
     * @param bool $expectedResult
     * @dataProvider isOverdueOptionsProvider
     */
    public function testIsOverdue(
        int $recoveryTimespan,
        int $coolDown,
        string $lastCaughtDttm,
        int $time,
        bool $expectedResult
    ): void {
        $proxyCapLimitError = new ProxyCapLimitError();
        $proxyCapLimitError->last_caught_dttm = $lastCaughtDttm;

        $strategyObject = new LinearStrategy(.95, $recoveryTimespan, $coolDown);
        $this->assertSame($strategyObject->isOverdue($proxyCapLimitError, $time), $expectedResult);
    }
}
