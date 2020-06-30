<?php

namespace components\proxy\recoveryStrategies;

use components\exceptions\ProxyRecoveryStrategy\UndefinedErrorTypeException;

/**
 * Class StrategyFactory
 * @package components\proxy\recoveryStrategies
 */
class StrategyFactory
{
    /**
     * Errors types
     */
    public const ERROR_TYPE_CLASSIC = 'classic';
    public const ERROR_TYPE_AKAMAI = 'akamai';

    /**
     * @param string $errorType
     * @return StrategyInterface
     * @throws UndefinedErrorTypeException
     */
    public function create(string $errorType): StrategyInterface
    {
        switch ($errorType) {
            case self::ERROR_TYPE_CLASSIC:
                return new LinearStrategy(.95, 5400, 0);

            case self::ERROR_TYPE_AKAMAI:
                return new LinearStrategy(0, 10, 600);

            default:
                WebLogWarning('Undefined error type; type=' . $errorType);
                throw new UndefinedErrorTypeException('Undefined error type: ' . $errorType);
        }
    }
}
