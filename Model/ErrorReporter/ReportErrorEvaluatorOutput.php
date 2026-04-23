<?php
/**
 * @author Jomashop
 */

namespace JomaShop\NewRelicMonitoring\Model\ErrorReporter;

class ReportErrorEvaluatorOutput
{
    public function __construct(
        private readonly bool $isReportAsCustomParameter,
        private readonly ?array $extraData
    ) {
    }

    public function getIsReportAsCustomParameter(): bool
    {
        return $this->isReportAsCustomParameter;
    }

    public function getExtraData(): array
    {
        return $this->extraData ?? [];
    }
}
