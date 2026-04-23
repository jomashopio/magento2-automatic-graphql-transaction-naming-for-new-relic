<?php
/**
 * @author Jomashop
 */

namespace JomaShop\NewRelicMonitoring\Helper;

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
     * @param $querySource
     * @return array
     */
    public function getTransactionData(Schema $schema, $querySource)
    {
        // Check Schema
        $gqlFieldsInfo = $this->getGqlFieldsInfo($schema);
        if (!$gqlFieldsInfo) {
            return [];
        }

        // Extract Data
        $gqlInfo = $this->extractGqlInfo($gqlFieldsInfo);
        $operationName = $this->getOperationNameFromQueryString($querySource);

        // Determine Final Call Name
        $finalGqlCallName = empty($operationName)
            ? ($gqlInfo['field_count'] > 1 ? self::MULTIPLE_QUERIES_FLAG : $gqlInfo['first_field_name'])
            : $operationName;

        // Format and Return
        return [
            'transactionName' => $this->buildTransactionName($gqlInfo['gql_call_type'], $finalGqlCallName),
            'fieldCount' => $gqlInfo['field_count'],
            'fieldNames' => $gqlInfo['all_field_names'],
        ];
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
            'first_field_name' =>  array_key_first($gqlFields),
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
    private function buildTransactionName($gqlCallType, $operationName)
    {
        return self::PREFIX . self::SEPARATOR . $gqlCallType . self::SEPARATOR . $operationName;
    }

    /**
     * Get operation name from query
     * @param $query
     * @return string
     */
    public function getOperationNameFromQueryString($query)
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
