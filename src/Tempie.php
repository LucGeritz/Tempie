<?php

namespace Tigrez\Tempie;

class Tempie
{
	private $config;
	private $content;

	private $data = [];
	private $file;

	private $filterCache = [];

	/**
	 * Log an error
	 * But only if a log callback is known
	 *
	 * @param string $errorText the text to log
	 */
	private function logError($errorText)
	{
		if ($this->config['logCallback']) {
			call_user_func($this->config['logCallback'], "** tempie err ({$this->file}): $errorText");
		}
	}

	/**
	 * Apply filters on value
	 * Unknown filters will not stop processing of the filter chain
	 * @param string $value the value to apply filters on
	 * @param array filter names
	 */
	private function filter($value, array $filters)
	{

		foreach ($filters as $filter) {
			// split filter name  from parameters
			$filterParts = explode('(', $filter);
			$params = '';
			if (count($filterParts) > 1) {
				$params = rtrim($filterParts[1], ')');
			}

			// Do we have it cached?
			$filterObject = $this->filterCache[$filterParts[0]] ?? null;
			if (!$filterObject) {
				// nope..
				// deduct class name from filtername
				$filterClass = $filterParts[0] . 'Filter';
				if (class_exists($filterClass)) {
					$filterObject = new $filterClass();
					if(is_a($filterObject, 'Tigrez\Tempie\Filter' )){
						// add to cache
						$this->filterCache[$filterParts[0]] = $filterObject;
					}
					else{
						$this->logError(sprintf('The %s class is not an implementation of Tigrez\Tempie\Filter', $filterClass));
						$filterObject = null;
					}
				} else {
					$this->logError(sprintf('%s filter assumes existence of class %s which cannot be found', $filterParts[0], $filterClass));
				}
			}
			// if we managed to create the filter
			if($filterObject){
				// .. so let us use it
				$value = $filterObject->filter($value, explode(',', $params));
			}
		}

		return $value;
	}

	/**
	 * Get a value from the data array
	 * Value {a.b.c} is offered as $keys [a,b,c]
	 * function tries to match it to [a => [b=>[c=>'value']]
	 *
	 * @param array $keys see above
	 * @param array $data array of data as loaded into the template
	 * @return mixed string value from data array or null if not found
	 */
	private function getValueFromArray($keys, $data)
	{
		if (!is_array($keys)) {
			$keys = explode('.', $keys);
		}

		foreach ($keys as $key) {
			if (array_key_exists($key, $data)) {
				$data = $data[$key];
				if (is_a($data, 'Tigrez\Tempie')) {
					$data = $data->render();
				}
			} else {
				return null;
			}
		}
		return $data;
	}


	private function findInnerMostTag($text, $tags)
	{
		// gather positions of all tags
		$positions = [];
		for ($tag = 0; $tag < 2; $tag++) {
			$start = 0;
			while (($pos = stripos($text, $tags[$tag], $start)) !== false) {
				$positions[$pos] = $tag;
				if ($tag == 1) break;
				$start = $pos + strlen($tags[$tag]);
			}
		}
		// find innermost part
		if ($positions) {
			ksort($positions);
			foreach ($positions as $key => $value) {
				if ($value == 1) {
					$to = $key;
					break;
				} else {
					$from = $key;
				}
			}
			if (!isset($from)) {
				$this->logError(sprintf('%s without %s', $tags[1], $tags[0]));
				return [];
			}
			if (!isset($to)) {
				$this->logError(sprintf('%s without %s', $tags[0], $tags[1]));
				return [];
			}

			// from & to are found, return in convenient format
			return ['from' => $from, 'length' => ($to - $from) + strlen($tags[1])];
		}

		return [];
	}

	private function resolveForEach($text)
	{

		$positions = $this->findInnerMostTag($text, $this->config['foreachTags']);

		while ($positions) {
			$placeHolder = substr($text, $positions['from'], $positions['length']);
			$expression = substr($placeHolder, strlen($this->config['foreachTags'][0]), -strlen($this->config['foreachTags'][1]));
			$parts = explode($this->config['foreachSeparator'], $expression);
			if (count($parts) == 1) {
				$this->logError('foreach without body');
				return '';
			}

			$varNames = explode(' as ', $parts[0]);
			if (count($varNames) < 2) {
				$this->logError(sprintf("'%s' is not a valid foreach expression"));
				return '';
			}

			// isolate vaiable names, remove optional tags
			$rowsVariable = str_replace($this->config['varTags'][0], '', $varNames[0]);
			$rowsVariable = trim(str_replace($this->config['varTags'][1], '', $rowsVariable));
			$rowVariable = str_replace($this->config['varTags'][0], '', $varNames[1]);
			$rowVariable = trim(str_replace($this->config['varTags'][1], '', $rowVariable));

			array_shift($parts);
			// reconstruct template part
			$template = implode($this->config['foreachSeparator'], $parts);
			$rows = $this->getValueFromArray($rowsVariable, $this->data);

			if (!is_array($rows)) {
				$this->logError(sprintf("'%s' is not an array", $rowsVariable));
				return '';
			}

			$resolvedTemplate = '';

			foreach ($rows as $key => $value) {
				$resolvedLine = $template;
				$resolvedLine = str_replace($rowVariable, $rowsVariable . '.' . $key, $resolvedLine);
				$resolvedTemplate .= $resolvedLine;
			}

			$text = str_replace($placeHolder, $resolvedTemplate, $text);

			$positions = $this->findInnerMostTag($text, $this->config['foreachTags']);
		}

		return $text;
	}

