<?php
/**
 * @author Jomashop
 */

namespace JomaShop\NewRelicMonitoring\Model\ErrorReporter;

use Throwable;

interface ReportErrorEvaluatorInterface
{
    public function evaluateError(Throwable $error): ReportErrorEvaluatorOutput;
}
