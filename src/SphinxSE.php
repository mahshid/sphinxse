<?php namespace Citco;

use Citco\Exceptions\SphinxSEException;

class SphinxSE {

	protected $query;
	protected $limit;
	protected $offset;
	protected $mode;
	protected $sort;
	protected $index;
	protected $fieldweights;
	protected $filter;
	protected $range;
	protected $floatrange;
	protected $groupby;
	protected $groupsort;
	protected $host;
	protected $port;
	protected $ranker;
	protected $maxmatches;

	private $selects = [];

	public function __construct($config = [])
	{
		foreach ($config as $key => $value)
		{
			if (property_exists($this, $key))
			{
				$this->{$key} = $value;
			}
		}
	}

	/**
	 * Set searchd host name and port
	 *
	 * @param string  $host
	 * @param integer $port
	 *
	 */
	public function setServer($host, $port = 0)
	{
		$this->host = $host;
		$this->port = $port;
	}

	/**
	 * Set offset and count into result set, optionally set max-matches and cutoff limits
	 *
	 * @param integer $offset
	 * @param integer $limit
	 * @param integer $max
	 * @param integer $cutoff
	 *
	 */
	public function setLimits($offset, $limit, $max = 1000, $cutoff = 0)
	{
		if ($offset >= $max)
		{
			throw new SphinxSEException('Offset out of bounds exception.');
		}

		$this->offset = $offset;
		$this->limit = $limit;
		$this->maxmatches = $max;
	}

	/**
	 * Set maximum query time, in milliseconds, per-index. 0 means "do not limit"
	 *
	 * @param integer $max
	 *
	 */
	public function setMaxQueryTime($max)
	{
		$this->maxquerytime = $max;
	}

	/**
	 * Set matching mode
	 *
	 * @param integer $mode
	 *
	 */
	public function setMatchMode($mode)
	{
		$this->mode = $mode;
	}

	/**
	 * Set ranking mode
	 *
	 * @param string $ranker
	 * @param string $rankexpr
	 *
	 */
	public function setRankingMode($ranker, $rankexpr = '')
	{
		$this->ranker = $rankexpr ? $ranker . ':' . $rankexpr : $ranker;
	}

	/**
	 * Set matches sorting mode
	 *
	 * @param string $mode
	 * @param string $sortby
	 *
	 */
	public function setSortMode($mode, $sortby = '')
	{
		$this->sort = $mode . ':' . $sortby;
	}

	/**
	 * Bind per-field weights by name
	 *
	 * @param array $weights
	 *
	 */
	public function setFieldWeights(array $weights)
	{
		foreach ($weights as $field => $weight)
		{
			$this->fieldweights .= $field . ',' . $weight . ',';
		}

		$this->fieldweights = trim($this->fieldweights, ',');
	}

	/**
	 * Set index to use for search
	 *
	 * @param $index
	 *
	 */
	public function setIndex($index)
	{
		$this->index = $index;
	}

	/**
	 * Add select statement to the query
	 *
	 * @param $select
	 *
	 */
	public function setSelect($select)
	{
		$this->selects[] = $select;
	}

	/**
	 * Add keywords to search on specifid fields.
	 *
	 * @param        $fields
	 * @param        $value
	 * @param float  $quorum
	 * @param string $operator
	 *
	 */
	public function fieldQuery($fields, $value, $quorum = 0.8, $operator = '/')
	{
		if (is_array($fields))
		{
			$field_string = '@(' . implode(',', $fields) . ')';
		}
		else
		{
			$field_string = '@' . $fields;
		}

		$this->query .= $field_string . ' "' . $this->escapeString($value) . '"' . ($quorum ? $operator . $quorum : '') . ' ';
	}

	/**
	 * Set values filter; only match records where $attribute value is in (or not in) the given set
	 *
	 * @param string  $attribute attribute name
	 * @param array   $values    value set
	 * @param boolean $exclude   exclude results
	 *
	 */
	public function setFilter($attribute, array $values, $exclude = false)
	{
		if (empty($values))
		{
			throw new SphinxSEException('Can not assign a filter with empty values');
		}

		$this->filter[] = ($exclude ? '!' : '') . $attribute . ',' . implode(',', $values);
	}

