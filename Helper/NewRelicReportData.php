<?php
/**
 * @author Jomashop
 */

namespace JomaShop\NewRelicMonitoring\Helper;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Visitor;
use Magento\Framework\GraphQl\Schema;
use GraphQL\Type\Definition\ObjectType;

class NewRelicReportData
{
    private const PREFIX = '/GraphQl/Controller/GraphQl';
    private const SEPARATOR = '/';
    private const MULTIPLE_QUERIES_FLAG = 'Multiple';

    /**
     * Get transaction data from GraphQl schema
     * @param Schema $schema
     * @param DocumentNode|string $querySource
     * @return array
     */
    public function getTransactionData(Schema $schema, DocumentNode|string $querySource)
    {
        // Check Schema
        $gqlFieldsInfo = $this->getGqlFieldsInfo($schema);
        if (!$gqlFieldsInfo) {
            return [];
        }

        if (is_string($querySource)) {
            // Extract from source as string
            // - The results here are not equivilent to as from DocumentNode
            // - (transactionName should be the same or similar in most cases)
            $gqlInfo = $this->extractGqlInfo($gqlFieldsInfo);
            return [
                'transactionName' => $this->buildTransactionName(
                    $gqlInfo['gql_call_type'],
                    $this->buildGqlCallName(
                        $this->getOperationNameFromQueryString($querySource),
                        $gqlInfo['all_field_names'],
                        $gqlInfo['last_field_name']
                    )
                ),
                'fieldCount' => $gqlInfo['field_count'],
                'fieldNames' => $gqlInfo['all_field_names'],
            ];
        }

        $gqlInfo = $this->extractGqlInfoFromAst($querySource);
        return [
            'transactionName' => $this->buildTransactionName(
                $gqlInfo['operation_type'],
                $this->buildGqlCallName(
                    $gqlInfo['operation_name'],
                    $gqlInfo['operation_field_names'],
                    $gqlInfo['operation_first_field_alias_or_name'],
                )
            ),
            'fieldCount' => $gqlInfo['field_count'],
            'fieldNames' => $gqlInfo['operation_field_names'],
        ];

    }

    private function buildGqlCallName($operationName, $fieldsUsed, $firstFieldName)
    {
        return $operationName ?: (
            count($fieldsUsed) > 1
                ? self::MULTIPLE_QUERIES_FLAG
                : $firstFieldName
            );
    }

    /**
     * @param ObjectType $gqlFieldsInfo
     * @return array
     */
    private function extractGqlInfo(ObjectType $gqlFieldsInfo)
    {
        $gqlFields = $gqlFieldsInfo->getFields();
        return [
            'field_count' => count($gqlFields),
            'gql_call_type' => $gqlFieldsInfo->name,
            'last_field_name' =>  array_key_last($gqlFields),
            'all_field_names' => array_keys($gqlFields),
        ];
    }

    /**
     * @param $schema
     * @return ObjectType
     */
    private function getGqlFieldsInfo($schema)
    {
        if (!$schema) {
            return null;
        }

        $schemaConfig = $schema->getConfig();
        if (!$schemaConfig) {
            return null;
        }

        // Mutation takes priority because the output is processed first, which will be indicated as a Query
        $hasMutationFields = count($schemaConfig->getMutation()->getFields());
        return $hasMutationFields ? $schemaConfig->getMutation() : $schemaConfig->getQuery();
    }

    /**
     * Build a transaction name based on query type and operation name
     * format: /GraphQl/Controller/GraphQl/{operation name|(query|mutation)}/{name|Multiple}
     * @param $gqlCallType
     * @param string $operationName
     * @return string
     */
    private function buildTransactionName($gqlCallType, $operationName): string
    {
        return self::PREFIX . self::SEPARATOR . $gqlCallType . self::SEPARATOR . $operationName;
    }

    /**
     * Extracts some relevant data from a DocumentNode
     *
     * @param DocumentNode $ast
     * @return array
     * @throws \Exception
     */
    public function extractGqlInfoFromAst(DocumentNode $ast)
    {
        $firstOperationName = null;
        $operationType = null;
        $operationTypeOperationName = null;
        $fieldNodeNames = [];
        $allOperationTopLevelFields = [];
        Visitor::visit($ast, [
            NodeKind::FIELD =>
                function (FieldNode $fieldNode) use (&$fieldNodeNames) {
                    $fieldNodeNames[] = $fieldNode->name->value ?? null;
                    return $fieldNode->selectionSet ? null : Visitor::skipNode();
                },
            NodeKind::OPERATION_DEFINITION =>
                function (OperationDefinitionNode $operationDefinitionNode) use (
                    &$operationType,
                    &$operationTypeOperationName,
                    &$allOperationTopLevelFields,
                    &$firstOperationName,
                ) {
                    // Gather all operation 'operation' names
                    $operationFields = $operationDefinitionNode
                        ->selectionSet
                        ->selections;
                    foreach ($operationFields as $operationField) {
                        /** @var FieldNode $operationField */
                        $allOperationTopLevelFields[] = $operationField->name->value;
                    }

                    // Set the first operation name
                    // Priorities Mutations
                    if ($operationType !== null) {
                        if ($operationDefinitionNode->operation !== 'mutation') {
                            // We have a value and not a mutation (Skip)
                            return;
                        }

                        if ($operationType === 'mutation') {
                            // Keep the first mutation found (Skip)
                            return;
                        }
                    }

                    // Set first operation or override the non-mutation
                    $operationType = $operationDefinitionNode->operation;
                    $firstOperationName = $operationDefinitionNode->name->value ?? '';

                    /** @var FieldNode $firstField */
                    $firstField = $operationFields[0];
                    $operationTypeOperationName = $firstField->alias->value ?? $firstField->name->value;
                },
        ]);

        $filteredFieldNames = array_filter($fieldNodeNames, fn($name) => $name && $name !== '__typename');
        return [
            'operation_type' => ucfirst($operationType),
            'operation_name' => $firstOperationName,
            'operation_field_names' => $allOperationTopLevelFields,
            'operation_first_field_alias_or_name' => $operationTypeOperationName,
            'field_count' => count($filteredFieldNames),
        ];
    }

    /**
     * Get operation name from query
     * @param string $query
     * @return string
     */
    public function getOperationNameFromQueryString(string $query)
    {
        // Get the string before query input, which is indicated by a '{'
        $operationBeginningSegment = substr($query, 0, stripos($query, '{'));
        if (!$operationBeginningSegment) {
            return '';
        }

        $operationName = '';
        if (preg_match('/(query|mutation)/', $operationBeginningSegment, $matches, PREG_OFFSET_CAPTURE)) {
            $strQueryOrMutation = $matches[0][0];
            // operation name is in between operation type and variable declaration
            $operationName = trim($this->getSubString($strQueryOrMutation, '(', $operationBeginningSegment));
        }

        return $operationName;
    }

    /**
     * Get string in between two strings
     * @param $startingStr
     * @param $endingStr
     * @param $str
     * @return string
     */
    private function getSubString($startingStr, $endingStr, $str)
    {
        $subStrStart = strpos($str, $startingStr);
        $subStrStart += strlen($startingStr);

        // Get length of the substring
        $hasEndingStr = (strpos($str, $endingStr, $subStrStart)) !== false;
        $lengthOfSubstr = $hasEndingStr
            ? (strpos($str, $endingStr, $subStrStart) - $subStrStart)
            : (strlen($str) - $subStrStart);

        return substr($str, $subStrStart, $lengthOfSubstr);
    }
}
