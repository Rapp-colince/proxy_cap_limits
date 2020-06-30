<?php

namespace components\proxy\recoveryStrategies;

use models\ProxyCapLimit;
use models\ProxyCapLimitError;

/**
 * Class LinearStrategy Linear strategy for calculate proxyCapLimit recovery
 * @package components\proxy\recoveryStrategies
 */
class LinearStrategy implements StrategyInterface
{
    /**
     * @var float
     */
    private $reduceFactor;

    /**
     * @var int
     */
    private $recoveryTimespan;

    /**
     * @var int
     */
    private $coolDown;

    /**
     * LinearStrategy constructor.
     * @param float $reduceFactor
     * @param int $recoveryTimespan
     * @param int $coolDown
     */
    public function __construct(
        float $reduceFactor,
        int $recoveryTimespan,
        int $coolDown
    ) {
        $this->reduceFactor = $reduceFactor;
        $this->recoveryTimespan = $recoveryTimespan;
        $this->coolDown = $coolDown;
    }

    /**
     * @param ProxyCapLimit $proxyCapLimit
     * @param ProxyCapLimitError $proxyCapLimitError
     * @param int $currentTs
     * @return int
     */
    public function getCurrentCap(ProxyCapLimit $proxyCapLimit, ProxyCapLimitError $proxyCapLimitError, int $currentTs): int
    {
        $currentCap = $proxyCapLimit->cap_max;

        $lastCaughtTs = strtotime($proxyCapLimitError->last_caught_dttm);
        if ($lastCaughtTs <= $currentTs && !$this->isOverdue($proxyCapLimitError, $currentTs)) {

            $currentCap = floor($proxyCapLimit->cap_max * $proxyCapLimitError->reduction_factor);

            $recoveryStartTs = $lastCaughtTs + $this->coolDown;
            if ($currentTs >= $recoveryStartTs) {
                $currentCap = floor($proxyCapLimit->cap_max * ($currentTs - $recoveryStartTs) / $this->recoveryTimespan);
                $currentCap += floor($proxyCapLimit->cap_max * $proxyCapLimitError->reduction_factor);

                if ($currentCap > $proxyCapLimit->cap_max) {
                    $currentCap = $proxyCapLimit->cap_max;
                }
            }
        }

        return $currentCap;
    }

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
    ): float {
        $reduceFactor = 1;

        if ($proxyCapLimit->cap_max > 0) {
            $currentCap = $this->getCurrentCap($proxyCapLimit, $proxyCapLimitError, $currentTs);
            $reduceFactor = $currentCap * $this->reduceFactor / $proxyCapLimit->cap_max;
        }

        $reduceFactor = floor($reduceFactor * 100) / 100;
        return $reduceFactor;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->reduceFactor < 0 || $this->reduceFactor > 1) {
            return false;
        }
        if ($this->recoveryTimespan <= 0) {
            return false;
        }
        if ($this->coolDown < 0) {
            return false;
        }
        return true;
    }

    /**
     * Checks strategy shelf life
     * @param ProxyCapLimitError $proxyCapLimitError
     * @param int $currentTs
     * @return bool
     */
    public function isOverdue(ProxyCapLimitError $proxyCapLimitError, int $currentTs): bool
    {
        $shelfLife = strtotime($proxyCapLimitError->last_caught_dttm) + $this->recoveryTimespan + $this->coolDown;
        return $shelfLife < $currentTs;
    }
}
