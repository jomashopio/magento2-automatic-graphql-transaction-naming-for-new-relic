# Magento 2 - Automatic GraphQL transaction naming for New Relic
New Relic's PHP agent has support for automatic transaction naming for REST and SOAP, but not GraphQL. Thus, this Magento 2 module is developed to support sending a GraphQL transaction name to New Relic automatically. 

## Details
A transaction is named based on the service class and method that handled the request, `/GraphQl/Controller/GraphQl\{operation name|(query|mutation)}\{name|Multiple}`.

The logic is explained as follows:
1. If the `operationName` field is set, use the operation name.
2. If the `operationName` field is not set, use the name of the query or mutation.
3. If a GraphQL query consists of multiple queries or mutations, the transaction would be indicated as 'Multiple'. Note that in any cases, the `operationName` field takes the priority.

## Installation
```
composer require jomashop/module-new-relic-monitoring-for-gql
```

## Examples

1. Operation name is set
```graphql
mutation createCustomerTest{
  createCustomer(
    input: {
      firstname: "Bob"
      lastname: "Loblaw"
      email: "test@example.com"
      password: "b0bl0bl@w"
      is_subscribed: true
    }
  ) {
    customer {
      firstname
      lastname
      email
      is_subscribed
    }
  }
}
```
In New Relic, the transaction name would be: `/GraphQl/Controller/GraphQl/Mutation/createCustomerTest`

2. Operation name is not set and only 1 query/mutation is requested
```graphql
mutation {
  createCustomer(
    input: {
      firstname: "Bob"
      lastname: "Loblaw"
      email: "test@example.com"
      password: "b0bl0bl@w"
      is_subscribed: true
    }
  ) {
    customer {
      firstname
      lastname
      email
      is_subscribed
    }
  }
}
```

In NR, the transaction name would be `/GraphQl/Controller/GraphQl/Mutation/createCustomer`

3. Operation name is not set and multiple queries/mutations are requested
```graphql
query {
  cmsBlocks(identifiers: "footer_links_block") {
    items {
      identifier
      title
      content
    }
  },
  storeConfig {
    id
    code
    website_id
    locale
    base_currency_code
    default_display_currency_code
    timezone
    weight_unit
    base_url
    base_link_url
    base_static_url
    base_media_url
    secure_base_url
    secure_base_link_url
    secure_base_static_url
    secure_base_media_url
    store_name
  }
}
```

In NR, the transaction name would be `/GraphQl/Controller/GraphQl/Query/Multiple`

---
### Other features
This module also supports using `newrelic_add_custom_parameter` instead of `newrelic_notice_error`.
- See ErrorHandler and ReportErrorEvaluatorInterface
- This can be used to reduce the number of `GraphQlInputException` / `GraphQlAuthorizationException` / `GraphQlAuthenticationException` which are logged as errors
- Data is set to ExceptionIsSkipReportError / ExceptionClass / ExceptionMessage

Error extraction:
- If the error is an `AggregateExceptionInterface` the module tries to use the inner error.
- If the error is a `LocalizedException` then ExceptionRawMessage is set as a custom parameter

If the header `x-client-version` is set then ClientVersion is set as a custom parameter
- This can be used to track the Frontend Version sending the GQL request

Core Magento logging from 2.4.4 is disabled as running both is does not provide extra value

If Magento >= 2.4.7 is used the extracted data changes
- The AST is parsed to extract various data
- <2.4.7 parses the string