	/**
	 * Set range filter; only match records if $attribute value between $min and $max (inclusive)
	 *
	 * @param string  $attribute attribute name
	 * @param integer $min       minimum attribute value
	 * @param integer $max       maximum attribute value
	 * @param boolean $exclude   exclude results
	 *
	 */
	public function setFilterRange($attribute, $min, $max, $exclude = false)
	{
		$this->range[] = ($exclude ? '!' : '') . $attribute . ",{$min},{$max}";
	}

	/**
	 * Set float range filter; only match records if $attribute value between $min and $max (inclusive)
	 *
	 * @param string  $attribute attribute name
	 * @param float   $min       minimum attribute value
	 * @param float   $max       maximum attribute value
	 * @param boolean $exclude   exclude results
	 *
	 */
	public function setFilterFloatRange($attribute, $min, $max, $exclude = false)
	{
		$this->floatrange[] = ($exclude ? '!' : '') . $attribute . ",{$min},{$max}";
	}

	/**
	 * Set grouping attribute and function
	 *
	 * @param string  $attribute attribute name
	 * @param integer $func      grouping function
	 * @param string  $groupsort group sorting clause
	 *
	 */
	public function setGroupBy($attribute, $func, $groupsort = '@group desc')
	{
		$this->groupby = $func . ':' . $attribute;
		$this->groupsort = $groupsort;
	}

	/**
	 * Set count-distinct attribute for group-by queries
	 *
	 * @param string $attribute attribute name
	 *
	 */
	public function setGroupDistinct($attribute)
	{
		$this->distinct = $attribute;
	}

	/**
	 * Set GEO anchor in select query
	 *
	 * @param string $attrlat  lat attr name
	 * @param string $attrlong lng attr name
	 * @param string $lat      lat value
	 * @param string $long     lng value
	 * @param string $alias    select alias
	 *
	 */
	public function setGeoAnchor($attrlat, $attrlong, $lat, $long, $alias = 'geodist')
	{
		$this->setSelect('GEODIST(' . $attrlat . ', ' . $attrlong . ', ' . $lat . ', ' . $long . ') AS ' . $alias);
	}

	public function toQuery()
	{
		$reflection = new \ReflectionClass($this);

		$properties = $reflection->getProperties();

		$query = empty($this->selects) ? '' : 'select=' . implode(',', $this->selects) . ';';

		foreach ($properties as $property)
		{
			$element = $this->{$property->name};

			if ($property->isProtected() && ! empty($element))
			{
				if (! is_array($element))
				{
					$element = [$element];
				}

				foreach ($element as $value)
				{
					if (starts_with($value, '!'))
					{
						$value = trim($value, '!');

						$exclude = true;
					}
					else
					{
						$exclude = false;
					}

					$query .= ($exclude ? '!' : '') . $property->name . '=' . $value . ';';
				}
			}
		}

		return $query;
	}

	/**
	 * Escapes characters that are treated as special operators by the query language parser
	 *
	 * @param string $string unescaped string
	 *
	 * @return string Escaped string.
	 */
	public function escapeString($string)
	{
		$string = addslashes($string);

		$from = ['(', ')', '|', '-', '!', '@', '~', '&', '/', '^', '$', '=', ';', "\x00", "\n", "\r", "\x1a"];
		$to = ['\(', '\)', '\|', '\-', '\!', '\@', '\~', '\&', '\/', '\^', '\$', '\=', '\;', "\\x00", "\\n", "\\r", "\\x1a"];

		return str_replace($from, $to, $string);
	}

	/**
	 * Return only query attribute.
	 *
	 * @return mixed
	 */
	public function getQuery()
	{
		return $this->query;
	}

	/**
	 * @param mixed $query
	 */
	public function setQuery($query)
	{
		$this->query = str_replace(';', '', $query);
	}

	/**
	 * @param mixed $query
	 */
	public function appendQuery($query)
	{
		$this->query .= str_replace(';', '', $query);
	}

	public function __call($name, $arguments)
	{
		if (empty($arguments))
		{
			throw new SphinxSEException('Need to provide field query');
		}

		$this->fieldQuery($name, $arguments[0]);
	}
}
