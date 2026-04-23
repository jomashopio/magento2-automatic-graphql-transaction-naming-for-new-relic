<?php
/**
 * @author Jomashop
 */
namespace JomaShop\NewRelicMonitoring\Plugin;

use Magento\Framework\Exception\AggregateExceptionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\ErrorHandler as QueryErrorHandler;
use Magento\NewRelicReporting\Model\NewRelicWrapper;
use Throwable;

class ErrorHandler
{
    public function __construct(
        private readonly NewRelicWrapper $newRelicWrapper,
    ) {
    }

    public function beforeHandle(QueryErrorHandler $errorHandler, array $errors, callable $formatter)
    {
        // We want to find the first error that has a previous
        // - errors are expected to be wrapped by graphql
        $firstPreviousError = array_filter(
            array_map(
                fn($error) => $error->getPrevious(),
                $errors
            ),
        )[0] ?? null;
        if (!$firstPreviousError) {
            return;
        }

        // Get error data
        $errorData = $this->getErrorData($firstPreviousError);

        // Report data
        $this->newRelicWrapper->reportError($errorData['errorToReport']);
        foreach ($errorData['extraData'] as $key => $value) {
            $this->newRelicWrapper->addCustomParameter($key, $value);
        }
    }

    private function getErrorData(Throwable $error): array
    {
        // Get inner exception if available
        // - See eg: \Magento\QuoteGraphQl\Model\Cart\AddSimpleProductToCart::execute
        $firstInnerError = $error instanceof AggregateExceptionInterface
            ? $error->getErrors()[0] ?? null
            : null;

        // Prepare data
        $data = [
            'errorToReport' => $firstInnerError ?? $error,
            'extraData' => []
        ];

        // Add Aggregate exception message (As we are using the first inner error to report)
        if ($firstInnerError instanceof AggregateExceptionInterface) {
            $data['extraData']['ExceptionAggregateMessage'] = $firstInnerError->getMessage();
        }

        return $data;
    }
}
