<?php

namespace Expose;

class Manager
{
    /**
     * Data to run the filter validation rules on
     * @var array
     */
    private $data = null;

    /**
     * Set of filters to execute
     * @var \Expose\FilterCollection
     */
    private $filters = null;

    /**
     * Overall impact score of the filter execution
     * @var integer
     */
    private $impact = 0;

    /**
     * Report results from the filter execution
     * @var array
     */
    private $reports = array();

    /**
     * Names of varaibles to ignore (exceptions to the rules)
     * @var array
     */
    private $exceptions = array();

    /**
     * Data "paths" to restrict checking to
     * @var array
     */
    private $restrctions = array();

    /**
     * Logger instance
     * @var object
     */
    private $logger = null;

    /**
     * Configuration object
     * @var \Expose\Config
     */
    private $config = null;

    /**
     * Init the object and assign the filters
     * 
     * @param \Expose\FilterCollection $filters Set of filters
     */
    public function __construct(\Expose\FilterCollection $filters, $logger = null)
    {
        $this->setFilters($filters);

        if ($logger !== null) {
            $this->setLogger($logger);
        }
    }

    /**
     * Run the filters against the given data
     * 
     * @param array $data Data to run filters against
     */
    public function run(array $data)
    {
        $this->getLogger()->info('Executing on data '.md5(print_r($data, true)));

        $this->setData($data);
        $data = $this->getData();
        $impact = $this->impact;

        $path = array();
        $filterMatches = $this->runFilters($data, $path);
    }

    /**
     * Run through the filters on the given data
     * 
     * @param array $data Data to check
     * @param array $path Current "path" in the data
     * @param integer $lvl Current nesting level
     * @return array Set of filter matches
     */
    public function runFilters($data, $path, $lvl = 0)
    {
        $filterMatches = array();
        $restrictions = $this->getRestrictions();

        foreach ($data as $index => $value) {
            if (count($path) > $lvl) {
                $path = array_slice($path, 0, $lvl);
            }
            $path[] = $index;

            // see if it's an exception
            if ($this->isException(implode('.', $path))) {
                $this->getLogger()->info('Exception found on '.implode('.', $path));
                continue;
            }

            if (is_array($value)) {
                $l = $lvl+1;
                $filterMatches = array_merge(
                    $filterMatches,
                    $this->runFilters($value, $path, $l)
                );
            } else {
                
                $p = implode('.', $path);

                // See if we have restrictions & if the path matches
                if (!empty($restrictions) && !in_array($p, $restrictions)) {
                    $this->getLogger()->info(
                        'Restrictions enabled, no match on path '.implode('.', $path),
                        array('restrictions' => $restrictions)
                    );
                    continue;
                }

                foreach ($this->getFilters() as $index => $filter) {
                    if ($filter->execute($value) === true) {
                        $this->getLogger()->info(
                            'Match found on Filter ID '.$filter->getId(),
                            array($filter->toArray())
                        );
                        $filterMatches[] = $filter;

                        $report = new \Expose\Report($index, $value);
                        $report->addFilterMatch($filter);
                        $this->reports[] = $report;

                        $this->impact += $filter->getImpact();
                    }
                }
            }
        }
        return $filterMatches;
    }

    /**
     * Get the current set of reports
     * 
     * @return array Set of \Expose\Reports
     */
    public function getReports()
    {
        return $this->reports;
    }

    /**
     * Get the current overall impact score
     * 
     * @return integer Impact score
     */
    public function getImpact()
    {
        return $this->impact;
    }

    /**
     * Set the overall impact value of the execution
     * 
     * @param integer $impact Impact value
     */
    public function setImpact($impact)
    {
        $this->impact = $impact;
    }

    /**
     * Set the source data for the execution
     * 
     * @param array $data Data to validate
     */
    public function setData(array $data)
    {
        $this->data = new \Expose\DataCollection($data);
    }

    /**
     * Get the current source data
     * 
     * @return array Source data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set the filters for the current validation
     * 
     * @param \Expose\FilterCollection $filters Filter collection
     */
    public function setFilters(\Expose\FilterCollection $filters)
    {
        $this->filters = $filters;
    }

    /**
     * Get the current set of filters
     * 
     * @return \Expose\FilterCollection Filter collection
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Add a variable name for an exception
     * 
     * @param string $varName Variable name
     */
    public function setException($path)
    {
        $path = (!is_array($path)) ? array($path) : $path;
        $this->exceptions = array_merge($this->exceptions, $path);
    }

    /**
     * Get a list of all exceptions
     * 
     * @return array Exception list
     */
    public function getExceptions()
    {
        return $this->exceptions;
    }

    /**
     * Add a path to restrict the checking to
     * 
     * @param string|array $path Path(s) to add to the restrictions
     */
    public function setRestriction($path)
    {
        $path = (!is_array($path)) ? array($path) : $path;
        $this->restrctions = array_merge($this->restrctions, $path);
    }

    /**
     * Get the list of all current restrictions
     * 
     * @return array Set of restrictions
     */
    public function getRestrictions()
    {
        return $this->restrctions;
    }

    /**
     * Test to see if a variable is an exception
     *     Checks can be exceptions, so we preg_match it
     * 
     * @param string $path Variable "path" (Ex. "POST.foo.bar")
     * @return boolean Found/not found
     */
    public function isException($path)
    {
        $isException = false;
        foreach ($this->exceptions as $exceptions) {
            $ex = str_replace('.', '\\.', $exceptions);
            if ($isException === false && preg_match('/^'.$ex.'$/', $path) !== 0) {
                $isException = true;
            }
        }

        return $isException;
    }

    /**
     * Set the current instance's logger object
     * 
     * @param \MonoLog\Logger $logger Logger instance
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get the current logger instance
     *     If it's not set, use Monolog to create a new one (default is for Mongo)
     * 
     * @return \Monolog\Logger
     */
    public function getLogger()
    {
        if ($this->logger === null) {
            // make a new Monolog logger for MongoDB
            $logger = new \Monolog\Logger('audit');
            $handler = new \Monolog\Handler\MongoDBHandler(new \Mongo(), 'expose', 'logs');
            $logger->pushHandler($handler);
            $this->setLogger($logger);
            return $logger;
        }

        return $this->logger;
    }

    /**
     * Set the configuration for the object
     * 
     * @param array|string $config Either an array of config settings
     *     or the path to the config file
     * @throws \InvalidArgumentException If config file doesn't exist
     */
    public function setConfig($config)
    {
        if (is_array($config)) {
            $this->config = new Config($config);
        } else {
            // see if it's a file path
            if (is_file($config)) {
                $cfg = parse_ini_file($config);
                $this->config = new Config($cfg);
            } else {
                throw new \InvalidArgumentException(
                    'Could not load configuration file '.$config
                );
            }
        }
    }

    /**
     * Get the configuration object/settings
     * 
     * @return \Expose\Config object
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Expose the current set of reports in the given format
     * 
     * @param string $format Fromat for the export
     * @return mixed Report output (or null if the export type isn't found)
     */
    public function export($format = 'text')
    {
        $className = '\\Expose\\Export\\'.ucwords(strtolower($format));
        if (class_exists($className)) {
            $export = new $className($this->getReports());
            return $export->render();
        }
        return null;
    }
}