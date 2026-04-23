<?php
/**
 * @author Jomashop
 */

namespace JomaShop\NewRelicMonitoring\Model\NullLogger;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\GraphQl\Schema;
use Magento\GraphQl\Helper\Query\Logger\LogData;

/**
 * Class NullGetLogData
 */
class NullGetLogData extends LogData
{
    public function getLogData(
        RequestInterface $request,
        array $data,
        ?Schema $schema,
        ?HttpResponse $response
    ): array
    {
        return [];
    }
}
