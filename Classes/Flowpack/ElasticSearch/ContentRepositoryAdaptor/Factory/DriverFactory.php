<?php
namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor\Factory;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\DocumentDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\DriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexerDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\QueryInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\DriverConfigurationException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface;
use TYPO3\Flow\Annotations as Flow;

/**
 * A factory for creating Elastic Drivers
 *
 * @Flow\Scope("singleton")
 */
class DriverFactory
{
    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="driver.mapping")
     */
    protected $mapping;

    /**
     * @var int
     * @Flow\InjectConfiguration(path="driver.version")
     */
    protected $driverVersion;

    /**
     * @return QueryInterface
     */
    public function createQuery()
    {
        return $this->resolve('query');
    }

    /**
     * @return DocumentDriverInterface
     */
    public function createDocumentDriver()
    {
        return $this->resolve('document');
    }

    /**
     * @return IndexerDriverInterface
     */
    public function createIndexerDriver()
    {
        return $this->resolve('indexer');
    }

    /**
     * @return IndexerDriverInterface
     */
    public function createIndexManagementDriver()
    {
        return $this->resolve('indexManagement');
    }

    /**
     * @return IndexerDriverInterface
     */
    public function createRequestDriver()
    {
        return $this->resolve('request');
    }

    /**
     * @return IndexerDriverInterface
     */
    public function createSystemDriver()
    {
        return $this->resolve('system');
    }

    /**
     * @param string $type
     * @return mixed
     * @throws DriverConfigurationException
     */
    protected function resolve($type)
    {
        $version = trim($this->driverVersion);
        if (trim($this->driverVersion) === '' || !isset($this->mapping[$version][$type]['className'])) {
            throw new DriverConfigurationException(sprintf('Missing or wrongly configured driver type "%s" with the given version: %s', $type, $version ?: '[missing]'), 1485933538);
        }

        $className = trim($this->mapping[$version][$type]['className']);

        $this->logger->log(sprintf('Load %s implementation for Elastic %s (%s)', $type, $version, $className), LOG_DEBUG);

        if (!isset($this->mapping[$version][$type]['arguments'])) {
            return new $className();
        }
        return new $className(...array_values($this->mapping[$version][$type]['arguments']));
    }
}