<?php
/**
 * @author Jomashop
 */

namespace JomaShop\NewRelicMonitoring\Plugin;

use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema;

class NewRelicMonitoring
{
    /**
     * @var \Magento\NewRelicReporting\Model\NewRelicWrapper
     */
    private $newRelicWrapper;

    /**
     * @var \JomaShop\NewRelicMonitoring\Helper\NewRelicReportData
     */
    private $dataHelper;

    /**
     * NewRelicMonitoring constructor.
     * @param \Magento\NewRelicReporting\Model\NewRelicWrapper $newRelicWrapper
     * @param \JomaShop\NewRelicMonitoring\Helper\NewRelicReportData $dataHelper
     */
    public function __construct(
        \Magento\NewRelicReporting\Model\NewRelicWrapper $newRelicWrapper,
        \JomaShop\NewRelicMonitoring\Helper\NewRelicReportData $dataHelper
    ) {
        $this->newRelicWrapper = $newRelicWrapper;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Rename a GraphQl transaction for New Relic before processing it
     * @param QueryProcessor $subject
     * @param Schema $schema
     * @param string $source
     * @param ContextInterface|null $contextValue
     * @param array|null $variableValues
     * @param string|null $operationName
     */
    public function beforeProcess(
        QueryProcessor $subject,
        Schema $schema,
        string $source,
        ContextInterface $contextValue = null,
        array $variableValues = null,
        string $operationName = null
    ) {
        if (!$this->newRelicWrapper->isExtensionInstalled()) {
            // No need to extract the data
            return;
        }

        $transactionData = $this->dataHelper->getTransactionData($schema, $source);
        if (empty($transactionData)) {
            return;
        }

        $this->newRelicWrapper->setTransactionName($transactionData['transactionName']);
        $this->newRelicWrapper->addCustomParameter('GraphqlNumberOfFields', $transactionData['fieldCount']);
        $this->newRelicWrapper->addCustomParameter('FieldNames', implode('|', $transactionData['fieldNames']));
    }
}
