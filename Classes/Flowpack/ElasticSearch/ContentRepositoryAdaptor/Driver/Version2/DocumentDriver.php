<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version2;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\Version1;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Mapping\NodeTypeMappingBuilder;
use Flowpack\ElasticSearch\Domain\Model\Index;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * Fulltext Indexer Driver for Elastic version 2.x
 *
 * @Flow\Scope("singleton")
 */
class DocumentDriver extends Version1\DocumentDriver
{
    /**
     * @param Index $index
     * @param NodeInterface $node
     * @param string $documentIdentifier
     */
    public function deleteByDocumentIdentifier(Index $index, NodeInterface $node, $documentIdentifier)
    {
        $type = NodeTypeMappingBuilder::convertNodeTypeNameToMappingName($node->getNodeType()->getName());
        $result = $index->request('GET', '/_search?scroll=1m', [], json_encode([
            'sort' => ['_doc'],
            'query' => [
                'bool' => [
                    'must' => [
                        'ids' => [
                            'values' => [$documentIdentifier]
                        ]
                    ],
                    'must_not' => [
                        'term' => [
                            '_type' => $type
                        ]
                    ]
                ]
            ]
        ]));
        $treatedContent = $result->getTreatedContent();
        $scrollId = $treatedContent['_scroll_id'];
        $mapHitToDeleteRequest = function ($hit) {
            $bulkRequest[] = json_encode([
                'delete' => [
                    '_type' => $hit['_type'],
                    '_id' => $hit['_id']
                ]
            ]);
        };
        $bulkRequest = [];
        while (isset($treatedContent['hits']['hits']) && $treatedContent['hits']['hits'] !== []) {
            $hits = $treatedContent['hits']['hits'];
            $bulkRequest = array_merge($bulkRequest, array_map($mapHitToDeleteRequest, $hits));
            $result = $index->request('GET', '/_search/scroll?scroll=1m', [], $scrollId, false);
            $treatedContent = $result->getTreatedContent();
        }
        $this->logger->log(sprintf('NodeIndexer: Check duplicate nodes for %s (%s), found %d document(s)', $documentIdentifier, $type, count($bulkRequest)), LOG_DEBUG, null, 'ElasticSearch (CR)');
        if ($bulkRequest !== []) {
            $index->request('POST', '/_bulk', [], implode("\n", $bulkRequest));
        }
        $this->searchClient->request('DELETE', '/_search/scroll', [], json_encode([
            'scroll_id' => [
                $scrollId
            ]
        ]));
    }
}