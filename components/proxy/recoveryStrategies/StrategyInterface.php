<?php

namespace components\proxy\recoveryStrategies;

use models\ProxyCapLimit;
use models\ProxyCapLimitError;

/**
 * Interface StrategyInterface
 */
interface StrategyInterface
{
    /**
     * @param ProxyCapLimit $proxyCapLimit
     * @param ProxyCapLimitError $proxyCapLimitError
     * @param int $currentTs
     * @return int
     */
    public function getCurrentCap(ProxyCapLimit $proxyCapLimit, ProxyCapLimitError $proxyCapLimitError, int $currentTs): int;

    /**
     * @param ProxyCapLimit $proxyCapLimit
     * @param ProxyCapLimitError $proxyCapLimitError
     * @param int $currentTs
     * @return float
     */
    public function calculateCurrentReductionFactor(
        ProxyCapLimit $proxyCapLimit,
        ProxyCapLimitError $proxyCapLimitError,
        int $currentTs
    ): float;

    /**
     * @return bool
     */
    public function isValid(): bool;

    /**
     * Checks strategy shelf life
     * @param ProxyCapLimitError $proxyCapLimitError
     * @param int $currentTs
     * @return bool
     */
    public function isOverdue(ProxyCapLimitError $proxyCapLimitError, int $currentTs): bool;
}
