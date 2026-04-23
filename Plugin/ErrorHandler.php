<?php
/**
 * @author Jomashop
 */
namespace JomaShop\NewRelicMonitoring\Plugin;

use JomaShop\NewRelicMonitoring\Model\ErrorReporter\ReportErrorEvaluatorInterface;
use Magento\Framework\Exception\AggregateExceptionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\ErrorHandler as QueryErrorHandler;
use Magento\NewRelicReporting\Model\NewRelicWrapper;
use Throwable;

class ErrorHandler
{
    public function __construct(
        private readonly NewRelicWrapper $newRelicWrapper,
        private readonly ?ReportErrorEvaluatorInterface $reportErrorEvaluator = null,
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
        $this->reportErrorOrAsCustomParameters($errorData['errorToReport']);
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

        // Add raw message from LocalizedException (With placeholders)
        if ($data['errorToReport'] instanceof LocalizedException) {
            $data['extraData']['ExceptionRawMessage'] = $data['errorToReport']->getRawMessage();
        }

        return $data;
    }

    private function reportErrorOrAsCustomParameters(Throwable $errorToReport): void
    {
        if (!$this->reportErrorEvaluator) {
            // Default to report as Error
            $this->newRelicWrapper->reportError($errorToReport);
            return;
        }

        $evaluationResult = $this->reportErrorEvaluator->evaluateError($errorToReport);
        if (!$evaluationResult->getIsReportAsCustomParameter()) {
            $this->newRelicWrapper->reportError($errorToReport);
            return;
        }

        // We want to send this as a custom event
        $this->newRelicWrapper->addCustomParameter('ExceptionIsSkipReportError', true);
        $this->newRelicWrapper->addCustomParameter('ExceptionClass', get_class($errorToReport));
        $this->newRelicWrapper->addCustomParameter('ExceptionMessage', $errorToReport->getMessage());

        // - Add headers (As this is not an error event)
        $this->maybeAddRequestHeadersForReportAsCustomParameter();

        // - Add extra data
        foreach ($evaluationResult->getExtraData() as $key => $value) {
            $this->newRelicWrapper->addCustomParameter($key, $value);
        }
    }

    private function maybeAddRequestHeadersForReportAsCustomParameter(): void
    {
        // We only want referer for now
        $newRelicFieldNameForHeader = 'request.headers.referer';
        $phpServerHeaderName = 'HTTP_REFERER';

        // Note: we are using server directly as NewRelic core code (likely) does not use Magento special logic
        $serverValue = $_SERVER[$phpServerHeaderName] ?? null;
        if (!$serverValue) {
            // No value, nothing to do
            return;
        }

        // This is the logic as described in attribute docs
        // (See: https://docs.newrelic.com/docs/apm/agents/php-agent/attributes/enable-or-disable-attributes/)
        // - Root level takes precedence for enabled.
        // - Destination enabled takes precedence over include and exclude.
        // - Attribute is included if the destination is enabled.
        // - Exclude always supersedes include.
        // - Keys are case sensitive.
        // - Use a star (\*) for wildcards.
        // - Most specific setting for a key takes priority.
        // - Include or exclude affects the specific destination.
        // Notes: This code is running a simplified version of the logic linked above
        // - ignoring wildcard logic
        // - ignoring prefix logic

        // No need to check this as if false all custom params are removed
        // |if (ini_get('newrelic.attributes.enabled') === 'false') {
        // |   return;
        // |}

        if (ini_get('newrelic.error_collector.attributes.enabled') === '0') {
            // If not enabled for error_collector it would not exist there (So don't add here)
            return;
        }

        // No need to check this as if in list will exclude the custom param
        // |if (str_contains(ini_get('newrelic.attributes.exclude'), $headerName)) {
        // |    return;
        // |}

        if (str_contains(ini_get('newrelic.attributes.include'), $newRelicFieldNameForHeader)) {
            // It will be included via normal include logic
            return;
        }

        // No need to check this as if false all matching params are removed
        // |if (str_contains(ini_get('newrelic.transaction_events.attributes.exclude'), $headerName)) {
        // |   return;
        // |}

        if (str_contains(ini_get('newrelic.transaction_events.attributes.include'), $newRelicFieldNameForHeader)) {
            // It will be included via normal include logic
            return;
        }

        if (str_contains(ini_get('newrelic.error_collector.attributes.exclude'), $newRelicFieldNameForHeader)) {
            // If not enabled for error_collector it would not exist there (So don't add here)
            return;
        }

        // Add value
        $this->newRelicWrapper->addCustomParameter($newRelicFieldNameForHeader, $serverValue);
    }
}
