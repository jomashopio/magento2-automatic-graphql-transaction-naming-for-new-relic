# Change Log
## v2.0.1

* PHP 8.4 Compatibility - Fixing Implicitly marking parameter as nullable is deprecated

## v2.0.0
* Improved error extraction for `LocalizedException` and `AggregateExceptionInterface`
* Support for using `newrelic_add_custom_parameter` instead of `newrelic_notice_error`.
    * This requires custom code providing a `ReportErrorEvaluatorInterface` which controls which errors are diverted
* Support for reading the `x-client-version` header to set ClientVersion
* Magento 2.4.4's NewRelic logging is disabled to prevent double work extracting the data
* If the GQL's AST is available, as in 2.4.7, the extracted data changes and should be improved.
* Transaction naming uses forward slashes only
    * This will 'break' all dashboards and queries that check or facet on the operation name

## v1.1.1
* Send requested fields as a custom parameter to NR

## v1.1.0
* Able to log GraphQl errors