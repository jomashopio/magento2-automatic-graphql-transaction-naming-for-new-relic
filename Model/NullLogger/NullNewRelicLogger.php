<?php
/**
 * @author Jomashop
 */

namespace JomaShop\NewRelicMonitoring\Model\NullLogger;

use Magento\GraphQl\Model\Query\Logger\LoggerInterface;

/**
 * Class NullNewRelicLogger
 */
class NullNewRelicLogger implements LoggerInterface
{

    public function execute(array $queryDetails)
    {
        // Do nothing
    }
}