	private function resolveIf($text)
	{

		$this->error = '';

		$positions = $this->findInnerMostTag($text, $this->config['ifTags']);

		while ($positions) {
			// extract the if expression from content text
			$placeHolder = substr($text, $positions['from'], $positions['length']);
			// remove tags to get expression
			$expression = substr($placeHolder, strlen($this->config['ifTags'][0]), -strlen($this->config['ifTags'][1]));
			$parts = explode($this->config['ifSeparator'], $expression);

			// isolate varnames, remove optional tags
			$varName = str_replace($this->config['varTags'][0], '', $parts[0]);
			$varName = trim(str_replace($this->config['varTags'][1], '', $varName));
			$not = $varName[0] == '!';
			$varName = ltrim($varName, '!');

			array_shift($parts);
			// reconstruct template part
			$template = implode($this->config['ifSeparator'], $parts);

			$value = $this->getValueFromArray($varName, $this->data);
			if ($not) {
				$value = !$value;
			}

			if ($value) {
				// expression is truthy, re-insert template
				$text = str_replace($placeHolder, $template, $text);
			} else {
				// expression is falsy, remove template
				$text = str_replace($placeHolder, '', $text);
			}
			$positions = $this->findInnerMostTag($text, $this->config['ifTags']);
		}

		return $text;
	}

	private function resolveRemark($text)
	{

		$positions = $this->findInnerMostTag($text, $this->config['remarkTags']);

		while ($positions) {
			// extract the remark from content text
			$placeHolder = substr($text, $positions['from'], $positions['length']);
			// remove it
			$text = str_replace($placeHolder, '', $text);
			$positions = $this->findInnerMostTag($text, $this->config['remarkTags']);
		}

		return $text;
	}

	private function resolveVariables($text)
	{
		// resolve variables
		while (($pos1 = strpos($text, $this->config['varTags'][0])) !== false) {

			$pos2 = strpos($text, $this->config['varTags'][1]);
			if ($pos2 !== false) {
				$pos2 = $pos2 + strlen($this->config['varTags'][1]) - 1;
			}
			$placeHolder = substr($text, $pos1, ($pos2 - $pos1) + 1);
			$varNamesStr = substr($placeHolder, strlen($this->config['varTags'][0]), -strlen($this->config['varTags'][1]));

			$filters = explode('|', $varNamesStr);
			if (count($filters) > 1) {
				$varNamesStr = $filters[0];
				array_shift($filters);
			}

			$varNames = explode('.', $varNamesStr);

			$value = $this->getValueFromArray($varNames, $this->data);

			if ($value) {
				if ($filters) {
					$value = $this->filter($value, $filters);
				}
				$text = str_replace($placeHolder, $value, $text);
			} else {
				$text = str_replace($placeHolder, '', $text);
			}
		}

		return $text;
	}

	private function resolve($text)
	{
		$text = $this->resolveRemark($text);
		$text = $this->resolveIf($text);
		$text = $this->resolveForeach($text);
		$text = $this->resolveVariables($text);

		return $text;
	}

	public function load(array $data)
	{
		$this->data = $data;
		return $this;
	}

	public function setFilterCache(FilterCache $filterCache)
	{
		$this->filterCache = $filterCache;
	}

	/**
	 * configure template
	 *
	 * @see readme.md
	 * @param array $config array of string valuename => string value
	 */
	public function config(array $config)
	{
		$this->config = array_merge($this->config, $config);
		return $this;
	}

	public function render()
	{
		$text = null;
		$text = $this->resolve($this->content);

		return $text;
	}

	public function __construct($fileOrString)
	{

		if (is_file($fileOrString)) {
			$this->file = $fileOrString;
			$this->content = file_get_contents($fileOrString);
		} else {
			$this->file = '';
			$this->content = $fileOrString;
		}

		// language definition, defaults
		$this->config['varTags'] = ['{{', '}}'];
		$this->config['foreachTags'] = ['[foreach]', '[/foreach]'];
		$this->config['foreachSeparator'] = '->';
		$this->config['ifTags'] = ['[if]', '[/if]'];
		$this->config['ifSeparator'] = '->';
		$this->config['remarkTags'] = ['[*]', '[/*]'];

		$this->config['logCallback'] = null;
	}
}
