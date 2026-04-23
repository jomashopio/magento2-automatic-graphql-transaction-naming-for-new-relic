<?php

namespace JomaShop\NewRelicMonitoring\Plugin;

use Magento\GraphQl\Controller\GraphQl;
use Magento\Framework\App\RequestInterface;
use Magento\NewRelicReporting\Model\NewRelicWrapper;

class NewRelicClientVersion
{
    const CLIENT_VERSION_HEADER = 'x-client-version';

    /**
     * @param NewRelicWrapper $newRelicWrapper
     */
    public function __construct(
        private NewRelicWrapper $newRelicWrapper,
    ) {
    }

    public function beforeDispatch(
        GraphQl $subject,
        RequestInterface $request,
    ) {
        $clientVersion = $request->getHeader(self::CLIENT_VERSION_HEADER);
        $this->newRelicWrapper->addCustomParameter('ClientVersion', $clientVersion ?? 'N/A');
    }
}
